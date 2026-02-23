<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_co_device'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => ['keys' => ['id' => 'primary', 'deviceId' => 'unique']],
    ],

    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['name'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['name', 'deviceId', 'enabled', 'lastSeen', 'ipLast'],
            'format' => '%s (%s) | %s | lastSeen: %s | %s',
        ],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'toggle' => [
                'href' => 'act=toggle&amp;field=enabled',
                'icon' => 'visible.svg',
            ],
            'token' => [
                'href' => 'key=token',
                'icon' => 'key.svg',
                'label' => ['Token generieren', 'Neuen API-Token für dieses Gerät erzeugen'],
            ],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],

    'palettes' => [
        'default' => '{device_legend},name,deviceId,enabled;{meta_legend},lastSeen,ipLast,notes',
    ],

    'fields' => [
        'id' => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],

        'name' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'deviceId' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'unique' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'apiTokenHash' => [
            'sql' => "varchar(255) NOT NULL default ''",
            'eval' => ['doNotShow' => true],
        ],
        'enabled' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'lastSeen' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'ipLast' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'notes' => [
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr', 'rte' => 'tinyMCE'],
            'sql' => "text NULL",
        ],
    ],
];