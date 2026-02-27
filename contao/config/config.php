<?php
// contao/config/config.php

declare(strict_types=1);

$GLOBALS['BE_MOD']['community_offers'] ??= [];

$GLOBALS['BE_MOD']['community_offers']['co_access_request'] ??= [
    'tables' => ['tl_co_access_request'],
];

$GLOBALS['BE_MOD']['community_offers']['co_door_log'] = [
    'tables' => ['tl_co_door_log'],
];

$GLOBALS['BE_MOD']['community_offers']['devices'] = [
    'tables' => ['tl_co_device'],
];

$GLOBALS['BE_MOD']['community_offers']['door_jobs'] = [
    'tables' => ['tl_co_door_job'],
];
