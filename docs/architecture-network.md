# Architektur & Netzwerk -- Zukunftwohnen Zugangssystem

## Systemübersicht

Das Zugangssystem basiert auf einem Pull-Modell ohne eingehende
Verbindungen am Raspberry Pi.

### Komponenten

-   Internet
-   FritzBox (Hauptstandort)
-   Contao Server (API)
-   Raspberry Pi (Device im Schuppen)
-   Relais / Türöffner

## Netzwerkprinzip

-   Keine eingehenden Ports am Pi
-   Pi pollt regelmäßig die API
-   Kommunikation ausschließlich outbound (HTTPS)
-   Gast-LAN oder separates VLAN empfohlen

## Datenfluss

1.  PWA sendet Open-Request
2.  Server speichert Door-Job
3.  Pi pollt API
4.  Server dispatcht Job
5.  Pi öffnet Relais
6.  Pi sendet Confirm

## Sicherheitsaspekte

-   HTTPS Pflicht
-   Kein Portforwarding notwendig
-   Device Auth über Secret
-   Logging aller Türöffnungen
