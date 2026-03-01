# Datenmodell – Zukunftwohnen Zugangssystem (Contao Bundle)

Stand: 2026-03-01

Diese Datei beschreibt die **persistenten Tabellen** aus den DCA-Dateien in `Resources/contao/dca/`:

- `tl_co_access_request`
- `tl_co_device`
- `tl_co_door_job`
- `tl_co_door_log`

Zusätzlich relevant (Contao Core):

- `tl_member` (Mitglieder)
- `tl_member_group` (Gruppen/Rollen)

## ER-Überblick

Die folgende Grafik zeigt die wichtigsten **technischen** (Feld-basierte) und **logischen** Beziehungen:

- PNG: `data-model-er.png`
- PDF: `data-model-er.pdf`

> Hinweis: Contao nutzt bei Bundle-Tabellen oft **keine echten Foreign Keys** im DB-Schema. Beziehungen sind daher primär **konventionell** (z.B. `memberId` → `tl_member.id`).

## Beziehungen (praktisch)

### tl_co_access_request → tl_member / Gruppen (logisch)
- `tl_co_access_request` speichert DOI-Anfragen und gewünschte Areas (`requestedAreas`).
- Nach Genehmigung (`approved=1` und `emailConfirmed=1`) werden im Backend typischerweise **Mitglied** und/oder **Gruppenzuordnung** in Contao gesetzt (Berechtigungen).

### tl_member → tl_co_door_job (technisch)
- `tl_co_door_job.requestedByMemberId` referenziert logisch `tl_member.id`.

### tl_co_device → tl_co_door_job (technisch)
- `tl_co_door_job.dispatchToDeviceId` referenziert logisch `tl_co_device.deviceId` (oder ein anderes Device-Kennfeld – siehe DCA).

### tl_member → tl_co_door_log (technisch)
- `tl_co_door_log.memberId` referenziert logisch `tl_member.id`.

## Tabellen

## 1) `tl_co_access_request`

**Zweck:** DOI-basierte Zugangs-Anfragen (E-Mail), inkl. gewünschter Areas und Freigabe-Status.

### Felder
| Feld | SQL |
|---|---|
| `id` | `int(10) unsigned NOT NULL auto_increment` |
| `tstamp` | `int(10) unsigned NOT NULL default 0` |
| `firstname` | `varchar(255) NOT NULL default ''` |
| `lastname` | `varchar(255) NOT NULL default ''` |
| `email` | `varchar(255) NOT NULL default ''` |
| `mobile` | `varchar(64) NOT NULL default ''` |
| `street` | `varchar(255) NOT NULL default ''` |
| `postal` | `varchar(16) NOT NULL default ''` |
| `city` | `varchar(255) NOT NULL default ''` |
| `requestedAreas` | `blob NULL` |
| `token` | `varchar(64) NOT NULL default ''` |
| `tokenExpiresAt` | `int(10) unsigned NOT NULL default 0` |
| `emailConfirmed` | `char(1) NOT NULL default ''` |
| `approved` | `char(1) NOT NULL default ''` |

### Keys / Indizes
| Key | Typ |
|---|---|
| `id` | `primary` |
| `email` | `index` |
| `token` | `index` |
| `emailConfirmed` | `index` |
| `approved` | `index` |

---

## 2) `tl_co_device`

**Zweck:** Registrierte Geräte (z.B. Raspberry Pi), die Jobs abholen dürfen.

### Felder
| Feld | SQL |
|---|---|
| `id` | `int(10) unsigned NOT NULL auto_increment` |
| `tstamp` | `int(10) unsigned NOT NULL default 0` |
| `name` | `varchar(128) NOT NULL default ''` |
| `deviceId` | `varchar(64) NOT NULL default ''` |
| `areas` | `blob NULL` |
| `enabled` | `char(1) NOT NULL default ''` |
| `apiTokenHash` | `varchar(64) NOT NULL default ''` |
| `lastSeen` | `int(10) unsigned NOT NULL default 0` |
| `ipLast` | `varchar(64) NOT NULL default ''` |

### Keys / Indizes
| Key | Typ |
|---|---|
| `id` | `primary` |
| `deviceId` | `unique` |
| `enabled` | `index` |

---

## 3) `tl_co_door_job`

**Zweck:** Door-Jobs (Open Requests) mit TTL/Status und Dispatch an Geräte.

### Felder
| Feld | SQL |
|---|---|
| `id` | `int(10) unsigned NOT NULL auto_increment` |
| `tstamp` | `int(10) unsigned NOT NULL default 0` |
| `createdAt` | `int(10) unsigned NOT NULL default 0` |
| `expiresAt` | `int(10) unsigned NOT NULL default 0` |
| `area` | `varchar(64) NOT NULL default ''` |
| `requestedByMemberId` | `int(10) unsigned NOT NULL default 0` |
| `requestIp` | `varchar(64) NOT NULL default ''` |
| `userAgent` | `varchar(255) NOT NULL default ''` |
| `status` | `varchar(20) NOT NULL default 'pending'` |
| `dispatchToDeviceId` | `varchar(64) NOT NULL default ''` |
| `dispatchedAt` | `int(10) unsigned NOT NULL default 0` |
| `executedAt` | `int(10) unsigned NOT NULL default 0` |
| `nonce` | `varchar(64) NOT NULL default ''` |
| `attempts` | `int(10) unsigned NOT NULL default 0` |
| `resultCode` | `varchar(40) NOT NULL default ''` |
| `resultMessage` | `varchar(255) NOT NULL default ''` |

### Keys / Indizes
| Key | Typ |
|---|---|
| `id` | `primary` |
| `status,expiresAt` | `index` |
| `area` | `index` |
| `dispatchToDeviceId` | `index` |

---

## 4) `tl_co_door_log`

**Zweck:** Audit-/Tracing-Log der Door-Vorgänge.

### Felder
| Feld | SQL |
|---|---|
| `id` | `int(10) unsigned NOT NULL auto_increment` |
| `tstamp` | `int(10) unsigned NOT NULL default 0` |
| `memberId` | `int(10) unsigned NOT NULL default 0` |
| `area` | `varchar(64) NOT NULL default ''` |
| `action` | `varchar(64) NOT NULL default ''` |
| `result` | `varchar(32) NOT NULL default ''` |
| `ip` | `varchar(64) NOT NULL default ''` |
| `userAgent` | `varchar(255) NOT NULL default ''` |
| `message` | `varchar(255) NOT NULL default ''` |
| `context` | `mediumtext NULL` |

### Keys / Indizes
| Key | Typ |
|---|---|
| `id` | `primary` |
| `memberId` | `index` |
| `area` | `index` |
| `tstamp` | `index` |
| `action` | `index` |
| `result` | `index` |

---

## Datentypen & Serialisierung

In mehreren Feldern wird Contao-typisch serialisiert/Blob genutzt:

- `tl_co_access_request.requestedAreas` (Blob): serialisierte Liste der Areas
- `tl_co_device.areas` (Blob): serialisierte Liste der Areas
- `tl_co_door_log.context` (Blob/Text): kontextuelle Daten (z.B. JSON/serialisiert)

Empfehlung: bei Debugging/Exports konsequent über `StringUtil::deserialize(..., true)` / `serialize()` arbeiten und im Code klar dokumentieren, ob es sich um `list<string>` handelt.

