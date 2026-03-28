# Netzwerk- und Systemarchitektur

Dieses Dokument beschreibt die Netzwerk- und Systemarchitektur
des Zugangssystems im Projekt Zukunftwohnen.

Das System basiert konsequent auf einem **Pull-Modell**
ohne eingehende Verbindungen zu den Geräten.

---

# Systemübersicht

Die Hauptkomponenten des Systems sind:

- Internet
- Router (z. B. FritzBox am Hauptstandort)
- Contao Server (API und Workflow-Logik)
- Raspberry Pi (physisches Device)
- Emulator-Device (für Test- und Diagnosezwecke)
- Relais / Türöffner-Hardware

Ziel der Architektur:

- minimale Angriffsfläche
- keine offenen Ports auf Devices
- robuste Kommunikation über HTTPS
- klare Trennung zwischen Backend und Hardware

---

# Netzwerkprinzip

Grundprinzipien:

- **Keine eingehenden Ports am Raspberry Pi**
- Geräte kommunizieren ausschließlich outbound
- Kommunikation erfolgt ausschließlich über HTTPS
- Geräte authentifizieren sich mit einem Device-Token
- Betrieb in Gast-LAN oder separatem VLAN empfohlen

## Poll-Mechanismus

Das Device arbeitet mit einem festen Poll-Intervall:

**Poll-Intervall: 2 Sekunden (hart im Code definiert)**

Eigenschaften:

- Poll erfolgt **kontinuierlich**
- Kein Backoff bei Idle-Zustand
- Poll erfolgt auch, wenn keine Jobs vorhanden sind
- Intervall ist aktuell **nicht konfigurierbar**

Lastabschätzung:

- 1 Device  → 30 Requests / Minute  
- 5 Devices → 150 Requests / Minute  
- 10 Devices → 300 Requests / Minute  

Dieses Modell verhindert:

- direkte Zugriffe auf Geräte
- Portforwarding-Risiken
- unnötige Netzwerkfreigaben

---

# Datenfluss

Der typische Ablauf eines Türöffnungsprozesses:

1. PWA sendet Open-Request an API  
2. Server erstellt Door-Job  
3. Device pollt API  
4. Server dispatcht passenden Job  
5. Device aktiviert Relais  
6. Device sendet Confirm  

Wichtige Eigenschaften:

- Kommunikation ist zustandsbasiert
- Jobs werden nur an passende Devices ausgeliefert
- Jeder Schritt wird protokolliert

---

# Sicherheitsaspekte

Wichtige Sicherheitsmaßnahmen:

- HTTPS ist verpflichtend
- Kein Portforwarding erforderlich
- Device-Authentifizierung über Token
- Speicherung des Tokens nur als Hash (z. B. SHA-256)
- Nonce-basierte Bestätigung bei Job-Ausführung
- Logging aller relevanten Ereignisse
- Rate-Limiting für Device-Kommunikation empfohlen

Zusätzliche empfohlene Maßnahmen:

- separates Netzwerksegment für Devices
- eingeschränkter Internetzugriff für Devices
- Monitoring von Device-Aktivität

---

# Netzwerksegmentierung (empfohlen)

Empfohlenes Setup:

- Hauptnetz: Server und Backend
- Separates VLAN oder Gastnetz: Devices
- Zugriff vom Device-Netz nur nach außen erlaubt
- Kein direkter Zugriff aus dem Internet auf Devices

Dies reduziert das Risiko bei kompromittierten Geräten.

---

# Architekturdiagramm

Die visuelle Darstellung der Architektur befindet sich in:

diagrams/generated/architecture.svg

Dieses Diagramm zeigt:

- Netzwerkgrenzen
- Kommunikationspfade
- Rollen der einzelnen Komponenten

Das Diagramm sollte regelmäßig aktualisiert werden,
wenn sich die Systemarchitektur ändert.
