<?php

use Contao\DC_Table;
use ZukunftsforumRissen\CommunityOffersBundle\src\DataContainer\TlCoDevice;

$GLOBALS['TL_DCA']['tl_co_device'] = [

    /*
     * Config
     */
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'deviceId' => 'unique',
                'enabled' => 'index',
            ],
        ],
    ],

    /*
     * List
     */
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['name'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['name', 'deviceId', 'areas', 'enabled'],
            'showColumns' => true,
            'label_callback' => [TlCoDevice::class, 'formatLabel'],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'toggle' => [
                'href' => 'act=toggle&amp;field=enabled',
                'icon' => 'visible.svg',
            ],
            'token' => [
                'href' => 'key=token',
                'icon' => 'key.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_co_device']['token'],
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    /*
     * Palettes
     */
    'palettes' => [
        'default' => '
            name,deviceId;
            {config_legend},enabled,areas;
            {state_legend},lastSeen,ipLast
        ',
    ],

    /*
     * Fields
     */
    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'name' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['name'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],

        'deviceId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['deviceId'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'areas' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['areas'],
            'inputType' => 'checkbox',
            'options' => ['depot', 'swap-house', 'workshop', 'sharing'],
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => "blob NULL",
        ],

        'enabled' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['enabled'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default ''",
        ],

        'apiTokenHash' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['apiTokenHash'],
            'eval' => ['doNotShow' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'lastSeen' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['lastSeen'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'ipLast' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_device']['ipLast'],
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

    ],
];
