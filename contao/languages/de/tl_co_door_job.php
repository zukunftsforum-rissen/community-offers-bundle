<?php

$GLOBALS['TL_LANG']['tl_co_door_job']['job_legend'] = 'Tür-Job';
$GLOBALS['TL_LANG']['tl_co_door_job']['dispatch_legend'] = 'Dispatch';
$GLOBALS['TL_LANG']['tl_co_door_job']['result_legend'] = 'Ergebnis';
$GLOBALS['TL_LANG']['tl_co_door_job']['meta_legend'] = 'Meta';

$GLOBALS['TL_LANG']['tl_co_door_job']['createdAt'] = ['Erstellt', 'Zeitpunkt der Erstellung des Jobs (Unix Timestamp).'];
$GLOBALS['TL_LANG']['tl_co_door_job']['expiresAt'] = ['Gültig bis', 'Nach diesem Zeitpunkt wird der Job als abgelaufen behandelt.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['area'] = ['Bereich', 'Welche Tür/Area soll geöffnet werden?'];
$GLOBALS['TL_LANG']['tl_co_door_job']['status'] = ['Status', 'Aktueller Status des Jobs.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['requestedByMemberId'] = ['Mitglied', 'Member-ID, die den Öffnungsvorgang ausgelöst hat.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['dispatchToDeviceId'] = ['Gerät', 'Gerät, an das der Job ausgeliefert wurde.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['dispatchedAt'] = ['Ausgeliefert', 'Zeitpunkt, zu dem der Job an das Gerät ausgeliefert wurde.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['executedAt'] = ['Ausgeführt', 'Zeitpunkt, zu dem das Gerät den Job bestätigt hat.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['attempts'] = ['Versuche', 'Wie oft wurde versucht, den Job auszuführen?'];
$GLOBALS['TL_LANG']['tl_co_door_job']['resultCode'] = ['Resultat', 'Maschinenlesbarer Ergebnis-Code.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['resultMessage'] = ['Hinweis', 'Kurzer Hinweis/Fehlermeldung.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['requestIp'] = ['IP', 'IP-Adresse des Anfordernden.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['userAgent'] = ['User-Agent', 'Browser/Client-Kennung.'];
$GLOBALS['TL_LANG']['tl_co_door_job']['nonce'] = ['Nonce', 'Einmal-Wert zur Bestätigung (Schutz vor Spoofing).'];

$GLOBALS['TL_LANG']['tl_co_door_job']['areas'] = [
    'depot' => 'Depot',
    'swap-house' => 'Tauschhaus',
    'workshop' => 'Werkstatt',
    'sharing' => 'Ausleihe',
];

$GLOBALS['TL_LANG']['tl_co_door_job']['statuses'] = [
    'pending' => 'Wartend',
    'dispatched' => 'Ausgeliefert',
    'executed' => 'Ausgeführt',
    'failed' => 'Fehlgeschlagen',
    'expired' => 'Abgelaufen',
];
