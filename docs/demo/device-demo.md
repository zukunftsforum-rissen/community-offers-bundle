# Device Demo (Visual Demonstration Mode)

Der Demo dient ausschließlich zu Demonstrations- und
Testzwecken im Sinne einer **visuellen Präsentation**.

Wichtig:

Der Demo ist **kein echtes Device** und **kein Emulator**.
Er simuliert nur visuell einen Ablauf und greift nicht
auf die produktiven Geräteprozesse zu.

---

# Zweck des Demos

Der Demo wird verwendet für:

- Präsentationen
- Schulungen
- Benutzer-Demonstrationen
- UI-Tests ohne echte Hardware

Er ersetzt **nicht** den Emulator.

Für technische Tests mit realistischem Verhalten
soll stattdessen der **Emulator** verwendet werden.

---

# Technische Eigenschaften

Der Demo:

- verwendet **nicht** den produktiven Poll-Endpunkt  
  `/api/device/poll`

- erzeugt **keine** Einträge in:

  - `tl_co_device.lastSeen`
  - `tl_co_door_log`

- beeinflusst keine echten Geräte

- verändert keine Produktionszustände

---

# Auswirkungen auf Monitoring

Der Device Monitor zeigt:

- ausschließlich echte Geräte
- optional Emulator-Geräte
- **keine Demo-Aktivitäten**

Das ist bewusst so implementiert, um:

- Fehlinterpretationen zu vermeiden
- Produktionsdaten sauber zu halten
- Monitoring zuverlässig zu halten

---

# Abgrenzung: Demo vs Emulator

| Funktion | Demo | Emulator |
|----------|------|-----------|
| Visuelle Simulation | ✔ | ✖ |
| API-Polling | ✖ | ✔ |
| Job-Verarbeitung | ✖ | ✔ |
| Logging | ✖ | ✔ |
| Hardware-Ersatz | ✖ | ✔ |
| Präsentationszwecke | ✔ | ✖ |

Diese Unterscheidung ist wichtig, um:

- Testverhalten korrekt zu verstehen
- Produktionssysteme nicht zu beeinflussen
- Fehlersuche sauber durchzuführen

---

# Sicherheitshinweis

Da der Demo keine echten Geräteoperationen ausführt:

- entstehen keine Sicherheitsrisiken
- werden keine Tokens benötigt
- erfolgt keine Geräteauthentifizierung

Er ist daher für Präsentationen besonders geeignet.
