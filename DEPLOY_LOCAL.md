# 🚀 Lokales Deployment System

## Übersicht

Statt über GitHub Actions (mit FTP Connection-Limits) wird direkt lokal via FTPS deployed.

**Vorteile:**
- ✅ Keine GitHub Actions Workflows nötig
- ✅ Direkte FTPS-Verbindung (FTP über TLS)
- ✅ Passive Mode für Firewall-Kompatibilität
- ✅ Begrenzt auf 3 Verbindungen (von 8 verfügbaren)
- ✅ Nur geänderte Dateien werden deployed
- ✅ Geschützte Dateien automatisch übersprungen

## Setup

### 1. Voraussetzungen

```bash
# lftp installieren (für macOS)
brew install lftp

# Oder: Prüfe ob lftp vorhanden ist
lftp --version
```

### 2. Credentials speichern

Die `.deploy.env` Datei enthält deine FTP-Credentials:

```bash
DEPLOY_HOST="wp1038982.server-he.de"
DEPLOY_USER="ftp1038982-menue"
DEPLOY_PASSWORD="rozce1-Gucnud-pyxzak"
DEPLOY_PATH="/"
```

**⚠️ WICHTIG:** `.deploy.env` ist in `.gitignore` und wird **NIEMALS** zu Git hinzugefügt!

## Deployment durchführen

### Option 1: Alle geänderten Dateien deployen

```bash
cd /Users/olaf/Documents/10_Development/11_Menüwahl
./deploy.sh
```

Das Script erkennt automatisch alle Dateien, die sich seit dem letzten Commit geändert haben.

### Option 2: Spezifische Dateien deployen

```bash
./deploy.sh admin/restore.php admin/restore_process.php setup_restore.php
```

### Option 3: Ganzes Verzeichnis

```bash
./deploy.sh admin/
./deploy.sh script/
./deploy.sh assets/
```

## Geschützte Dateien

Diese Dateien werden **AUTOMATISCH ÜBERSPRUNGEN**:

- `db.php` - Datenbank-Konfiguration (lokal!)
- `install.php` - Installation (nicht deployen)
- `script/config.yaml` - Server-Konfiguration
- `storage/*` - Logs, PDFs, Temp-Dateien
- `.deploy.env` - Credentials (niemals!)
- `*.md` - Dokumentation

## Beispiel-Workflow

```bash
# 1. Code ändern und committen
git add admin/backup.php
git commit -m "Fix: Some improvement"
git push origin main

# 2. Sofort deployen (ohne GitHub Actions)
./deploy.sh

# 3. Oder spezifische Datei
./deploy.sh admin/backup.php
```

## Output Beispiel

```
═══════════════════════════════════════════
🚀 EMOS Deployment via FTPS
═══════════════════════════════════════════

📊 Deployment-Konfiguration:
   Server: wp1038982.server-he.de
   User: ftp1038982-menue
   Root: /
   Protokoll: ftps (Passive: true)
   Max Connections: 3/8

📁 Modus: Alle veränderten Dateien

📋 Dateien zum Deployen:

   ✓ admin/restore.php
   ✓ admin/restore_process.php
   ⊘ db.php (geschützt)

Connecting to FTPS...

✅ Deployment erfolgreich!

📊 Statistik:
   Deployed: 2 Dateien
   Übersprungen: 1 Datei
```

## Fehlerbehebung

### ❌ "lftp: command not found"

```bash
# macOS
brew install lftp

# Linux (Ubuntu/Debian)
sudo apt-get install lftp

# Linux (Fedora/RHEL)
sudo yum install lftp
```

### ❌ "530 Login incorrect"

- Überprüfe Credentials in `.deploy.env`
- Stelle sicher, dass FTP-User aktiv ist

### ❌ "max-retries exceeded"

- Zu viele Verbindungen auf dem Server
- Warte 1-2 Minuten
- Script begrenzt auf 3 Verbindungen (von 8) - sollte ok sein

### ❌ "425 Security data connection error"

- Firewall/Proxy blockiert passive FTP
- Versuche Firewall zu checken oder nutze andere WiFi

## GitHub Integration

GitHub wird weiterhin für Versionskontrolle genutzt:

```bash
# Wie immer: Code ändern, committen, pushen
git add .
git commit -m "Feature: Add XYZ"
git push origin main

# GitHub Actions wird NICHT mehr für Deployment genutzt
# (kann entfernt oder deaktiviert werden)

# Stattdessen: Lokal deployen
./deploy.sh
```

## Tipps & Best Practices

### ✅ Nach wichtigen Änderungen

```bash
# Backup vor Deploy
./deploy.sh admin/backup.php

# Dann deployen
git add -A && git commit -m "Update: XYZ" && git push
./deploy.sh
```

### ✅ Nur bestimmte Dateien

```bash
# Nicht alles deployen - nur was geändert wurde
./deploy.sh  # Automatisch geänderte Files erkennen

# Oder explizit
./deploy.sh admin/restore.php script/lang.php
```

### ✅ Rollback Falls Fehler

```bash
# Wenn etwas schiefgeht: Alten Commit auschecken
git checkout HEAD~1 admin/restore.php

# Deployen
./deploy.sh admin/restore.php

# Oder von Backup wiederherstellen
# (falls vorhanden: admin/restore.php → restore.php)
```

## Sicherheit

⚠️ **Wichtig:**

- `.deploy.env` ist im `.gitignore` - wird nicht gepusht
- Passwort ist nur lokal gespeichert
- FTPS mit TLS verschlüsselt die Verbindung
- Nutze Strong Passwörter für FTP-Accounts

## Automation (Optional)

Wenn du automatisches Deployment nach Commits möchtest, kannst du einen Git Hook nutzen:

```bash
# .git/hooks/post-commit
#!/bin/bash
./deploy.sh
```

Aber Vorsicht - das deployut JEDEN Commit automatisch!

---

**Viel Erfolg beim Deployen!** 🚀
