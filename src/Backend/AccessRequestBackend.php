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
use ZukunftsforumRissen\CommunityOffersBundle\Service\InternalNotificationMailer;

class AccessRequestBackend extends Backend
{
    public function __construct(
        private readonly ApprovalMailer $approvalMailer,
        private readonly AccessService $accessService,
        private readonly InternalNotificationMailer $internalNotificationMailer,
    ) {
    }

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

        $memberId = (int) ($row['memberId'] ?? 0);

        if ($memberId <= 0) {
            $this->addError('Kein Member verknüpft. Bitte DOI-Provisionierung prüfen.');
            $this->redirectToRequestList();

            return;
        }

        $member = $this->findMemberByPk($memberId);

        if (null === $member) {
            $this->addError('Verknüpfter Member nicht gefunden.');
            $this->redirectToRequestList();

            return;
        }

        $areas = array_values(array_map('strval', StringUtil::deserialize($row['requestedAreas'], true)));
        $groupIds = $this->mapAreasToGroups($areas);

        $existingGroups = StringUtil::deserialize($member->groups, true);
        $mergedGroups = array_values(array_unique(array_merge($existingGroups, $groupIds)));

        $member->groups = serialize($mergedGroups);
        $member->disable = false;
        $member->tstamp = time();

        $this->saveMember($member);
        $this->markRequestApproved($id);

        $this->addConfirmation(
            'Der Antrag wurde erfolgreich freigegeben. '
            .'Bereits eingeloggte Nutzer müssen sich neu anmelden, '
            .'damit die neuen Bereiche in der App sichtbar werden.',
        );

        $areasHuman = $this->formatAreasHuman($areas);

        $this->approvalMailer->sendApprovalMail(
            (string) $row['email'],
            (string) $row['firstname'],
            (string) $row['lastname'],
            $areasHuman,
            (string) $member->username,
        );

        $this->internalNotificationMailer->sendApprovedNotification(
            (string) $row['firstname'],
            (string) $row['lastname'],
            (string) $row['street'],
            (string) $row['postal'],
            (string) $row['city'],
            (string) $row['mobile'],
            (string) $row['email'],
            $areasHuman,
        );

        $this->redirectToRequestList();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function generateApproveButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        if (
            !$row['emailConfirmed']
            || $row['approved']
            || (int) ($row['memberId'] ?? 0) <= 0
        ) {
            return '';
        }

        return \sprintf(
            '<a href="contao?do=co_access_request&key=approve&id=%s" title="%s" style="color:green;font-weight:bold">%s Freigeben</a>',
            $row['id'],
            StringUtil::specialchars($title),
            Image::getHtml($icon),
        );
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

    protected function findMemberByPk(int $memberId): MemberModel|null
    {
        return MemberModel::findById($memberId);
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

    protected function addError(string $message): void
    {
        Message::addError($message);
    }

    protected function redirectToRequestList(): void
    {
        $this->redirect('contao?do=co_access_request');
    }

    /**
     * @param list<string> $areas
     *
     * @return list<int>
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
}
