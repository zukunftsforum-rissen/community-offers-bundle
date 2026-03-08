<?php

$GLOBALS['TL_LANG']['tl_co_door_log']['log_legend'] = 'Protokoll';

$GLOBALS['TL_LANG']['tl_co_door_log']['tstamp']    = ['Zeit', 'Zeitpunkt des Vorgangs'];
$GLOBALS['TL_LANG']['tl_co_door_log']['memberId']  = ['Mitglied', 'ID des Mitglieds'];
$GLOBALS['TL_LANG']['tl_co_door_log']['area']      = ['Bereich', 'Tür/Bereich'];
$GLOBALS['TL_LANG']['tl_co_door_log']['action']    = ['Aktion', 'z. B. door_open'];
$GLOBALS['TL_LANG']['tl_co_door_log']['result']    = ['Ergebnis', 'z. B. granted/forbidden/rate_limited/error'];
$GLOBALS['TL_LANG']['tl_co_door_log']['ip']        = ['IP', 'Client-IP'];
$GLOBALS['TL_LANG']['tl_co_door_log']['userAgent'] = ['User-Agent', 'Browser/Client'];
$GLOBALS['TL_LANG']['tl_co_door_log']['message']   = ['Message', 'Kurzinfo'];
$GLOBALS['TL_LANG']['tl_co_door_log']['context']   = ['Context', 'Zusätzliche Daten (JSON)'];
$GLOBALS['TL_LANG']['tl_co_door_log']['workflow']  = ['Workflow', 'Workflow zu dieser Correlation-ID anzeigen'];
$GLOBALS['TL_LANG']['tl_co_door_log']['correlationId'] = ['Correlation-ID', 'Eindeutige ID des kompletten Workflows'];
// ===== Optionen =====

$GLOBALS['TL_LANG']['tl_co_door_log']['areas'] = [
    'workshop'   => 'Werkstatt',
    'sharing'    => 'Sharing',
    'depot'      => 'Depot',
    'swap-house' => 'Tauschhaus',
];

$GLOBALS['TL_LANG']['tl_co_door_log']['actions'] = [
    'door_confirm'   => 'Türstatus melden',
    'door_dispatch'  => 'Job abholen',
    'door_open'      => 'Türöffnung anfragen',
    'request_access' => 'Zugang beantragen',
];

$GLOBALS['TL_LANG']['tl_co_door_log']['results'] = [
    'attempt'          => 'Öffnung angefragt',
    'granted'          => 'Auftrag erstellt',
    'forbidden'        => 'Kein Zugriff',
    'unknown_area'     => 'Unbekannter Bereich',
    'unauthenticated'  => 'Nicht angemeldet',
    'rate_limited'     => 'Zu viele Anfragen',
    'error'            => 'Fehler',
    'dispatched'       => 'Vom Gerät abgeholt',
    'confirmed'        => 'Entriegelung bestätigt',
    'failed'           => 'Fehlgeschlagen',
    'timeout'          => 'Zeitüberschreitung',
];