# Datenmodell (aus DCA generiert)

Diese Datei wurde aus `contao/dca/*.php` generiert (Felder + SQL + Keys).

## 1) `tl_co_access_request`

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

### Felder
| Feld | SQL |
|---|---|

### Keys / Indizes
| Key | Typ |
|---|---|

---

## 3) `tl_co_door_job`

### Felder
| Feld | SQL |
|---|---|

### Keys / Indizes
| Key | Typ |
|---|---|

---

## 4) `tl_co_door_log`

### Felder
| Feld | SQL |
|---|---|

### Keys / Indizes
| Key | Typ |
|---|---|

---
