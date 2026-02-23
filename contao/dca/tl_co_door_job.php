<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_co_door_job'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'status,expiresAt' => 'index',
                'area' => 'index',
                'dispatchToDeviceId' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['createdAt'],
            'flag' => 6, // newest first
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['createdAt', 'area', 'status', 'requestedByMemberId', 'dispatchToDeviceId'],
            'format' => '%s | %s | %s | member %s | device %s',
        ],
        'global_operations' => [
            'all' => ['href' => 'act=select', 'class' => 'header_edit_all', 'label' => &$GLOBALS['TL_LANG']['MSC']['all']],
        ],
        'operations' => [
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
        ],
    ],

    'palettes' => [
        'default' => '{job_legend},createdAt,expiresAt,area,status,requestedByMemberId;{dispatch_legend},dispatchToDeviceId,dispatchedAt,executedAt,attempts;{result_legend},resultCode,resultMessage;{meta_legend},requestIp,userAgent,nonce',
    ],

    'fields' => [
        'id' => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],

        'createdAt' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['createdAt'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'expiresAt' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['expiresAt'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'area' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['area'],
            'inputType' => 'select',
            'options' => ['depot', 'swap-house', 'workshop', 'sharing'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_job']['areas'],
            'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'requestedByMemberId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['requestedByMemberId'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'requestIp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['requestIp'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'userAgent' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['userAgent'],
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr', 'rows' => 2],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'status' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['status'],
            'inputType' => 'select',
            'options' => ['pending', 'dispatched', 'executed', 'failed', 'expired'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_job']['statuses'],
            'eval' => ['tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(20) NOT NULL default 'pending'",
        ],

        'dispatchToDeviceId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['dispatchToDeviceId'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'dispatchedAt' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['dispatchedAt'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'executedAt' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['executedAt'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'nonce' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['nonce'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'attempts' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['attempts'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'resultCode' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['resultCode'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(40) NOT NULL default ''",
        ],

        'resultMessage' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_job']['resultMessage'],
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr', 'rows' => 2],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];