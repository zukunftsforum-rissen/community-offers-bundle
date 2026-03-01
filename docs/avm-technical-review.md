# Technische Prüfungsfassung -- Zugangssystem Zukunftwohnen

## 1. Ziel des Systems

Realisierung eines sicheren, netzwerkbasierten Türöffnungssystems ohne
eingehende Verbindungen im Zielnetz (Schuppen / Nebengebäude).

Systemprinzip: Pull-Modell

------------------------------------------------------------------------

## 2. Netzwerkarchitektur

### Komponenten

-   Internet
-   FritzBox (Hauptrouter Wohngebäude)
-   Optional: Zweitrouter im Schuppen (LAN-Bridge oder eigenes VLAN)
-   Raspberry Pi (Device)
-   Relaismodul (Türöffner)

### Sicherheitsprinzip

-   Keine Portfreigaben erforderlich
-   Keine eingehenden Verbindungen zum Pi
-   Pi initiiert ausschließlich ausgehende HTTPS-Verbindungen
-   TLS-gesicherte Kommunikation

------------------------------------------------------------------------

## 3. Kommunikationsmodell

1.  Mitglied sendet Öffnungsanforderung an Server
2.  Server speichert Job in Datenbank
3.  Raspberry Pi pollt API in Intervallen
4.  Server dispatcht Job
5.  Pi öffnet Relais
6.  Pi bestätigt Ausführung

------------------------------------------------------------------------

## 4. Sicherheitsmaßnahmen

-   TLS (HTTPS)
-   Device-Authentifizierung (deviceId + Secret)
-   Nonce pro Dispatch (Replay-Schutz)
-   Rate Limiting (Mitglied + Area)
-   Locking (Area + Member)
-   Confirm Timeout (30 Sekunden)

------------------------------------------------------------------------

## 5. Bewertung aus Netzwerksicht

Das System benötigt:

-   Keine Portweiterleitungen
-   Keine exponierten Dienste im Schuppen
-   Keine eingehenden Firewall-Regeln

Empfehlung:

-   Separates VLAN oder Gast-LAN für Raspberry Pi
-   Regelmäßige Secret-Rotation
-   Monitoring von Fehlversuchen
