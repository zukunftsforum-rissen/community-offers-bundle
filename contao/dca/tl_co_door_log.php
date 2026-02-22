<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_co_door_log'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'memberId' => 'index',
                'area' => 'index',
                'tstamp' => 'index',
                'action' => 'index',
                'result' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['tstamp DESC', 'id DESC'],
            'flag' => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['tstamp', 'memberId', 'area', 'action', 'result'],
            'label_callback' => ['tl_co_door_log', 'labelCallback'],
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? 'Delete?') . '\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => [],
        'default' => '{log_legend},tstamp,memberId,area,action,result,ip,userAgent,message,context',
    ],

    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['tstamp'],
            'sorting' => true,
            'flag' => 6,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'memberId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['memberId'],
            'filter' => true,
            'search' => true,
            'inputType' => 'select',
            'options_callback' => ['tl_co_door_log', 'getMemberOptions'],
            'eval' => [
                'chosen' => true,
                'includeBlankOption' => true,
                'tl_class' => 'w50',
            ],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'area' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['area'],
            'filter' => true,
            'inputType' => 'select',
            'options' => ['workshop', 'sharing', 'depot', 'swap-house'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_log']['areas'],
            'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'action' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['action'],
            'filter' => true,
            'inputType' => 'select',
            'options' => ['door_open', 'request_access'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_log']['actions'],
            'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'result' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['result'],
            'filter' => true,
            'inputType' => 'select',
            'options' => ['attempt', 'granted', 'forbidden', 'unknown_area', 'unauthenticated', 'rate_limited', 'error'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_log']['results'],
            'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'ip' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['ip'],
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'userAgent' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['userAgent'],
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'message' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['message'],
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'context' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['context'],
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'rte' => 'ace|json', 'tl_class' => 'clr'],
            'sql' => "mediumtext NULL",
        ],
    ],
];

class tl_co_door_log
{
    public function labelCallback(array $row): string
    {
        // --- Zeit ---
        $time = '';
        if (!empty($row['tstamp'])) {
            $time = \Contao\Date::parse(\Contao\Config::get('datimFormat'), (int) $row['tstamp']);
        }

        // --- Mitglied (mit kleinem Cache, damit nicht pro Zeile neu geladen wird) ---
        static $memberCache = [];

        $memberLabel = 'Gast/Unbekannt';
        $memberId = (int) ($row['memberId'] ?? 0);

        if ($memberId > 0) {
            if (!array_key_exists($memberId, $memberCache)) {
                $m = \Contao\Database::getInstance()
                    ->prepare("SELECT firstname, lastname, email FROM tl_member WHERE id=?")
                    ->execute($memberId);

                $memberCache[$memberId] = $m->numRows ? [
                    'firstname' => (string) $m->firstname,
                    'lastname'  => (string) $m->lastname,
                    'email'     => (string) $m->email,
                ] : null;
            }

            $md = $memberCache[$memberId];
            if (is_array($md)) {
                $name = trim(($md['firstname'] ?? '') . ' ' . ($md['lastname'] ?? ''));
                $email = (string) ($md['email'] ?? '');

                $memberLabel = $name !== '' ? $name : ('#' . $memberId);
                if ($email !== '') {
                    $memberLabel .= ' <' . $email . '>';
                }
            } else {
                $memberLabel = '#' . $memberId;
            }
        }

        // --- Übersetzungen (area/action/result) ---
        $areaKey   = (string) ($row['area'] ?? '');
        $actionKey = (string) ($row['action'] ?? '');
        $resultKey = (string) ($row['result'] ?? '');

        $area   = $GLOBALS['TL_LANG']['tl_co_door_log']['areas'][$areaKey] ?? $areaKey;
        $action = $GLOBALS['TL_LANG']['tl_co_door_log']['actions'][$actionKey] ?? $actionKey;
        $result = $GLOBALS['TL_LANG']['tl_co_door_log']['results'][$resultKey] ?? $resultKey;

        if ($time === '' && $area === '' && $action === '' && $result === '') {
            return 'Logeintrag';
        }

        return trim(sprintf(
            '%s | %s | %s | %s | %s',
            $time,
            $memberLabel,
            $area,
            $action,
            $result
        ));
    }

    public function getMemberOptions(): array
    {
        $options = [];

        $res = \Contao\Database::getInstance()
            ->execute("
            SELECT DISTINCT l.memberId AS id, m.firstname, m.lastname, m.email
            FROM tl_co_door_log l
            LEFT JOIN tl_member m ON m.id = l.memberId
            WHERE l.memberId > 0
            ORDER BY m.lastname, m.firstname
        ");

        while ($res->next()) {
            $id = (int) $res->id;

            $name = trim(($res->firstname ?? '') . ' ' . ($res->lastname ?? ''));
            $email = (string) ($res->email ?? '');

            $label = $name !== '' ? $name : ('#' . $id);
            if ($email !== '') {
                $label .= ' <' . $email . '>';
            }

            $options[$id] = $label;
        }

        return $options;
    }
}
