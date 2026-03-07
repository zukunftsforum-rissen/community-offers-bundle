
# Community Offers – Systemverständnis & Review-Leitfaden (Erweitert)

Dieses Dokument fasst **alle empfohlenen Vorgehensweisen** zusammen, um das
Community‑Offers‑System strukturiert zu verstehen, zu prüfen und zu dokumentieren.

Es kombiniert:

• Datenmodell  
• Architekturverständnis  
• Code‑Reading‑Route  
• Review‑Checklisten  
• KI‑Prompts für Codeanalyse  
• Vorgehen für systematische Audits

---

# 1. Mentales Minimalmodell

Das gesamte System lässt sich auf fünf Kernbegriffe reduzieren:

Member  
DoorJob  
Area  
Device  
Nonce

Merksatz:

Member erstellt DoorJob → Device führt Job aus.

---

# 2. Fachliches Domänenmodell

Fachlich existieren folgende Konzepte:

Member → DoorJob → Area → Device

Bedeutung:

Member  
Benutzer des Systems

DoorJob  
Auftrag eine Tür zu öffnen

Area  
logischer Zugangsbereich

Device  
Raspberry Pi, der Türöffnungen ausführt

---

# 3. Wichtig: Area ist kein eigenes Entity

In der aktuellen Implementierung:

Area = String-Identifier

Beispiele:

workshop  
sharing  
swap-house  
depot

Areas erscheinen in:

tl_co_device.areas (Blob-Liste)  
tl_co_door_job.area  
tl_co_door_log.area

---

# 4. Physisches Datenbankmodell

## tl_member

Contao-Mitglieder.

Relevante Rolle:

• erzeugt DoorJobs
• Permissions hängen indirekt an Memberdaten

---

## tl_co_device

Repräsentiert ein Hardwaregerät.

Wichtige Felder:

id  
name  
deviceId  
enabled  
apiTokenHash  
areas (blob)  
lastSeen  
ipLast

Die Spalte **areas** enthält eine Liste unterstützter Areas.

---

## tl_co_door_job

Zentrale Entität des Systems.

Wichtige Felder:

id  
createdAt  
expiresAt  
area  
requestedByMemberId  
status  
dispatchToDeviceId  
dispatchedAt  
executedAt  
nonce  
attempts  
resultCode  
resultMessage

---

## tl_co_door_log

Audit‑Log des Systems.

Felder:

memberId  
area  
action  
result  
ip  
userAgent  
message  
context

Zweck:

Nachvollziehbarkeit aller Aktionen.

---

# 5. DoorJob Lifecycle

pending → confirmed

optional erweitert:

created → pending → confirmed

pending  
wartet auf Device

confirmed  
Tür wurde geöffnet

---

# 6. Permission‑Regeln

| Area | Regel |
|-----|------|
| workshop | Mitglied ≥ 18 |
| sharing | Mitglied |
| swap-house | Mitglied |
| depot | Mitglied mit Kisten‑Abo |

---

# 7. Systemworkflow

Member

POST /api/door/open/{area}

→ AccessController

→ DoorJobService

→ DoorJob (pending)

Device

GET /api/device/poll

→ DeviceController

→ Device führt Job aus

Device

POST /api/device/confirm

→ DoorJobService

→ DoorJob (confirmed)

---

# 8. Zentrale Systeminvarianten

Diese Regeln dürfen im Code niemals verletzt werden:

1 DoorJob gehört genau zu einem Member

1 DoorJob gehört genau zu einer Area

DoorJob darf nur einmal bestätigt werden

Nonce darf nur einmal verwendet werden

Device darf nur Jobs für unterstützte Areas ausführen

---

# 9. Empfohlene Code‑Reading‑Route

Beim ersten Verständnis des Systems folgende Reihenfolge lesen:

1 docs/ai-project-context.md

2 docs/ai/architecture.md

3 docs/ai/api-flows.md

4 config/routes.yaml

5 src/Controller/AccessController.php

6 src/Controller/DeviceController.php

7 src/Service/DoorJobService.php

8 Permission-Logik

9 src/Service/LoggingService.php

10 Entities und Repositories

11 Tests

---

# 10. 5 Standardfragen beim Lesen jeder Datei

1 Was ist die fachliche Verantwortung dieser Datei?

2 Welche Klassen werden aufgerufen?

3 Welche Eingaben erwartet der Code?

4 Welche Seiteneffekte entstehen?

5 Passt der Code zur Architektur-Dokumentation?

---

# 11. Kritische Übergänge im System

Besonders wichtig zu verstehen:

PWA → AccessController

AccessController → DoorJobService

DeviceController → DoorJobService

DoorJobService → Datenbank

---

# 12. Strukturierter Code‑Review‑Workflow

Für jede Datei:

1 Abschnittsweise erklären lassen

2 Architekturabgleich prüfen

3 Sicherheitsaspekte prüfen

4 Fehlende Fehlerfälle identifizieren

5 Fehlende Tests identifizieren

---

# 13. Empfohlene KI‑Prompts für Reviews

## Allgemeines File Review

Prüfe:

• Zweck der Datei  
• Architekturkonformität  
• fehlende Fehlerfälle  
• fehlende Tests  
• unnötige Komplexität

Antwortstruktur:

A Kurzbeschreibung  
B Abschnittserklärung  
C Architekturabgleich  
D Risiken  
E fehlende Tests  
F relevante Dateien  
G Verbesserungen

---

## Zeile‑für‑Zeile Erklärung

Für jeden Abschnitt:

• Was macht der Code?  
• Warum wurde er so geschrieben?  
• Welche Abhängigkeiten bestehen?  
• Welche Seiteneffekte entstehen?

---

## Controller‑Review

Prüfen:

• enthält Controller Business‑Logik?  
• sind Request‑Validierungen korrekt?  
• sind Response‑Strukturen stabil?

---

## Service‑Review

Prüfen:

• klare Verantwortung des Services  
• korrekte Statuswechsel  
• keine illegalen Zustände

---

## Permission‑Analyse

Prüfen:

• stimmen Regeln mit Domainmodell überein  
• sind alle Areas abgedeckt  
• sind negative Fälle getestet

---

## Angreiferperspektive

Fragen:

• welche Parameter kann ein Client manipulieren?

• können Berechtigungen umgangen werden?

• fehlen Validierungen?

---

# 14. Praktische Review‑Technik

Beim Lesen des Codes drei Listen führen:

verstanden

unklar

widerspricht Dokumentation

---

# 15. KI‑unterstützter Reviewprozess

Schrittweise Analyse:

1 Datei erklären lassen

2 Architekturabgleich prüfen

3 Sicherheitsanalyse durchführen

4 Testlücken identifizieren

5 nächste relevante Dateien ermitteln

Frage:

"Welche drei Dateien sollte ich als nächstes lesen, um diese Datei vollständig zu verstehen?"

---

# 16. Langfristige Dokumentationsstrategie

Empfohlene Struktur:

docs/ai/

architecture.md

domain-model.md

api-flows.md

data-model-review-guide.md

code-reading-route.md

generated/

Automatisch generierte Dateien sollten in

docs/ai/generated/

liegen.

---

# 17. Ziel dieses Leitfadens

Dieses Dokument dient als:

• Gedächtnisstütze  
• Einstiegspunkt für neue Entwickler  
• Review‑Checkliste  
• Grundlage für KI‑gestützte Codeanalyse
