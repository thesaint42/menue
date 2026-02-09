# Backup & Restore System - EMOS v3.0

## Übersicht

Das EMOS v3.0 System enthält ein vollständiges Backup- und Wiederherstellungs-System, das mehrere Szenarien unterstützt:

- ✅ **Automatische Backups** erstellen
- ✅ **Datenbank + Dateien** sichern
- ✅ **Beliebige Backups wiederherstellen**
- ✅ **Auf frisch installierten Systemen** restore
- ✅ **Projekt-spezifische** Backups

---

## 1. Backup erstellen

### Admin-Interface

```
admin/backup.php
```

**Backup-Typen:**
- **Vollständig** (default): Datenbank (SQL) + Dateien (ZIP) + optionale Archivierung
- **Nur Datenbank**: SQL-Dump aller Tabellen
- **Nur Dateien**: ZIP mit admin/, script/, assets/, nav/, views/

**Prozess:**
1. Wählen Sie den Backup-Typ
2. Klicken Sie "Backup jetzt erstellen"
3. Warten Sie auf Fertigstellung (zeigt Echtzeit-Fortschritt)
4. Backup wird in `/storage/backups/` gespeichert

**Backup-Dateien:**
- Datenbank: `backup_TIMESTAMP_db.sql`
- Dateien: `backup_TIMESTAMP_full.zip` (oder nur SQL für schnelle Backups)
- Format: Struktur bleibt gleich, aber verschiedene Dateitypen

---

## 2. Backups verwalten

### Backup-Liste anzeigen

Auf `admin/backup.php` werden alle erstellten Backups aufgelistet mit:
- 📊 Dateiname
- 📁 Größe
- 📅 Erstellungsdatum
- ⬇️ Download-Button
- 🗑️ Löschen-Button
- 📥 **Wiederherstellen-Button** (neu!)

---

## 3. Backups wiederherstellen

### A. Auf laufendem System

**URL:** `admin/restore.php` (Login erforderlich)

**Schritte:**
1. Gehen Sie zu **Admin → Backup-Verwaltung → Backups wiederherstellen**
2. Wählen Sie das gewünschte Backup aus
3. Bestätigen Sie mit ⚠️ Warnung
4. Kreuzen Sie an: "Ich verstehe, dass dies nicht rückgängig gemacht werden kann"
5. Klicken Sie "Ja, wiederherstellen"

**Was wird wiederhergestellt:**
- **SQL-Backup**: Ersetzt Datenbank-Tabellen und Daten
- **ZIP-Backup**: Ersetzt admin/, script/, assets/, nav/, views/ Verzeichnisse

**Sicherheit:**
- Fremdschlüssel während Restore deaktiviert
- Nach Restore wieder aktiviert
- Alle Änderungen werden geloggt

---

### B. Auf frisch installierten Systemen

**URL:** `setup_restore.php` (kein Login erforderlich)

**Szenario:**
- Neues System / Server
- DB.php konfiguriert, aber noch kein Code
- Willens, vorheriges Backup zu restore

**Schritte:**
1. Gehen Sie zu `https://example.com/menue/setup_restore.php`
2. Laden Sie ZIP-Backup hoch
3. Klicken Sie "Backup hochladen"
4. Wählen Sie Backup aus Liste
5. Klicken Sie "📥 Wiederherstellen"
6. Nach Restore:
   - Konfigurieren Sie `db.php` (falls noch nicht geschehen)
   - Rufen Sie `migrate.php` auf
   - Starten Sie mit `index.php`

**ZIP-Inhalt wird extrahiert nach:**
- `/admin/` → Admin-Panel Code
- `/script/` → Backend-Logik
- `/assets/` → CSS/JS
- `/nav/` → Navigation Templates
- `/views/` → (falls vorhanden)

---

## 4. Backup-Dateien Struktur

### SQL-Dump Format

```sql
-- Beispiel: db_backup_1234567890.sql
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `menu_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  ...
) ENGINE=InnoDB;

INSERT INTO `menu_projects` VALUES (1, 'Projekt 1', ...);

SET FOREIGN_KEY_CHECKS=1;
```

**Besonderheiten:**
- Automatische Datenbankauswahl
- Tabellen-Drops für Update-Sicherheit
- Fremdschlüssel-Handling
- UTF-8 Encoding

---

### ZIP-Struktur

```
backup_1234567890_full.zip
├── admin/
│   ├── backup.php
│   ├── restore.php
│   ├── dishes.php
│   └── ...
├── script/
│   ├── auth.php
│   ├── config.yaml
│   ├── lang.php
│   └── phpmailer/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
├── nav/
│   └── top_nav.php
└── views/ (falls vorhanden)
```

---

## 5. Technische Details

### Backend-Funktionen

#### `admin/backup_process.php`

**AJAX-Endpoint** für Backup-Erstellung.

**Aktionen:**
- `action=backup_full`: Vollständiges Backup
- `action=backup_database`: Nur DB
- `action=backup_files`: Nur Dateien
- `action=cleanup`: Temp-Dateien räumen

**Response:**

```json
{
  "success": true,
  "status": "completed",
  "backup_file": "backup_1234567890_full.sql",
  "size": "2.5 MB",
  "elapsed_time": 12.3
}
```

---

#### `admin/restore_process.php`

**AJAX-Endpoint** für Backup-Wiederherstellung.

**Aktionen:**
- `action=restore`: Backup aus POST-Daten restore

**Request:**

```
POST /admin/restore_process.php?action=restore
Content-Type: application/x-www-form-urlencoded

