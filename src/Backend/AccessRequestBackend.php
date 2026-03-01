<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Backend;

use Contao\Backend;
use Contao\Database;
use Contao\Image;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\ApprovalMailer;

class AccessRequestBackend extends Backend
{
    public function __construct(
        private readonly ApprovalMailer $approvalMailer,
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * Handle backend actions. Diese Methode prüft, ob eine Aktion (hier: "approve")
     * ausgeführt werden soll. Wenn ja, führt sie die entsprechenden Schritte aus
     * (Member anlegen/aktualisieren, Antrag freigeben).
     */
    public function handleActions(): void
    {
        if ('approve' !== $this->getInputValue('key')) {
            return;
        }

        $id = (int) $this->getInputValue('id');

        $row = $this->fetchAccessRequestRow($id);

        if (!$row || !$row['emailConfirmed'] || $row['approved']) {
            $this->redirectToRequestList();

            return;
        }

        // 🔹 Member finden oder neu anlegen (E-Mail als Kriterium)
        $email = mb_strtolower(trim((string) $row['email']));

        $member = $this->findMemberByEmail($email);

        $isNew = false;
        if (null === $member) {
            $member = $this->createMember();
            $isNew = true;

            // Minimaler Satz Pflichtwerte für neuen Member
            $member->tstamp = time();
            $member->email = $email;

            $member->username = $email; // $email ist bereits lowercased/trimmed

            // Login aktivieren (Contao-Standard)
            $member->login = true;

            // Passwort-Reset erzwingen: zufälliges Passwort setzen
            $member->password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        }

        // immer aktualisieren (auch bei existing)
        $member->tstamp = time();
        $member->firstname = (string) $row['firstname'];
        $member->lastname = (string) $row['lastname'];
        $member->street = (string) $row['street'];
        $member->postal = (string) $row['postal'];
        $member->city = (string) $row['city'];
        $member->mobile = (string) $row['mobile'];

        // Gruppen zuweisen (merge statt überschreiben? – siehe unten)
        $areas = array_values(array_map('strval', StringUtil::deserialize($row['requestedAreas'], true)));
        $groupIds = $this->mapAreasToGroups($areas);

        // Bestehende Gruppen optional behalten und nur ergänzen:
        $existingGroups = StringUtil::deserialize($member->groups, true);
        $mergedGroups = array_values(array_unique(array_merge($existingGroups, $groupIds)));

        $member->groups = serialize($mergedGroups);

        // disable nicht blind überschreiben (nur wenn neu)
        if ($isNew) {
            $member->disable = false;
        }

        $this->saveMember($member);

        // 🔹 Antrag als approved markieren
        $this->markRequestApproved($id);

        $this->addConfirmation('Der Antrag wurde erfolgreich freigegeben.');

        $areas = array_values(array_map('strval', StringUtil::deserialize($row['requestedAreas'], true)));
        $areasHuman = $this->formatAreasHuman($areas);

        $this->approvalMailer->sendApprovalMail(
            $email,
            (string) $row['firstname'],
            (string) $row['lastname'],
            $areasHuman,
        );

        $this->redirectToRequestList();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function generateApproveButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        if (!$row['emailConfirmed'] || $row['approved']) {
            return ''; // nicht anzeigen
        }

        return \sprintf(
            '<a href="contao?do=co_access_request&key=approve&id=%s" title="%s" style="color:green;font-weight:bold">%s Freigeben</a>',
            $row['id'],
            StringUtil::specialchars($title),
            Image::getHtml($icon),
        );
    }

    /**
     * @param list<string> $areas
     *
     * @return list<int> Group IDs
     */
    private function mapAreasToGroups(array $areas): array
    {
        return $this->accessService->getGroupIdsForAreas($areas);
    }

    /**
     * @param list<string> $areas
     *
     * @return list<string>
     */
    private function formatAreasHuman(array $areas): array
    {
        $map = [
            'workshop' => 'Werkstatt',
            'sharing' => 'Sharingstation',
            'depot' => 'Lebensmittel-Depot',
            'swap-house' => 'Tauschhaus',
        ];

        $out = [];

        foreach ($areas as $a) {
            $out[] = $map[$a] ?? $a;
        }

        return $out;
    }

    protected function getInputValue(string $key): string|null
    {
        return Input::get($key);
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function fetchAccessRequestRow(int $id): array|false
    {
        return Database::getInstance()
            ->prepare('SELECT * FROM tl_co_access_request WHERE id=?')
            ->execute($id)
            ->fetchAssoc()
        ;
    }

    protected function findMemberByEmail(string $email): object|null
    {
        return MemberModel::findOneBy('email', $email);
    }

    protected function createMember(): object
    {
        return new MemberModel();
    }

    protected function saveMember(object $member): void
    {
        if (method_exists($member, 'save')) {
            $member->save();
        }
    }

    protected function markRequestApproved(int $id): void
    {
        Database::getInstance()
            ->prepare("UPDATE tl_co_access_request SET approved='1', tstamp=? WHERE id=?")
            ->execute(time(), $id)
        ;
    }

    protected function addConfirmation(string $message): void
    {
        Message::addConfirmation($message);
    }

    protected function redirectToRequestList(): void
    {
        $this->redirect('contao?do=co_access_request');
    }
}
