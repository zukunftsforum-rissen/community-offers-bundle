# Executive Summary -- Digitales Zugangssystem Zukunftwohnen

## Überblick

Das Projekt „Zukunftwohnen -- Zugangssystem" realisiert ein sicheres,
netzwerkbasiertes Türöffnungssystem für gemeinschaftlich genutzte Räume
innerhalb eines Wohnprojekts.

Ziel ist es, physische Schlüssel durch eine digitale, nachvollziehbare
und datenschutzkonforme Lösung zu ersetzen.

------------------------------------------------------------------------

## Problemstellung

-   Mehrere Haushalte verwalten gemeinsam genutzte Räume.
-   Physische Schlüssel sind aufwendig in Verwaltung und Nachverfolgung.
-   Bei Verlust entstehen Sicherheitsrisiken und Kosten.
-   Transparente Protokollierung ist mit klassischen Schlüsseln nicht
    möglich.

------------------------------------------------------------------------

## Lösung

Ein webbasiertes System mit folgenden Komponenten:

-   **Web-App (PWA)** für Mitglieder
-   **Server (Contao / Symfony)** zur Rechteprüfung und Job-Verwaltung
-   **Raspberry Pi** als lokale Steuereinheit im Schuppen
-   **Relaismodul** zur Türöffnung

Das System arbeitet im sicheren Pull-Modell:

-   Keine offenen Ports im Schuppen
-   Keine eingehenden Verbindungen zum Raspberry Pi
-   Kommunikation ausschließlich über verschlüsselte HTTPS-Verbindungen

------------------------------------------------------------------------

## Sicherheitskonzept

-   Zugriff nur für eingeloggte Mitglieder
-   Geräte-Authentifizierung (deviceId + Secret)
-   Einmalige Nonce pro Türöffnung (Replay-Schutz)
-   Zeitbegrenzung pro Öffnungsvorgang (30 Sekunden Confirm-Fenster)
-   Rate Limiting und Sperrmechanismen gegen Missbrauch
-   Vollständige Protokollierung aller Vorgänge

------------------------------------------------------------------------

## Gesellschaftlicher Nutzen

-   Förderung nachhaltiger Sharing-Strukturen
-   Unterstützung gemeinschaftlicher Selbstorganisation
-   Reduktion von Schlüsselverlusten und Verwaltungsaufwand
-   Open-Source-Architektur ohne proprietäre Cloud-Abhängigkeit

------------------------------------------------------------------------

## Technische Besonderheiten

-   Pull-Architektur ohne Portweiterleitungen
-   Klare Zustandsmaschine (pending → dispatched →
    executed/failed/expired)
-   Modular erweiterbar (weitere Räume, Buchungssysteme,
    Smart-Home-Integration)
-   Energieeffiziente Hardware (Raspberry Pi)

------------------------------------------------------------------------

## Erweiterbarkeit & Zukunftsperspektive

Das System ist modular konzipiert und kann:

-   auf weitere Standorte skaliert werden
-   um Buchungsfunktionen ergänzt werden
-   auf andere Wohnprojekte übertragen werden
-   als Open-Source-Referenzprojekt dienen

------------------------------------------------------------------------

## Status

-   API implementiert und getestet (inkl. End-to-End-Tests)
-   Sicherheitsmechanismen aktiv
-   Dokumentation und Netzskizzen vorhanden
-   System bereit für Pilot- oder Dauerbetrieb

------------------------------------------------------------------------

## Zusammenfassung

Das Zugangssystem kombiniert moderne Webtechnologien mit einer sicheren,
wartungsarmen Netzarchitektur. Es ermöglicht eine digitale, transparente
und nachhaltige Verwaltung gemeinschaftlicher Ressourcen und ist
zugleich technisch robust sowie zukunftsfähig.