backup_file=backup_1234567890_full.sql&restore_type=database
```

**Response:**

```json
{
  "success": true,
  "message": "Restore completed",
  "statements_executed": 245
}
```

---

#### `setup_restore.php`

**Standalone-Seite** für Backup-Upload auf frischen Installationen.

**Features:**
- Datei-Upload mit Validierung (max 500MB)
- ZIP-Extraktion mit Pfad-Sicherheit
- Keine Login erforderlich
- Automatische Verzeichnis-Erstellung

---

## 6. Sicherheitsaspekte

### Validierungen

✅ **Datei-Typ Prüfung:** Nur `.sql` und `.zip`

✅ **Größe-Beschränkung:** Max 500MB

✅ **Pfad-Sicherheit:** 
- Keine `../` Sequenzen erlaubt
- Nur bestimmte Verzeichnisse extrahiert
- Symlinks ignoriert

✅ **Login-Schutz:**
- `admin/restore.php` erfordert Admin-Login
- `setup_restore.php` offen für Installations-Scenario

✅ **Logging:**
- Alle Restore-Aktionen werden geloggt
- Fehler werden dokumentiert

### Best Practices

⚠️ **Sicherheit:**
1. **Vor Restore:** Aktuelles Backup erstellen!
2. **Fremdschlüssel:** Werden automatisch deaktiviert
3. **Berechtigungen:** Dateien bekommen 0755 (Verzeichnisse) / Standard (Dateien)
4. **Backup-Ort:** `/storage/backups/` sollte `.htaccess` haben

---

## 7. Fehlerbehandlung

### Häufige Fehler

| Fehler | Ursache | Lösung |
|--------|--------|--------|
| "ZIP-Datei kann nicht geöffnet werden" | Beschädigte ZIP | Backup erneut erstellen |
| "Dateiename zu lang" | Pfad > 255 Zeichen | Verzeichnisse prüfen |
| "Fremdschlüssel-Fehler" | Constraint-Verletzung | Datenbank vor Restore prüfen |
| "Permissions denied" | Schreibrechte fehlen | `/storage/backups/` Rechte prüfen |
| "SQL Syntax Error" | Beschädigter SQL-Dump | Backup vor Restore validieren |

### Logs

Fehler werden geloggt in:
- `/storage/logs/error.log`
- `/storage/logs/app.log`

---

## 8. Workflow-Beispiele

### Szenario 1: Tägliche Backups

```
1. Jeden Abend: admin/backup.php → "Vollständiges Backup"
2. Nach 1 Woche: Altes Backup löschen
3. Speichern wichtiger Backups: Download-Button
```

### Szenario 2: Umzug auf neuen Server

```
1. Alt-Server: admin/backup.php → "Vollständiges Backup" → Download
2. Neu-Server: db.php konfigurieren
3. Neu-Server: setup_restore.php aufrufen
4. Backup hochladen → "Wiederherstellen"
5. migrate.php aufrufen
6. Starten!
```

### Szenario 3: Wiederherstellung nach Fehler

```
1. admin/backup.php → Liste anzeigen
2. Fehler vor X Stunden → Backup wählen
3. "📥 Wiederherstellen" klicken
4. System aktualisiert mit altem Stand
```

---

## 9. Dateiverzeichnis

### Backup-Speicherort

```
/storage/backups/
├── backup_1699564200_db.sql           (Nur DB)
├── backup_1699564200_full.zip         (ZIP mit Dateien)
├── backup_1699564400_db.sql           (Nächstes Backup)
└── backup_1699564400_database.sql     (Alternative Benennung)
```

### Temporäre Dateien (während Backup)

```
/storage/tmp/
├── backup_1234567890.sql              (Temp SQL)
├── backup_1234567890.zip              (Temp ZIP)
└── backup_process_1234567890.json     (Status-Datei)
```

---

## 10. API-Reference

### Backup-Status abrufen

```
GET /admin/backup_process.php?action=status&id=1699564200
```

**Response:**
```json
{
  "status": "in_progress",
  "progress": 45,
  "current_step": "Compressing files...",
  "elapsed_time": 23.5
}
```

### Restore-Status (ähnlich)

```
GET /admin/restore_process.php?action=status
```

---

## 11. v3.0 Spezifikationen

Backups enthalten:
- ✅ order_sessions Tabelle (neue v3.0)
- ✅ price Felder in dishes Tabelle
- ✅ show_prices Flag in projects
- ✅ Migrationen (inkl. v3.0 Migrationen)
- ✅ Alle Admin-Code Updates
- ✅ Neue restore.php Funktion

---

## Zusammenfassung

| Funktion | URL | Login | Beschreibung |
|----------|-----|-------|-------------|
| Backup erstellen | `admin/backup.php` | ✅ | Erstellt Backups |
| Backups verwalten | `admin/backup.php` | ✅ | Liste, Download, Löschen |
| Restore (Betrieb) | `admin/restore.php` | ✅ | Restore auf laufendem System |
| Restore (Neu) | `setup_restore.php` | ❌ | Restore auf Neu-Installation |

**v3.0 ist produktionsreif mit vollständigem Backup/Restore-System! 🚀**
