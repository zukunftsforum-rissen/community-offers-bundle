<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Database\Result;
use Contao\StringUtil;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class AccessRequestService
{
    private const ADDITIONAL_REQUEST_COOLDOWN = 600; // 10 Minuten

    public function __construct(
        private readonly RouterInterface $router,
        private readonly MailerInterface $mailer,
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * @param list<string> $requestedAreas
     *
     * @return 'ok'|'invalid_email'|'already_open'
     */
    public function tryCreateRequestAndSendDoiMail(string $firstname, string $lastname, string $email, string $street, string $postal, string $city, string $mobile, array $requestedAreas): string
    {
        $this->framework->initialize();

        $firstname = $this->cleanString($firstname, 100);
        $lastname = $this->cleanString($lastname, 100);

        $email = $this->cleanEmail($email);
        if (!$email) {
            return 'invalid_email';
        }

        $street = $this->cleanString($street, 255);
        $postal = $this->cleanString($postal, 16);
        $city = $this->cleanString($city, 255);
        $mobile = $this->cleanPhone($mobile);

        $requestedAreas = $this->cleanAreas($requestedAreas);

        $existing = Database::getInstance()
            ->prepare("SELECT id FROM tl_co_access_request WHERE email=? AND approved='' LIMIT 1")
            ->execute($email)
        ;

        if ($existing->numRows > 0) {
            return 'already_open';
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = time() + (2 * 24 * 60 * 60);

        Database::getInstance()
            ->prepare('
                INSERT INTO tl_co_access_request
                (tstamp, firstname, lastname, email, mobile, street, postal, city,
                 requestedAreas, token, emailConfirmed, approved, tokenExpiresAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')
            ->execute(
                time(),
                $firstname,
                $lastname,
                $email,
                $mobile,
                $street,
                $postal,
                $city,
                serialize($requestedAreas),
                $tokenHash,
                '',
                '',
                $expiresAt,
            )
        ;

        $this->sendDoiMail(
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            token: $token,
            requestedAreas: $requestedAreas,
            street: $street,
            postal: $postal,
            city: $city,
            mobile: $mobile,
        );

        return 'ok';
    }

    /**
     * @param list<string> $requestedAreas
     */
    public function createRequestAndSendDoiMail(string $firstname, string $lastname, string $email, string $street, string $postal, string $city, string $mobile, array $requestedAreas): void
    {
        $this->tryCreateRequestAndSendDoiMail(
            $firstname,
            $lastname,
            $email,
            $street,
            $postal,
            $city,
            $mobile,
            $requestedAreas,
        );
    }

    public function confirmToken(string $rawToken): bool
    {
        return null !== $this->confirmTokenAndGetRequestId($rawToken);
    }

    public function confirmTokenAndGetRequestId(string $rawToken): int|null
    {
        $this->framework->initialize();

        $tokenHash = hash('sha256', $rawToken);

        $row = Database::getInstance()
            ->prepare('SELECT id, emailConfirmed, tokenExpiresAt FROM tl_co_access_request WHERE token=? LIMIT 1')
            ->execute($tokenHash)
            ->fetchAssoc()
        ;

        if (!$row) {
            return null;
        }

        if (!empty($row['emailConfirmed'])) {
            return null;
        }

        if (!empty($row['tokenExpiresAt']) && (int) $row['tokenExpiresAt'] < time()) {
            return null;
        }

        Database::getInstance()
            ->prepare("
                UPDATE tl_co_access_request
                SET emailConfirmed='1',
                    tstamp=?
                WHERE id=?
            ")
            ->execute(time(), (int) $row['id'])
        ;

        return (int) $row['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestRow(int $id): array|null
    {
        $this->framework->initialize();

        $row = Database::getInstance()
            ->prepare('SELECT * FROM tl_co_access_request WHERE id=? LIMIT 1')
            ->execute($id)
            ->fetchAssoc()
        ;

        return $row ?: null;
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    public function getPendingRequestsForEmail(string $email): array
    {
        $this->framework->initialize();

        $email = $this->cleanEmail($email) ?? '';
        if ('' === $email) {
            return [];
        }

        $cooldown = self::ADDITIONAL_REQUEST_COOLDOWN;
        $result = [];

        $res = Database::getInstance()
            ->prepare("
                SELECT tstamp, requestedAreas, emailConfirmed, approved
                FROM tl_co_access_request
                WHERE email=?
                  AND approved=''
                ORDER BY id DESC
                LIMIT 50
            ")
            ->execute($email)
        ;

        while ($res->next()) {
            $areas = StringUtil::deserialize($this->resultField($res, 'requestedAreas'), true);
            $areas = array_map('strval', $areas);

            foreach ($areas as $area) {
                $areaClean = $this->cleanAreas([$area]);
                if ([] === $areaClean) {
                    continue;
                }
                $area = $areaClean[0];

                if (isset($result[$area])) {
                    continue;
                }

                if ('' === (string) $this->resultField($res, 'emailConfirmed')) {
                    $age = time() - (int) $this->resultField($res, 'tstamp');
                    $remaining = $cooldown - $age;
                    $remaining = $remaining > 0 ? $remaining : 0;

                    $result[$area] = [
                        'state' => 'pending_unconfirmed',
                        'retryAfterSeconds' => $remaining,
                    ];
                } else {
                    $result[$area] = [
                        'state' => 'pending_confirmed',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Sends or resends a DOI (Double Opt-In) email for a specific area.
     *
     * @return array{code:string, retryAfterSeconds?:int}
     */
    public function sendOrResendDoiForArea(string $firstname, string $lastname, string $email, string $area): array
    {
        $this->framework->initialize();

        $firstname = $this->cleanString($firstname, 100);
        $lastname = $this->cleanString($lastname, 100);

        $email = $this->cleanEmail($email);
        if (!$email) {
            return ['code' => 'invalid_email'];
        }

        $areaList = $this->cleanAreas([$area]);
        if ([] === $areaList) {
            return ['code' => 'invalid_email'];
        }
        $area = $areaList[0];

        $res = Database::getInstance()
            ->prepare("
                SELECT id, tstamp, requestedAreas, emailConfirmed, approved
                FROM tl_co_access_request
                WHERE email=?
                  AND approved=''
                ORDER BY id DESC
                LIMIT 50
            ")
            ->execute($email)
        ;

        $matchId = null;
        $matchTstamp = null;
        $matchConfirmed = null;

        while ($res->next()) {
            $areas = StringUtil::deserialize($this->resultField($res, 'requestedAreas'), true);
            $areas = array_map('strval', $areas);

            if (!\in_array($area, $areas, true)) {
                continue;
            }

            $matchId = (int) $this->resultField($res, 'id');
            $matchTstamp = (int) $this->resultField($res, 'tstamp');
            $matchConfirmed = (string) $this->resultField($res, 'emailConfirmed');
            break;
        }

        if (null !== $matchId) {
            if ('' !== $matchConfirmed) {
                return ['code' => 'pending_confirmed'];
            }

            $age = time() - (int) $matchTstamp;
            $remaining = self::ADDITIONAL_REQUEST_COOLDOWN - $age;

            if ($remaining > 0) {
                return ['code' => 'cooldown', 'retryAfterSeconds' => $remaining];
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = time() + (2 * 24 * 60 * 60);

            Database::getInstance()
                ->prepare("
                    UPDATE tl_co_access_request
                    SET tstamp=?, token=?, tokenExpiresAt=?, emailConfirmed=''
                    WHERE id=?
                ")
                ->execute(time(), $tokenHash, $expiresAt, $matchId)
            ;

            $this->sendDoiMail(
                firstname: $firstname,
                lastname: $lastname,
                email: $email,
                token: $token,
                requestedAreas: [$area],
                street: '',
                postal: '',
                city: '',
                mobile: '',
            );

            return ['code' => 'ok'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = time() + (2 * 24 * 60 * 60);

        Database::getInstance()
            ->prepare('
                INSERT INTO tl_co_access_request
                (tstamp, firstname, lastname, email, mobile, street, postal, city,
                 requestedAreas, token, emailConfirmed, approved, tokenExpiresAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')
            ->execute(
                time(),
                $firstname,
                $lastname,
                $email,
                '',
                '',
                '',
                '',
                serialize([$area]),
                $tokenHash,
                '',
                '',
                $expiresAt,
            )
        ;

        $this->sendDoiMail(
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            token: $token,
            requestedAreas: [$area],
            street: '',
            postal: '',
            city: '',
            mobile: '',
        );

        return ['code' => 'ok'];
    }

    /**
     * @param list<string> $areas
     */
    private function formatAreas(array $areas): string
    {
        if (!$areas) {
            return '-';
        }

        $map = [
            'workshop' => 'Werkstatt',
            'sharing' => 'Sharingstation',
            'depot' => 'Lebensmittel-Depot',
            'swap-house' => 'Tauschhaus',
        ];

        return implode(', ', array_map(static fn ($a) => $map[$a] ?? $a, $areas));
    }

    /**
     * @param list<string> $requestedAreas
     */
    private function sendDoiMail(string $firstname, string $lastname, string $email, string $token, array $requestedAreas, string $street, string $postal, string $city, string $mobile): void
    {
        $confirmUrl = $this->router->generate(
            'community_offers_access_confirm',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $text = <<<TXT
            Hallo {$firstname} {$lastname},

            bitte bestätigen Sie Ihre E-Mail-Adresse, indem Sie diesen Link öffnen:

            {$confirmUrl}

            Ihre Anfrage:
            - Adresse: {$street}, {$postal} {$city}
            - Mobil: {$mobile}
            - Sie möchten nutzen: {$this->formatAreas($requestedAreas)}

            Wenn Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.

            Viele Grüße
            Zukunftwohnen / Zukunftsforum Rissen
            TXT;

        $mail = (new Email())
            ->from('admin@zukunftwohnen.zukunftsforum-rissen.de')
            ->to($email)
            ->subject('Bitte bestätigen Sie Ihre E-Mail-Adresse')
            ->text($text)
        ;

        $this->mailer->send($mail);
    }

    private function cleanString(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return mb_substr($value, 0, $maxLength);
    }

    private function cleanEmail(string $email): string|null
    {
        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
    }

    private function cleanPhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9+\-\/\s]/', '', $phone) ?? '';

        return mb_substr($phone, 0, 64);
    }

    /**
     * @param array<mixed> $areas
     *
     * @return list<string>
     */
    private function cleanAreas(array $areas): array
    {
        $allowed = ['workshop', 'sharing', 'depot', 'swap-house'];

        return array_values(array_filter(
            array_map('strval', $areas),
            static fn (string $area): bool => \in_array($area, $allowed, true),
        ));
    }

    private function resultField(Result $result, string $key): mixed
    {
        $row = $result->row();

        return $row[$key] ?? null;
    }
}
