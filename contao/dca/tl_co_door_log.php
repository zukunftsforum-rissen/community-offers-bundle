<?php

declare(strict_types=1);

use Contao\DC_Table;
use ZukunftsforumRissen\CommunityOffersBundle\DataContainer\DoorLogCallback;

$GLOBALS['TL_DCA']['tl_co_door_log'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'correlationId' => 'index',
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
            'fields' => ['tstamp DESC'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['tstamp', 'correlationId', 'memberId', 'area', 'action', 'result'],
            'label_callback' => [DoorLogCallback::class, 'labelCallback'],
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
            'workflow' => [
                'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['workflow'],
                'href' => '',
                'icon' => 'bundles/communityoffers/icons/workflow-grey.svg',
                'button_callback' => [\ZukunftsforumRissen\CommunityOffersBundle\DataContainer\DoorLogCallback::class, 'workflowButton'],
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => [],
        'default' => '{log_legend},tstamp,correlationId,memberId,area,action,result,ip,userAgent,message,context',
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
        'correlationId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['correlationId'],
            'search' => true,
            'filter' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(36) NOT NULL default ''",
        ],
        'memberId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['memberId'],
            'filter' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
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
            'options' => ['door_open', 'door_dispatch', 'door_confirm', 'request_access'],
            'reference' => &$GLOBALS['TL_LANG']['tl_co_door_log']['actions'],
            'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'result' => [
            'label' => &$GLOBALS['TL_LANG']['tl_co_door_log']['result'],
            'filter' => true,
            'inputType' => 'select',
            'options' => [
                'attempt',
                'granted',
                'forbidden',
                'unknown_area',
                'unauthenticated',
                'rate_limited',
                'dispatched',
                'confirmed',
                'failed',
                'timeout',
                'error',
            ],
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
