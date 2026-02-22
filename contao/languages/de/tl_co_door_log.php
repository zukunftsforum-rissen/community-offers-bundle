<?php

$GLOBALS['TL_LANG']['tl_co_door_log']['log_legend'] = 'Protokoll';

$GLOBALS['TL_LANG']['tl_co_door_log']['tstamp'] = ['Zeit', 'Zeitpunkt des Vorgangs'];
$GLOBALS['TL_LANG']['tl_co_door_log']['memberId'] = ['Mitglied', 'ID des Mitglieds'];
$GLOBALS['TL_LANG']['tl_co_door_log']['area'] = ['Bereich', 'Tür/Bereich'];
$GLOBALS['TL_LANG']['tl_co_door_log']['action'] = ['Aktion', 'z. B. door_open'];
$GLOBALS['TL_LANG']['tl_co_door_log']['result'] = ['Ergebnis', 'z. B. granted/forbidden/rate_limited/error'];
$GLOBALS['TL_LANG']['tl_co_door_log']['ip'] = ['IP', 'Client-IP'];
$GLOBALS['TL_LANG']['tl_co_door_log']['userAgent'] = ['User-Agent', 'Browser/Client'];
$GLOBALS['TL_LANG']['tl_co_door_log']['message'] = ['Message', 'Kurzinfo'];
$GLOBALS['TL_LANG']['tl_co_door_log']['context'] = ['Context', 'Zusätzliche Daten (JSON)'];

// ===== Optionen =====

$GLOBALS['TL_LANG']['tl_co_door_log']['areas'] = [
    'workshop'   => 'Werkstatt',
    'sharing'    => 'Sharing',
    'depot'      => 'Depot',
    'swap-house' => 'Tauschhaus',
];

$GLOBALS['TL_LANG']['tl_co_door_log']['actions'] = [
    'door_open'      => 'Tür öffnen',
    'request_access' => 'Zugang beantragen',
];

$GLOBALS['TL_LANG']['tl_co_door_log']['results'] = [
    'attempt'          => 'Versuch',
    'granted'          => 'Gewährt',
    'forbidden'        => 'Kein Zugriff',
    'unknown_area'     => 'Unbekannter Bereich',
    'unauthenticated'  => 'Nicht angemeldet',
    'rate_limited'     => 'Rate-Limit',
    'error'            => 'Fehler',
];