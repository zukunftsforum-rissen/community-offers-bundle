<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\DataContainer;

use Contao\Config;
use Contao\Database;
use Contao\Date;
use Contao\StringUtil;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class DoorLogCallback
{
    public function __construct(
        private readonly ParameterBagInterface $params,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function labelCallback(array $row): string
    {
        $time = '';
        if (!empty($row['tstamp'])) {
            $time = Date::parse(Config::get('datimFormat'), (int) $row['tstamp']);
        }

        static $memberCache = [];

        $memberLabel = 'Gast/Unbekannt';
        $memberId = (int) ($row['memberId'] ?? 0);

        if ($memberId > 0) {
            if (!\array_key_exists($memberId, $memberCache)) {
                $member = Database::getInstance()
                    ->prepare('SELECT firstname, lastname, email FROM tl_member WHERE id=?')
                    ->execute($memberId)
                ;

                if ($member->numRows > 0) {
                    /** @var array<string, mixed> $memberRow */
                    $memberRow = $member->row();

                    $memberCache[$memberId] = [
                        'firstname' => (string) ($memberRow['firstname'] ?? ''),
                        'lastname' => (string) ($memberRow['lastname'] ?? ''),
                        'email' => (string) ($memberRow['email'] ?? ''),
                    ];
                } else {
                    $memberCache[$memberId] = null;
                }
            }

            $memberData = $memberCache[$memberId];
            if (\is_array($memberData)) {
                $name = trim(($memberData['firstname'] ?? '').' '.($memberData['lastname'] ?? ''));
                $email = (string) ($memberData['email'] ?? '');

                $memberLabel = '' !== $name ? $name : '#'.$memberId;
                if ('' !== $email) {
                    $memberLabel .= ' <'.$email.'>';
                }
            } else {
                $memberLabel = '#'.$memberId;
            }
        }

        $correlationId = (string) ($row['correlationId'] ?? '');
        if ('' !== $correlationId) {
            $correlationId = substr($correlationId, 0, 8);
        }

        $areaKey = (string) ($row['area'] ?? '');
        $actionKey = (string) ($row['action'] ?? '');
        $resultKey = (string) ($row['result'] ?? '');

        $area = $GLOBALS['TL_LANG']['tl_co_door_log']['areas'][$areaKey] ?? $areaKey;
        $action = $GLOBALS['TL_LANG']['tl_co_door_log']['actions'][$actionKey] ?? $actionKey;
        $result = $GLOBALS['TL_LANG']['tl_co_door_log']['results'][$resultKey] ?? $resultKey;

        if ('' === $time && '' === $area && '' === $action && '' === $result && '' === $correlationId) {
            return 'Logeintrag';
        }

        return trim(\sprintf(
            '%s | %s | %s | %s | %s%s',
            $time,
            $memberLabel,
            $area,
            $action,
            $result,
            '' !== $correlationId ? ' | CID '.$correlationId : '',
        ));
    }

    /**
     * @return array<int, string>
     */
    public function getMemberOptions(): array
    {
        $options = [];

        $result = Database::getInstance()
            ->execute('
                SELECT DISTINCT l.memberId AS id, m.firstname, m.lastname, m.email
                FROM tl_co_door_log l
                LEFT JOIN tl_member m ON m.id = l.memberId
                WHERE l.memberId > 0
                ORDER BY m.lastname, m.firstname
            ')
        ;

        while ($result->next()) {
            /** @var array<string, mixed> $row */
            $row = $result->row();

            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['firstname'] ?? '').' '.(string) ($row['lastname'] ?? ''));
            $email = (string) ($row['email'] ?? '');

            $label = '' !== $name ? $name : '#'.$id;
            if ('' !== $email) {
                $label .= ' <'.$email.'>';
            }

            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function workflowButton(array $row, string|null $href = null, string|null $label = null, string|null $title = null, string|null $icon = null, string|null $attributes = null): string
    {
        $cid = (string) ($row['correlationId'] ?? '');

        if ('' === $cid) {
            return '';
        }

        $backendPrefix = rtrim((string) $this->params->get('contao.backend.route_prefix'), '/');
        $url = $backendPrefix.'/door-workflow?cid='.rawurlencode($cid);

        $icon = $icon ?: 'show.svg';

        return \sprintf(
            '<a href="%s" title="%s"%s><img src="system/themes/flexible/icons/%s" width="16" height="16" alt=""></a>',
            StringUtil::specialchars($url),
            StringUtil::specialchars($title ?? 'Workflow anzeigen'),
            $attributes ?? '',
            StringUtil::specialchars($icon),
        );
    }
}
