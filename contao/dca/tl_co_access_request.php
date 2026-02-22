<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_co_access_request'] = [

    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'email' => 'index',
                'token' => 'index',
                'emailConfirmed' => 'index',
                'approved' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['tstamp DESC'],
            'flag' => 1
        ],
        'label' => [
            'fields' => ['firstname', 'lastname', 'email', 'emailConfirmed', 'approved'],
            'format' => '%s %s – %s | DOI: %s | Freigabe: %s'
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Wirklich löschen?\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
            'approve' => [
                'label' => ['Freigeben', 'Antrag freigeben und Member anlegen'],
                'href'  => 'key=approve',
                'icon'  => 'visible.svg',
                'button_callback' => [
                    'ZukunftsforumRissen\CommunityOffersBundle\Backend\AccessRequestBackend',
                    'generateApproveButton'
                ]
            ],
        ],
    ],

    'palettes' => [
        'default' => '{general_legend},
        firstname,lastname,email,mobile,
        street,postal,city,
        requestedAreas,
        emailConfirmed,approved'
    ],

    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        'firstname' => [
            'label' => ['Vorname'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'lastname' => [
            'label' => ['Nachname'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'email' => [
            'label' => ['E-Mail'],
            'inputType' => 'text',
            'eval' => ['rgxp' => 'email', 'mandatory' => true],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'mobile' => [
            'label' => ['Mobil'],
            'inputType' => 'text',
            'eval' => ['rgxp' => 'phone'],
            'sql' => "varchar(64) NOT NULL default ''"
        ],

        'street' => [
            'label' => ['Straße und Hausnummer'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'postal' => [
            'label' => ['PLZ'],
            'inputType' => 'text',
            'eval' => ['rgxp' => 'digit', 'maxlength' => 10, 'mandatory' => true],
            'sql' => "varchar(16) NOT NULL default ''"
        ],

        'city' => [
            'label' => ['Ort'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'requestedAreas' => [
            'label' => ['Angebote'],
            'inputType' => 'checkbox',
            'options' => ['workshop', 'sharing', 'depot', 'swap-house'],
            'eval' => ['multiple' => true],
            'sql' => "blob NULL"
        ],

        'token' => [
            'sql' => "varchar(64) NOT NULL default ''"
        ],

        'tokenExpiresAt' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        'emailConfirmed' => [
            'label' => ['E-Mail bestätigt'],
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''"
        ],

        'approved' => [
            'label' => ['Freigegeben'],
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''"
        ],
    ]
];
