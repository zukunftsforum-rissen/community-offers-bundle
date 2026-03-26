<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\Database;
use Contao\MemberModel;

final class MemberProvisioningService implements MemberProvisioningServiceInterface
{
    public function __construct(
        private readonly string $loginIdentifier,
    ) {
    }

    public function createMemberFromConfirmedRequest(int $requestId): MemberProvisioningResult
    {
        $db = Database::getInstance();

        /** @var array<string, mixed>|false $row */
        $row = $db
            ->prepare('SELECT * FROM tl_co_access_request WHERE id=?')
            ->limit(1)
            ->execute($requestId)
            ->fetchAssoc()
        ;

        if (!$row) {
            throw new \RuntimeException(\sprintf('Access request %d not found.', $requestId));
        }

        // Bereits verknüpft?
        if ((int) ($row['memberId'] ?? 0) > 0) {
            $member = MemberModel::findById(
                (int) $row['memberId'],
            );

            if (null === $member) {
                throw new \RuntimeException(\sprintf('Member %d not found.', (int) $row['memberId']));
            }

            return new MemberProvisioningResult($member, false);
        }

        $email = (string) ($row['email'] ?? '');

        if ('' === $email) {
            throw new \RuntimeException(\sprintf('Access request %d has no email.', $requestId));
        }

        // EXISTIERENDEN MEMBER SUCHEN
        $existing = MemberModel::findOneBy('email', $email);

        if (null !== $existing) {
            // Request verknüpfen
            $db
                ->prepare('UPDATE tl_co_access_request SET memberId=?, tstamp=? WHERE id=?')
                ->execute(
                    (int) $existing->id,
                    time(),
                    $requestId,
                )
            ;

            return new MemberProvisioningResult($existing, false);
        }

        // ===== NEUEN MEMBER ANLEGEN =====

        $firstname = (string) ($row['firstname'] ?? '');
        $lastname = (string) ($row['lastname'] ?? '');

        $username = $this->determineUsername(
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            requestId: $requestId,
        );

        $member = new MemberModel();

        $member->tstamp = time();

        $member->firstname = $firstname;
        $member->lastname = $lastname;

        $member->email = $email;
        $member->username = $username;

        $member->street = (string) ($row['street'] ?? '');
        $member->postal = (string) ($row['postal'] ?? '');
        $member->city = (string) ($row['city'] ?? '');

        $member->mobile = (string) ($row['mobile'] ?? '');

        $member->login = true;
        $member->disable = true;

        $member->dateAdded = time();

        $member->save();

        // Request verknüpfen
        $db
            ->prepare('UPDATE tl_co_access_request SET memberId=?, tstamp=? WHERE id=?')
            ->execute(
                (int) $member->id,
                time(),
                $requestId,
            )
        ;

        return new MemberProvisioningResult($member, true);
    }

    private function determineUsername(string $firstname, string $lastname, string $email, int $requestId): string
    {
        if ('email' === $this->loginIdentifier) {
            return $email;
        }

        return $this->generateStableUsername(
            $firstname,
            $lastname,
            $email,
            $requestId,
        );
    }

    private function generateStableUsername(string $firstname, string $lastname, string $email, int $requestId): string
    {
        $base = strtolower(
            preg_replace(
                '/[^a-z0-9]+/',
                '-',
                trim($firstname.'-'.$lastname),
            ) ?? '',
        );

        $base = trim($base, '-');

        if ('' === $base) {
            $local = strstr($email, '@', true);

            $base = strtolower(
                preg_replace(
                    '/[^a-z0-9]+/',
                    '-',
                    (string) $local,
                ) ?? '',
            );

            $base = trim($base, '-');
        }

        if ('' === $base) {
            $base = 'member-'.$requestId;
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->usernameExists($candidate)) {
            $candidate = $base.'-'.$suffix;

            ++$suffix;
        }

        return mb_substr($candidate, 0, 64);
    }

    private function usernameExists(string $username): bool
    {
        return null !== MemberModel::findOneBy(
            'username',
            $username,
        );
    }
}
