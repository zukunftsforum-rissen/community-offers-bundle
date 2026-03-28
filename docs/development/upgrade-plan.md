# Upgrade Plan (PHPUnit + Stylelint)

Stand: 2026-03-01

Dieses Dokument beschreibt eine konservative Upgrade-Strategie
für Test- und Lint-Tools, mit Fokus auf Stabilität und
kontrollierte Major-Upgrades.

---

# Ziel

Das System soll stabil bleiben,
während zukünftige Major-Upgrades vorbereitet werden.

Strategie:

- PHPUnit bewusst stabil halten
- Stylelint kontrolliert weiterentwickeln
- Risiken durch isolierte Branches minimieren
- Keine unnötigen Änderungen im Hauptbranch

---

# Phase 1 — Stabilisieren (aktuell)

## PHPUnit (bei Version 9.6 bleiben)

Empfehlung:

- Konfiguration im PHPUnit-9.6-Schema halten
- Nur Patch- und Minor-Updates zulassen
- Keine Major-Upgrades im laufenden Betrieb

Commands:

composer update phpunit/phpunit --with-all-dependencies

./vendor/bin/phpunit -c phpunit.xml.dist tests

Ziel:

- Tests bleiben stabil
- CI bleibt zuverlässig
- Keine neuen Deprecation-Risiken

---

## Stylelint (Status einfrieren)

Empfehlung:

- Aktuellen Lint-Status grün halten
- Keine Regelmigration im Hauptbranch durchführen

Command:

npm run lint:css

Wichtig:

Lint-Regeln nicht neben funktionalen Änderungen verändern.

---

# Phase 2 — Vorbereiten (separater Branch)

## Deprecation-Transparenz

Regelmäßig prüfen:

- veraltete Pakete
- mögliche zukünftige Konflikte

Commands:

composer outdated --direct

npm outdated --depth=0

Hinweis:

Outdated-Reports sollen informativ sein,
nicht blockierend.

---

## Stylelint Major-Probe (isoliert)

Vorgehen:

- Feature-Branch erstellen
- Stylelint-Version erhöhen
- Konfigurationsanpassungen durchführen

Beispiel:

npm install --save-dev stylelint@latest stylelint-config-standard@latest

npm run lint:css

Wichtig:

Nur Formatierungsänderungen,
keine funktionalen Änderungen.

---

# Phase 3 — Gezielte Major-Upgrades

Diese Phase erfolgt nur:

- bei ausreichender Testabdeckung
- mit definiertem Zeitfenster
- in isolierten Branches

---

## PHPUnit Major-Upgrade (z. B. 10+ oder höher)

Voraussetzungen:

- Deprecations bereinigt
- Tests vollständig grün
- CI stabil

Empfehlung:

Upgrade immer in:

separatem Branch

mit:

klarer Testmatrix.

---

## Stylelint dauerhaft aktualisieren

Nach erfolgreicher Probe:

- Änderungen in Hauptbranch übernehmen
- CI erneut prüfen

---

# Minimal-CI-Empfehlung

Empfohlene CI-Jobs:

---

## Job 1 — PHPUnit

./vendor/bin/phpunit -c phpunit.xml.dist tests

Ziel:

- Testlauf muss dauerhaft stabil bleiben

---

## Job 2 — CSS Lint

npm run lint:css

Ziel:

- CSS-Regeln bleiben konsistent

---

## Job 3 — Outdated-Report (optional, z. B. monatlich)

composer outdated --direct

npm outdated --depth=0

Ziel:

- Upgradebedarf frühzeitig erkennen

---

# Done-Kriterien

Die Upgrade-Phase gilt als erfolgreich, wenn:

- PHPUnit-Job dauerhaft grün bleibt
- Keine Schema-Warnungen in phpunit.xml.dist auftreten
- Stylelint reproduzierbar grün ist
- Major-Upgrades isoliert durchgeführt wurden
- CI weiterhin stabil bleibt

---

# Erweiterungsempfehlung (optional)

Optional sinnvoll:

## Sicherheitsabhängigkeiten prüfen

Empfohlen:

composer audit

Ziel:

Bekannte Sicherheitslücken früh erkennen.

---

## Versionsstrategie dokumentieren

Empfohlen:

Semantic Versioning verwenden:

MAJOR.MINOR.PATCH

Beispiel:

v1.0.0  
v1.1.0  
v1.1.1  

