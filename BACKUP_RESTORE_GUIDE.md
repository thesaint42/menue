# Backup & Restore System - EMOS v1.8

## Übersicht

Das EMOS v1.8 System enthält ein vollständiges Backup- und Wiederherstellungs-System, das mehrere Szenarien unterstützt:

- ✅ **Automatische Backups** erstellen
- ✅ **Datenbank + Dateien** sichern
- ✅ **Beliebige Backups wiederherstellen**
- ✅ **Auf frisch installierten Systemen** restore
- ✅ **Projekt-spezifische** Backups (nur projektrelevante Daten)

---

## 1. Backup erstellen

### Admin-Interface

```
admin/backup.php
```

**Backup-Typen:**
- **Vollständig** (default): Datenbank (SQL) + Dateien (ZIP)
- **Nur Datenbank**: SQL-Dump aller Tabellen
- **Nur Dateien**: ZIP mit admin/, script/, assets/, nav/, views/

**Prozess:**
1. Wählen Sie den Backup-Typ
2. Klicken Sie "Backup jetzt erstellen"
3. Warten Sie auf Fertigstellung (Echtzeit-Fortschritt)
4. Backup wird in `/storage/backups/` gespeichert

### Projekt-Backup (projektspezifisch)

Projekt-Backups werden über die Projektverwaltung erstellt:
- **Admin → Projekte → Bearbeiten → Projekt sichern**
- Oder bei deaktivierten Projekten über **Backup**

---

## 2. Backup-Dateien

**Dateinamen:**
- Datenbank: `db_backup_YYYY-MM-DD_HH-MM-SS.sql`
- Dateien: `files_backup_YYYY-MM-DD_HH-MM-SS.zip`
- Projekt: `project_backup_<projectID>_<projectName>_yyyymmdd_hhmmss.sql`

---

## 3. Backups verwalten

Auf `admin/backup.php` werden alle Backups aufgelistet mit:
- 📊 Dateiname
- 📁 Größe
- 📅 Erstellungsdatum
- ⬇️ Download-Button
- 🗑️ Löschen-Button
- 📥 **Wiederherstellen-Button**

---

## 4. Backups wiederherstellen

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

---

### B. Auf frisch installierten Systemen

**URL:** `setup_restore.php` (kein Login erforderlich)

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

---

## 5. Backup-Dateien Struktur

### SQL-Dump Format

```sql
-- Beispiel: db_backup_2026-02-22_12-30-00.sql
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `menu_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  ...
) ENGINE=InnoDB;

INSERT INTO `menu_projects` VALUES (1, 'Projekt 1', ...);

SET FOREIGN_KEY_CHECKS=1;
```

### ZIP-Struktur

```
files_backup_2026-02-22_12-30-00.zip
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
└── views/
```

### Projekt-Backup Inhalt

Projekt-Backups enthalten nur projektrelevante Tabellen/Zeilen:
- `projects`
- `dishes`
- `guests`
- `family_members`
- `order_sessions`
- `order_guest_data`
- `order_people`
- `orders`

---

## 6. Technische Details

### `admin/backup_process.php`

**AJAX-Endpoint** für Backup-Erstellung.

**Aktionen:**
- `action=start`: Backup-Status initialisieren
- `action=execute`: Backup ausführen (Typ via `backup_type`)
- `action=status`: Status abfragen (Progress)
- `action=cleanup`: Temp-Dateien räumen

**Request (Beispiel, Projekt-Backup):**

```
POST /admin/backup_process.php?action=execute
Content-Type: application/x-www-form-urlencoded

backup_type=project&project_id=3
```

**Response (Beispiel):**

```json
{
  "status": "completed",
  "files_created": ["project_backup_3_Test_20260222_123000.sql"],
  "duration": 12
}
```

---

## 7. Sicherheitsaspekte

✅ **Datei-Typ Prüfung:** Nur `.sql` und `.zip`

✅ **Pfad-Sicherheit:**
- Keine `../` Sequenzen
- Nur definierte Verzeichnisse bei ZIP-Extraktion

✅ **Login-Schutz:**
- `admin/restore.php` erfordert Admin-Login
- `setup_restore.php` offen für Installations-Szenario

---

## 8. Workflow-Beispiele

### Szenario 1: Tägliche Backups

```
1. Jeden Abend: admin/backup.php → "Vollständiges Backup"
2. Nach 1 Woche: Altes Backup löschen
3. Wichtige Backups: Download-Button
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
├── db_backup_2026-02-22_12-30-00.sql
├── files_backup_2026-02-22_12-30-00.zip
└── project_backup_3_Test_20260222_123000.sql
```

### Temporäre Dateien (während Backup)

```
/storage/tmp/
└── backup_status_<hash>.json
```

---

## 10. Zusammenfassung

| Funktion | URL | Login | Beschreibung |
|----------|-----|-------|-------------|
| Backup erstellen | `admin/backup.php` | ✅ | Erstellt Backups |
| Backups verwalten | `admin/backup.php` | ✅ | Liste, Download, Löschen |
| Restore (Betrieb) | `admin/restore.php` | ✅ | Restore auf laufendem System |
| Restore (Neu) | `setup_restore.php` | ❌ | Restore auf Neu-Installation |
