# Projektbeschreibung — Digitales Zugangssystem Zukunftwohnen

## Projektziel

Entwicklung eines digitalen Zugangssystems für gemeinschaftlich genutzte
Räume im Wohnprojekt Zukunftwohnen.

Das System ermöglicht:

- Zeitgemäße digitale Türöffnung
- Geregelte Zugriffskontrolle pro Raum (Area)
- Transparente und nachvollziehbare Protokollierung
- Datenschutzkonforme Umsetzung
- Sicheren Betrieb ohne direkte Hardware-Ansteuerung aus dem Frontend

---

## Gesellschaftlicher Nutzen

Das System unterstützt gemeinschaftliches Wohnen
und nachhaltige Nutzung gemeinsamer Ressourcen.

Konkrete Vorteile:

- Förderung gemeinschaftlicher Ressourcennutzung
- Unterstützung nachhaltiger Sharing-Konzepte
- Reduktion physischer Schlüsselverwaltung
- Stärkung digitaler Selbstorganisation
- Nachvollziehbarkeit sicherheitsrelevanter Vorgänge

---

## Technische Umsetzung

Das System basiert auf einer modularen Webarchitektur.

Zentrale Komponenten:

- Webbasierte Anwendung (Progressive Web App — PWA)
- Open-Source Software (Symfony / Contao)
- Raspberry Pi oder kompatibles Device als Steuerungseinheit
- Pull-Modell ohne eingehende Verbindungen zu Devices
- Asynchrone Türsteuerung über Job-Workflow

Wichtiges Architekturprinzip:

Devices öffnen Türen **nicht direkt auf Benutzeranforderung**,  
sondern holen aktiv ausstehende Jobs vom Server ab.

Dieses Modell reduziert die Angriffsfläche erheblich.

---

## Sicherheitskonzept

Mehrstufiges Sicherheitsmodell:

- Verschlüsselte Kommunikation (HTTPS/TLS)
- Zugriff nur für berechtigte Mitglieder
- Geräteauthentifizierung per Token
- Nonce-basierte Bestätigung von Türaktionen
- Rate Limiting zum Schutz vor Missbrauch
- Automatische Zeitfenster (confirmWindow)
- Ablaufmechanismus für veraltete Jobs (`expired`)
- Vollständige Audit-Protokollierung mit Correlation-ID

Zusätzlich:

- Keine offenen Ports zu Devices erforderlich
- Geräte befinden sich in getrennten Netzwerksegmenten
  (z. B. Gast-LAN oder VLAN)

---

## Erweiterbarkeit

Das System ist modular aufgebaut.

Es ermöglicht zukünftige Erweiterungen ohne grundlegende Änderungen.

Mögliche Erweiterungen:

- Integration weiterer Räume
- Erweiterung um Buchungssysteme
- Integration zusätzlicher Sensorik
- Anbindung an weitere Smart-Home-Komponenten
- Nutzung in anderen Wohnprojekten
- Mehrere Devices pro Standort

---

## Nachhaltigkeit

Das System unterstützt nachhaltigen Betrieb.

Merkmale:

- Einsatz energieeffizienter Hardware (z. B. Raspberry Pi)
- Nutzung offener Softwarestandards
- Open-Source Architektur
- Wartungsarm durch Pull-Modell
- Keine Abhängigkeit von proprietären Cloud-Diensten
- Lokale Datenhaltung möglich

---

## Betriebssicherheit

Das System ist für langfristigen Betrieb ausgelegt.

Unterstützende Maßnahmen:

- Strukturierte Protokollierung (Audit Logs)
- Fehleranalyse über Correlation-ID
- Automatisches Ablaufmanagement für Jobs
- Überwachung von Rate Limits und Fehlercodes
- Dokumentierte Betriebsabläufe (Runbooks)

Diese Maßnahmen ermöglichen:

- Schnelle Fehlersuche
- Nachvollziehbarkeit von Ereignissen
- Stabilen Dauerbetrieb
