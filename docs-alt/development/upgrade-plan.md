# Upgrade Plan (PHPUnit + Stylelint)

Stand: 2026-03-01

## Ziel

Stabil bleiben (bewusst PHPUnit 9.6), gleichzeitig den späteren Major-Upgrade vorbereiten, ohne unnötiges Risiko.

## Phase 1 – Stabilisieren (jetzt)

### PHPUnit (bei 9.6 bleiben)

- Konfiguration auf 9.6-Schema halten (`phpunit.xml.dist` ist bereits angepasst).
- Nur Patch-/Minor-Updates innerhalb 9.6 zulassen.

Commands:

```bash
composer update phpunit/phpunit --with-all-dependencies
./vendor/bin/phpunit -c phpunit.xml.dist tests
```

### Stylelint (Status einfrieren)

- Aktuellen Lint-Status grün halten.
- Keine Regel- oder Config-Migration im Hauptbranch mischen.

Command:

```bash
npm run lint:css
```

---

## Phase 2 – Vorbereiten (kurzfristig, separater Branch)

### Deprecation-Transparenz

- PHPUnit-Run regelmäßig in CI ausführen.
- Outdated-Report nur informativ (nicht failen).

Commands:

```bash
composer outdated --direct
npm outdated --depth=0
```

### Stylelint-Major-Probe (isoliert)

- In Feature-Branch `stylelint` + `stylelint-config-standard` gemeinsam anheben.
- Nur Regelanpassungen in CSS vornehmen, keine fachlichen Refactorings.

Beispiel:

```bash
npm install --save-dev stylelint@latest stylelint-config-standard@latest
npm run lint:css
```

---

## Phase 3 – Gezielte Major-Upgrades (wenn Zeitfenster da ist)

### PHPUnit 10+/13 (später)

- Erst wenn ausreichend Tests vorhanden und Deprecations abgearbeitet sind.
- Upgrade in dediziertem Branch mit klarer Test-Matrix.

### Stylelint dauerhaft aktualisieren

- Nach erfolgreicher Probe aus Phase 2 in den Hauptbranch übernehmen.

---

## Minimal-CI-Empfehlung

1. Job: PHPUnit

```bash
./vendor/bin/phpunit -c phpunit.xml.dist tests
```

2. Job: CSS-Lint

```bash
npm run lint:css
```

3. Optional monatlich: Outdated-Report

```bash
composer outdated --direct
npm outdated --depth=0
```

---

## Done-Kriterien

- PHPUnit-Job dauerhaft grün auf 9.6.
- Keine Schema-Warnungen mehr aus `phpunit.xml.dist`.
- Stylelint-Status grün und reproduzierbar.
- Major-Upgrades nur in separaten, reviewbaren Branches.
