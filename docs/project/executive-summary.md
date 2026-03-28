# Executive Summary — Digitales Zugangssystem Zukunftwohnen

## Überblick

Das Projekt „Zukunftwohnen — Zugangssystem" realisiert ein sicheres,
netzwerkbasiertes Türöffnungssystem für gemeinschaftlich genutzte Räume
innerhalb eines Wohnprojekts.

Ziel ist es, physische Schlüssel durch eine digitale,
nachvollziehbare und datenschutzkonforme Lösung zu ersetzen.

Das System kombiniert moderne Webtechnologien mit einer
asynchronen, sicherheitsorientierten Architektur.

---

## Problemstellung

Mehrere Haushalte verwalten gemeinsam genutzte Räume.

Typische Herausforderungen:

- Physische Schlüssel sind aufwendig in Verwaltung und Nachverfolgung.
- Bei Verlust entstehen Sicherheitsrisiken und Kosten.
- Schlüsselkopien sind schwer kontrollierbar.
- Transparente Protokollierung ist mit klassischen Schlüsseln nicht möglich.
- Zugriffsrechte lassen sich nur schwer flexibel anpassen.

---

## Lösung

Ein webbasiertes System mit mehreren kooperierenden Komponenten:

- **Web-App (PWA)** für Mitglieder
- **Server (Contao / Symfony)** zur Rechteprüfung und Job-Verwaltung
- **Device (z. B. Raspberry Pi)** als lokale Steuereinheit
- **Relaismodul** zur physischen Türöffnung

Das System arbeitet im sicheren Pull-Modell:

- Keine offenen Ports im Geräte-Netz
- Keine eingehenden Verbindungen zu Devices
- Kommunikation ausschließlich über verschlüsselte HTTPS-Verbindungen
- Türöffnungen erfolgen asynchron über Job-Workflow

Wichtig:

Das Device öffnet Türen **nicht direkt auf Benutzeranforderung**,  
sondern verarbeitet vom Server bereitgestellte Jobs.

---

## Sicherheitskonzept

Mehrstufiges Sicherheitsmodell:

- Zugriff nur für eingeloggte Mitglieder
- Geräte-Authentifizierung (`deviceId` + Token)
- Einmalige Nonce pro Türöffnung (Replay-Schutz)
- Zeitfenster pro Öffnungsvorgang (`confirmWindow`)
- Automatischer Ablauf veralteter Jobs (`expired`)
- Rate Limiting und Sperrmechanismen gegen Missbrauch
- Vollständige Audit-Protokollierung aller Vorgänge
- Nachvollziehbarkeit über Correlation-ID

Zusätzlich:

- Keine direkten Netzwerkverbindungen zu Devices erforderlich
- Netzwerksegmentierung möglich (z. B. Gast-LAN)

---

## Gesellschaftlicher Nutzen

Das System unterstützt nachhaltige und gemeinschaftliche Nutzung
von Ressourcen.

Vorteile:

- Förderung nachhaltiger Sharing-Strukturen
- Unterstützung gemeinschaftlicher Selbstorganisation
- Reduktion von Schlüsselverlusten
- Verringerung administrativer Aufwände
- Open-Source-Architektur ohne proprietäre Cloud-Abhängigkeit

---

## Technische Besonderheiten

Zentrale technische Eigenschaften:

- Pull-Architektur ohne Portweiterleitungen
- Asynchroner Workflow für Türaktionen
- Klare Zustandsmaschine:

pending → dispatched → executed / failed / expired

- Atomarer Dispatch von Jobs
- Nonce-basierte Bestätigung
- Modular erweiterbare Architektur
- Energieeffiziente Hardware (z. B. Raspberry Pi)

Diese Architektur erhöht:

- Systemsicherheit
- Stabilität
- Wartbarkeit

---

## Erweiterbarkeit & Zukunftsperspektive

Das System ist modular konzipiert.

Es kann erweitert werden für:

- Weitere Räume oder Gebäude
- Mehrere Devices pro Standort
- Integration zusätzlicher Sensorik
- Buchungssysteme
- Smart-Home-Komponenten
- Einsatz in anderen Wohnprojekten
- Nutzung als Open-Source-Referenzprojekt

---

## Status

Aktueller Entwicklungsstand:

- API implementiert und getestet
- Workflow-Logik stabil
- Sicherheitsmechanismen aktiv
- Gerätekommunikation funktionsfähig
- Dokumentation umfangreich vorhanden
- System geeignet für Pilot- und Dauerbetrieb

---

## Zusammenfassung

Das Zugangssystem kombiniert moderne Webtechnologien mit einer sicheren,
wartungsarmen Systemarchitektur.

Es ermöglicht:

- Digitale Zugangskontrolle
- Transparente Nachvollziehbarkeit
- Sicheren Betrieb ohne offene Ports
- Nachhaltige Verwaltung gemeinschaftlicher Ressourcen

Die Lösung ist technisch robust,
langfristig wartbar
und auf zukünftige Erweiterungen ausgelegt.
