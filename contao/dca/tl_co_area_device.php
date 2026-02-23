<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_co_area_device'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'area' => 'unique',
                'deviceId' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['area'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['area', 'deviceId', 'enabled'],
            'format' => '%s → %s %s',
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
            ],
        ],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'toggle' => [
                'href' => 'act=toggle&amp;field=enabled',
                'icon' => 'visible.svg',
            ],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],

    'palettes' => [
        'default' => '{mapping_legend},area,deviceId,enabled',
    ],

    'fields' => [
        'id' => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],

        'area' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_area_device']['area'],
            'inputType' => 'select',
            'options' => ['depot', 'swap-house', 'workshop', 'sharing'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_area_device']['areas'],
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'deviceId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_area_device']['deviceId'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],

        'enabled' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_area_device']['enabled'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
    ],
];