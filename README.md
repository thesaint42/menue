# 🍽️ Event Menue Order System (EMOS)

Ein vollständiges PHP-basiertes System zur Verwaltung von Menüauswahl für Gäste mit Admin-Dashboard, PDF-Export und E-Mail-Integration.

**Aktuelle Version:** 2.2.0 (22. Februar 2026)

## Features

✅ **Gast-Formular**
- PIN-basierter Zugang (statt direkter URL)
- **NEU v1.7:** Direkte Order-ID-Eingabe zur Bearbeitung bestehender Bestellungen
- **NEU v1.7:** Toggle zwischen PIN-Eingabe und Order-ID-Eingabe
- Persönliche Daten erfassen (Name, Email, Telefon)
- Unterscheidung: Einzelperson oder Familie/Haushalt
- Detaillierte Gast-Informationen pro Familienmitglied:
  - Name jeder Person
  - Typ: Erwachsen oder Kind
  - Alter des Kindes
  - Hochstuhl benötigt (ja/nein)
- Intuitive +/- Button zur Menümengenauswahl
- Automatische Bestätigungsemail an den Gast
- Admin erhält BCC-Kopie aller Bestellungen

✅ **Admin-Bereich**
- Projektmanagement (Veranstaltungen)
  - PIN-basierter Zugang
  - **NEU v1.7:** WYSIWYG-Editor (Quill.js) für Projektbeschreibungen
  - **NEU v1.7:** Rich-Text-Formatierung: Überschriften, Farben, Größen, Ausrichtung, Listen
  - **NEU v1.8:** Projekt-Backup (nur projektspezifische Daten) mit Dateiname `project_backup_<projectID>_<projectName>_yyyymmdd_hhmmss.sql`
  - QR-Code Generator und Download
  - E-Mail Einladung versenden
- Menüverwaltung (5 Kategorien: Vorspeise, Hauptspeise, Beilage, Salat, Nachspeise)
- Gästeübersicht mit Statistiken
- Bestellungshistorie
- **NEU v2.2.0:** Rollenbasierte Statistiken auf Dashboard (Systemadmin sieht alle, andere nur zugewiesene Projekte)
- **NEU v2.2.0:** Gästezähler in Projektliste mit order_people-Abfrage
- **NEU v1.7.1:** Optimierter PDF-Report mit Bestellübersicht
  - Hierarchische Darstellung: Bestellung → Person → Gerichte
  - Hochstuhl-Übersicht (HS-Angabe)
  - Detaillierte Statistiken (Anzahl Bestellungen, Personen, Hochstühle)
  - Korrekte Darstellung aller Personen mit fettgedruckten Namen
  - Automatischer Seitenumbruch bei langen Bestellungen
- PDF-Export der Gästeübersicht
- SMTP Mail-Konfiguration mit Test-Funktion
- Datenbankmigrationen für Versionsupdates

✅ **Datenbankschema**
- Flexible Tabellenpräfixe (z.B. `menu_`)
- Optimierte Datenstruktur für Projekte, Menüs, Gäste und Bestellungen
- Familienmitglieder-Tabelle mit erweiterten Informationen
- Zugangs-PIN System
- Audit Logging für Admin-Aktionen
- Mail Logging für Versandhistorie
- Migration Tracking für Versionsupdates

✅ **Sicherheit**
- Passwort-Hashing mit PHP's Password Hashing API
- Session-Management
- SQL-Injection Protection (Prepared Statements)
- **NEU v1.7:** HTML-Sanitization für Projektbeschreibungen
- **NEU v1.7:** XSS-Schutz mit Whitelist-Tags und Style-Attributfilterung
- CSRF Protection (Optional)

✅ **Mehrsprachigkeit**
- Deutsche und englische Sprachdateien
- Einfaches Translator-System (_t() Funktion)

---

## Installation

### 1. Voraussetzungen
- PHP 8.0+
- MySQL 5.7+
- PDO MySQL Extension
- Mbstring Extension

### 2. Installation starten

Öffnen Sie im Browser:
```
http://localhost/install.php
```

Folgen Sie den Installationsschritten:
1. **Umgebungsprüfung** - System überprüft Anforderungen
2. **Datenbank-Verbindung** - Geben Sie DB-Zugangsdaten und Tabellenpräfix ein
3. **Admin-Benutzer** - Erstellen Sie den ersten Administrator
4. **SMTP-Konfiguration** - Richten Sie Mail-Versand ein

### 3. Nach der Installation

Admin-Login:
```
http://localhost/admin/login.php
```

Gast-Formular:
```
http://localhost/index.php?project=1
```

---

## Projektstruktur

```
11_Menüwahl/
├── index.php                 # Gast-Formular
├── install.php              # Installations-Assistent
├── migrate.php              # Datenbank-Migrationen
├── db.php                   # Zentrale DB-Verbindung
├── datenschutz.php          # Datenschutzerklärung
├── impressum.php            # Impressum
├── vvt.php                  # Verfahrensverzeichnis
├── deploy.sh                # FTPS-Deployment-Script
├── BACKUP_RESTORE_GUIDE.md  # Backup- & Restore-Dokumentation
├── UPGRADE.md               # Upgrade-Anleitung
├── UPGRADE_CHECKLIST.md     # Upgrade-Checkliste
├── DEPLOY_LOCAL.md          # Lokales Deployment
├── script/
│   ├── config.yaml          # Konfigurationsdatei (wird bei Installation erstellt)
│   ├── auth.php             # Authentifizierungsfunktionen
│   ├── mailer.php           # PHPMailer Integration
│   ├── schema.php           # Datenbankschema
│   ├── lang.php             # Sprachfunktionen
│   ├── lang/
│   │   ├── de.php           # Deutsche Übersetzungen
│   │   └── en.php           # English Translations
│   ├── phpmailer/           # PHPMailer Library
│   └── tcpdf/               # TCPDF Library
├── admin/
│   ├── login.php            # Admin Login
│   ├── admin.php            # Dashboard
│   ├── backup.php           # Backup-Verwaltung
│   ├── backup_process.php   # Backup-AJAX-Endpoint
│   ├── restore.php          # Restore-UI
│   ├── restore_process.php  # Restore-AJAX-Endpoint
│   ├── projects.php         # Projektenverwaltung (mit WYSIWYG-Editor)
│   ├── dishes.php           # Menüverwaltung
│   ├── guests.php           # Gästeübersicht
│   ├── orders.php           # Bestellungsübersicht
│   ├── export_pdf.php       # PDF Export
│   ├── settings_mail.php    # Mail-Einstellungen
│   ├── profile.php          # Admin Profil
│   └── logout.php           # Logout
├── views/
│   ├── pin_entry.php        # PIN/Order-ID Eingabe (v1.7)
│   ├── order_start.php      # Projekt-Willkommensseite mit Rich-Text-Beschreibung
│   ├── order_form.php       # Bestellformular
│   └── order_success.php    # Erfolgsbestätigung
├── nav/
│   └── top_nav.php          # Hauptnavigation
├── assets/
│   ├── css/
│   │   └── style.css        # Hauptstyles
│   └── js/
├── tools/                   # Entwickler-Tools und Scripts
├── storage/
│   ├── backups/             # Backup-Dateien
│   ├── logs/                # Log-Dateien
│   ├── pdfs/                # Exportierte PDFs
│   └── tmp/                 # Temporäre Dateien
└── img/                     # Logo und Bilder
```

---

## Datenbankschema

### Haupttabellen

**`menu_roles`** - Benutzerrollen
```sql
id, name, description
```

**`menu_users`** - Administrator-Benutzer
```sql
id, firstname, lastname, email, password_hash, role_id, is_active, created_at
```

**`menu_password_resets`** - Passwort-Reset-Tokens
```sql
id, email, token, expires_at
```

**`menu_projects`** - Veranstaltungen/Projekte
```sql
id, name, description, location, contact_person, contact_phone, contact_email, 
max_guests, admin_email, access_pin, is_active, show_prices, created_by, created_at
```
*NEU v1.7: description unterstützt Rich-Text-HTML*

**`menu_menu_categories`** - Menü-Kategorien
```sql
id, name, sort_order
```

**`menu_dishes`** - Menü-Gerichte
```sql
id, project_id, category_id, name, description, price, sort_order, is_active, created_at
```

**`menu_order_sessions`** - Bestellvorgänge mit eindeutiger Order-ID
```sql
id, order_id (36 Zeichen UUID), project_id, email, created_at
```
*NEU v1.7: Ermöglicht Order-ID-basierte Bestellbearbeitung*

**`menu_guests`** - Gäste mit Bestellinformationen
```sql
id, project_id, firstname, lastname, email, phone, guest_type (individual|family), 
family_size, person_type (adult|child), child_age, highchair_needed, 
order_status (pending|confirmed|cancelled), created_at
```

**`menu_family_members`** - Familienmitglieder bei Familienbestellungen
```sql
id, guest_id, name, member_type (adult|child), child_age, highchair_needed, created_at
```

**`menu_orders`** - Einzelne Menüauswahl pro Person und Gang
```sql
id, order_id (UUID), person_id, dish_id, category_id, created_at
```

**`menu_order_guest_data`** - Zwischenspeicher für Gastdaten pro Order (optional)
```sql
order_id, project_id, email, firstname, lastname, phone, phone_raw, guest_type, person_type, child_age, highchair_needed
```

**`menu_order_people`** - Personen pro Order (NEU v2.2.0 - Primary Guest Source)
```sql
order_id (UUID), person_index, name, person_type, child_age, highchair_needed
```
*NEU v2.2.0: Wird für Gästezähler in Dashboard und Projektlisten verwendet (COUNT = primäre Quelle)*

**`menu_smtp_config`** - SMTP Server-Konfiguration
```sql
id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, sender_email, sender_name
```

**`menu_mail_logs`** - Versandhistorie
```sql
id, sender, recipient, subject, sent_at, status (success|failed), error_message
```

**`menu_logs`** - Audit Log (Admin-Aktionen)
```sql
id, user_id, action, details, ip_address, created_at
```

---

## Konfiguration

Die `script/config.yaml` wird während der Installation automatisch erstellt:

```yaml
database:
  host: "localhost"
  db_name: "menuselection"
  user: "root"
  pass: ""
  prefix: "menu_"

mail:
  admin_email: "admin@example.com"
  sender_name: "Event Menue Order System (EMOS)"

system:
  language: "de"
  timezone: "Europe/Berlin"
```

---

## Verwendung

### Gast-Menüauswahl

**Variante 1: Mit PIN (Neugast)**
1. Gast öffnet: `index.php`
2. Gibt 6-stellige PIN ein, um auf das Projekt zuzugreifen
3. Füllt Persönliche Daten aus
4. Wählt Menüs mit +/- Buttons
5. Submittet das Formular
6. Erhält Bestätigungsemail mit Order-ID

**Variante 2: Mit Order-ID (Bestellung bearbeiten)**
1. Gast öffnet: `index.php`
2. Klickt auf "📝 Bestellung mit Order-ID bearbeiten"
3. Gibt Order-ID ein (z.B. `12345-67890`)
4. Bestehende Bestellung wird geladen
5. Kann Änderungen vornehmen und speichern

### Admin-Funktionen

**Projekte erstellen:**
- Admin-Bereich → Projekte → Neues Projekt
- Definiert: Name, Ort, Max. Gäste, Kontaktdaten, Admin-Email
- **NEU:** Rich-Text-Beschreibung mit WYSIWYG-Editor:
  - Überschriften (H1, H2, H3)
  - Textgrößen (klein, normal, groß, riesig)
  - Farben und Hintergrundfarben
  - Textausrichtung (links, zentriert, rechts, Blocksatz)
  - Fett, Kursiv, Unterstrichen
  - Aufzählungen und nummerierte Listen
  - Links

**Menüs verwalten:**
- Admin-Bereich → Menüs
- Fügt Gerichte zu Kategorien hinzu
- Sortierung möglich

**Gäste einsehen:**
- Admin-Bereich → Gäste
- Übersicht aller Anmeldungen mit Bestellungen
- Filter nach Projekt

**Projekt-Backup erstellen (v1.8):**
- Admin-Bereich → Projekte → Bearbeiten → „Projekt sichern“
- Ergebnis: eine SQL-Datei je Projekt mit projektspezifischen Tabellen/Zeilen

**PDF exportieren:**
- Admin-Bereich → PDF Export
- Gästeübersicht als PDF oder CSV

---

## Email-Integration

### Konfiguration

1. Im Admin-Bereich: Mail-Einstellungen
2. Geben Sie SMTP-Daten ein (z.B. von Ihrem Mail-Provider)
3. Klicken Sie "Test-Email versenden"

### Beispiel SMTP-Konfigurationen

**Gmail:**
- Host: `smtp.gmail.com`
- Port: 587
- Benutzername: `your-email@gmail.com`
- Passwort: App-Passwort (kein normales Passwort!)
- Verschlüsselung: TLS

**Strato:**
- Host: `smtp.strato.de`
- Port: 587
- Verschlüsselung: TLS

**1&1 IONOS:**
- Host: `smtp.ionos.de`
- Port: 587
- Verschlüsselung: TLS

### Email-Bestätigungen

**Gast-Bestätigungen (automatisch):**
- Enthalten Order-ID für spätere Bearbeitung
- Admin erhält BCC-Kopie
- Versandhistorie in Mail-Logs

**NEU v1.7:** Order-ID wird prominent in der Bestätigungs-Email angezeigt und kann direkt auf index.php zur Bearbeitung verwendet werden.

---

## Mehrsprachigkeit

### Sprache setzen

```php
setLanguage('de');  // Deutsch
setLanguage('en');  // English
```

### Übersetzungen verwenden

```php
echo _t('form_firstname');  // "Vorname" (DE) oder "First Name" (EN)
```

### Neue Übersetzung hinzufügen

1. Öffnen Sie `script/lang/de.php` und `script/lang/en.php`
2. Fügen Sie neuen Key ein:
```php
'my_key' => 'Mein Text',
```

---

## API & Hooks

### Gast-Bestellung verarbeiten

```php
require_once 'script/mailer.php';
sendOrderConfirmation($pdo, $prefix, $guest_id);
```

### Order-ID Bestellung laden

```php
// Bestellung über Order-ID laden (v1.7)
$order_id = '12345-67890';
$existing_order = load_order_by_id($pdo, $prefix, $order_id);
if ($existing_order) {
    $project_id = $existing_order['project_id'];
    // Projekt laden und fortfahren
}
```

### HTML-Sanitization für Rich-Text (v1.7)

```php
// Projektbeschreibung sanitizen
function sanitize_project_description($html) {
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><span><h1><h2><h3>';
    $clean = strip_tags((string)$html, $allowed);
    // Event Handler entfernen
    $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $clean);
    // javascript: URLs entfernen
    $clean = preg_replace('/javascript:/i', '', $clean);
    return trim($clean);
}
```

### Log-Eintrag erstellen

```php
logAction($pdo, $prefix, 'action_name', 'Details');
```

### Projekt-Backup via AJAX (v1.8)

```php
// POST /admin/backup_process.php?action=execute
// backup_type=project&project_id=3
```

### Sprache ändern

```php
setLanguage('en');
```

---

## Troubleshooting

**"Datenbankfehler" bei der Installation:**
- Überprüfen Sie DB-Host, Benutzer, Passwort
- Stellen Sie sicher, dass der DB-Benutzer Datenbanken erstellen darf
- Bei Migration: Verwenden Sie `migrate.php` für Versionsupdates

**Emails werden nicht versendet:**
- Überprüfen Sie SMTP-Konfiguration
- Klicken Sie auf "Test-Email versenden"
- Überprüfen Sie Mail-Logs im Admin-Bereich
- Bei Gmail: Verwenden Sie App-Passwort, kein normales Passwort

**Installation wird nicht angezeigt:**
- Überprüfen Sie, dass `script/` Schreibrechte hat
- Überprüfen Sie PHP Error Logs
- install.php wird nach erfolgreicher Installation automatisch ausgeblendet

**WYSIWYG-Editor zeigt keine Formatierung (v1.7):**
- Quill CSS muss eingebunden sein: `<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">`
- Browser-Cache leeren
- JavaScript-Konsole auf Fehler prüfen

**Order-ID funktioniert nicht (v1.7):**
- Format prüfen: `12345-67890` (5 Ziffern - 5 Ziffern)
- Order-ID aus Bestätigungs-Email kopieren
- Sicherstellen, dass Bestellung existiert

**Formatierung wird nicht gespeichert (v1.7):**
- Überprüfen Sie, dass die sanitize_project_description() Funktion die benötigten Tags erlaubt
- Prüfen Sie, ob HTML in der Datenbank gespeichert wird (nicht nur Plain Text)

**Projekt-Backup erstellt falsche Dateien (v1.8):**
- Prüfen Sie, ob `admin/backup_process.php` auf dem Server aktualisiert ist
- Cache/OPcache leeren
- Stellen Sie sicher, dass die Tabellen `order_sessions`, `orders`, optional `order_guest_data`/`order_people` vorhanden sind

---

## Sicherheit

- ✅ Passwörter mit `password_hash()` verschlüsselt
- ✅ Prepared Statements gegen SQL-Injection
- ✅ Session-basierte Authentifizierung
- ✅ Admin-Funktionen nur für authorisierte Benutzer
- ✅ **NEU v1.7:** HTML-Sanitization für Rich-Text-Content
- ✅ **NEU v1.7:** Whitelist-basierte Tag-Filterung (nur sichere HTML-Tags erlaubt)
- ✅ **NEU v1.7:** XSS-Schutz durch Entfernung von Event-Handlern und JavaScript-URLs
- ✅ **NEU v1.7:** Style-Attribut-Filterung (nur erlaubte CSS-Properties)
- ✅ Unique Order-IDs mit UUID-Format
- ⚠️ SSL/HTTPS **dringend** empfohlen für Production (Schutz der Order-IDs)
- ⚠️ Regelmäßige Backups durchführen
- ⚠️ `install.php` nach Installation löschen oder Zugriff einschränken

---

## Changelog

### Version 2.2.0 (22. Februar 2026)

**Dashboard & Statistics - Rollenbasierte Berechtigungen:**
- ✅ **Rollenbasierte Dashboard-Statistiken:** Systemadmin sieht alle Projekte, andere nur zugewiesene
- ✅ **Bestellstatistiken-Queries:** Optimiert mit ORDER BY für Zuverlässigkeit
- ✅ **Gästecount mit `order_people`:** Neue Abfrage nutzt `order_people` + `order_sessions` JOIN
- ✅ **Bestellcount mit `orders`:** COUNT(*) von orders-Tabelle mit Project-Filterung
- ✅ **Permission-aware Recent Projects:** Zeigt nur Projekte, auf die der User Zugriff hat
- ✅ **Per-Project Guest Count:** Individuelle Gästezahlen pro Projekt in Projektliste

**Navigation & UI Updates:**
- ✅ **Button "Neue Menükategorie":** Umbenennung von "Mail-Einstellungen" (führt zu menu_categories.php)
- ✅ **Button "Reporting":** Umbenennung von "PDF exportieren" (führt zu reports.php)
- ✅ **Neue Icons:** + Symbol für "Neue Menükategorie", 📊 für "Reporting"
- ✅ **Button-Reihenfolge:** Projekt > Kategorie > Menü in "Neue Aktion"; Projekte > Gäste > Reporting in "Verwaltung"
- ✅ **Konsistente Spacing:** Einheitliche Abstände und Layouting

**Code Quality:**
- ✅ **Debug Output removed:** Keine debug_info / debug_queries-Arrays mehr
- ✅ **Error Handling:** Try-catch Blöcke für sichere Fehlerbehandlung
- ✅ **Try-catch für fetch():** Schutz vor Array-Access auf false
- ✅ **Clean SQL Queries:** Vereinfachte, produktionsreife Queries
- ✅ **Safe NULL-Checks:** Alle Fetch-Ergebnisse mit isset() geprüft

**Database Query Updates:**
- ✅ **Guest Count Query:**
  ```sql
  SELECT COUNT(*) FROM menu_order_people op 
  INNER JOIN menu_order_sessions os ON op.order_id = os.order_id 
  WHERE os.project_id IN (?, ?, ...)
  ```
- ✅ **Order Count Query:**
  ```sql
  SELECT COUNT(*) FROM menu_orders 
  WHERE order_id IN (
    SELECT order_id FROM menu_order_sessions WHERE project_id IN (?, ?, ...)
  )
  ```

---

### Version 2.1.0 (22. Februar 2026)

**UI/UX Optimierungen - Guests/Orders Pages:**
- ✅ **guests.php & orders.php:** Responsive Design für Mobile und Desktop
- ✅ Konvertierung von Tabellen-Layout zu Card-Layout
- ✅ **Kopfzeile (Mobile):** Zweizeilig mit Bestellnummer • Besteller-Name | Delete-Button (Zeile 1) + Email | Datum | Personenanzahl | Hochstühle (Zeile 2)
- ✅ **Kopfzeile (Desktop):** Einzeilig mit allen Informationen
- ✅ **Badges:** Einheitliche Breite (mobile: 95px, desktop: 140px) für Kind/Erwachsener
- ✅ **Responsive Text:** "Kind (11 Jahre)" auf Desktop, "Kind (11)" auf Mobile
- ✅ **Hochstühle:** Dynamisch Singular/Plural - "1 Hochstuhl" / "2+ Hochstühle"
- ✅ **Mobile Kopfzeile:** Nur Symbol + Zahl für Hochstühle, kein Text
- ✅ **Buttons:** Einheitliche Größe (min-width 110px) mit Symbol-only auf Mobile (≤576px)
- ✅ **Navigation:** Menü-Labels "Gäste" → "Gästeübersicht", "Bestellungen" → "Bestellübersicht"
- ✅ **Überschriften:** Konsistente `<h1>` für beide "Gästeübersicht" und "Bestellübersicht"
- ✅ **Responsive Breakpoints:** 576px (Mobile), 768px (Tablet), 1024px (Desktop)
- ✅ Non-Breaking Space (`&nbsp;`) für zuverlässige Spacing zwischen Kind und Altersangabe

---

### Version 2.0.0 (22. Februar 2026)

**Dokumentation:**
- Vollständige Aktualisierung der Projektdokumentation
- Überarbeitete Backup/Restore-Dokumentation
- Konsolidierte Troubleshooting- und Support-Sektionen

---

### Version 1.8.0 (22. Februar 2026)

**Neue Features:**
- Projekt-Backup (projektspezifischer SQL-Export)
- Export über `order_id`-basierte Tabellenstruktur

---

### Version 1.7.1 (21. Februar 2026)

**Verbesserungen:**
- Optimierter PDF-Report mit Bestellübersicht
- Korrekte Personen-Darstellung und Statistiken

---

### Version 1.7.0 (21. Februar 2026)

**Neue Features:**
- 🎨 WYSIWYG-Editor (Quill.js) für Projektbeschreibungen im Admin-Bereich
- 📝 Rich-Text-Formatierung mit allen gängigen Optionen:
  - Überschriften (H1, H2, H3)
  - Textgrößen (klein, normal, groß, riesig)
  - Farben und Hintergrundfarben
  - Textausrichtung (links, zentriert, rechts, Blocksatz)
  - Fett, Kursiv, Unterstrichen
  - Aufzählungen und nummerierte Listen
  - Links
- 🔑 Direkte Order-ID-Eingabe für Bestellbearbeitung ohne PIN
- 🔄 Toggle zwischen PIN-Eingabe und Order-ID-Eingabe auf Startseite

**Verbesserungen:**
- Verbesserte HTML-Sanitization mit Whitelist-Tags für sichere Inhaltsanzeige
- Optimierte Zeilenabstände und Formatierung auf der Frontend-Ansicht
- Bedingte Anzeige des Install-Links (nur wenn install.php existiert)

**Sicherheit:**
- XSS-Schutz für Rich-Content mit Style-Attributfilterung
- Regex-basierte Event-Handler-Entfernung

---

## Berechtigungssystem (v2.2.0+)

Das System verwendet ein feature-basiertes Berechtigungssystem mit Read- und Write-Zugriff:

### Feature-zu-Seite Mapping

| Feature | Checkbox-Label | Admin-Seite | Access-Check | Typ |
|---------|----------------|-------------|--------------|-----|
| **Projekt-Features** | | | | |
| `dashboard` | Dashboard | admin/admin.php | requireMenuAccess | Read |
| `menu_categories_read` | Menükategorien lesen | admin/menu_categories.php | requireMenuAccess | Read |
| `menu_categories_write` | Menükategorien schreiben | admin/menu_categories.php | Edit/Delete | Write |
| `projects_read` | Projekte lesen | admin/projects.php | requireMenuAccess | Read |
| `projects_write` | Projekte schreiben | admin/projects.php | POST-Operationen | Write |
| `menus_read` | Menüs lesen | admin/dishes.php | requireMenuAccess | Read |
| `menus_write` | Menüs schreiben | admin/dishes.php | Edit/Delete | Write |
| `guests_read` | Gästeübersicht lesen | admin/guests.php | requireMenuAccess | Read |
| `guests_write` | Gästeübersicht schreiben | admin/guests.php | Edit/Delete | Write |
| `orders_read` | Bestellübersicht lesen | admin/orders.php | requireMenuAccess | Read |
| `orders_write` | Bestellübersicht schreiben | admin/orders.php | Edit/Delete | Write |
| `reporting` | Reporting | admin/reports.php, export_pdf.php | requireMenuAccess | Read |
| **Admin-Features** | | | | |
| `users` | Benutzer verwalten | admin/users.php | requireMenuAccess | Admin |
| `roles` | Rollen verwalten | admin/roles.php | requireMenuAccess | Admin |
| `settings_mail` | Mail Setup | admin/settings_mail.php, test_mail.php | requireMenuAccess | Admin |
| `update` | Update durchführen | migrate.php | requireMenuAccess | Admin |
| `backup_create` | Backup erstellen | admin/backup_process.php | requireMenuAccess | Admin |
| `backup_import` | Backup importieren | admin/backup.php, restore.php | requireMenuAccess | Admin |

**Standardrollen:**
- **Systemadmin (Rolle 1)**: Alle Features inkl. Benutzerverwaltung, Backup/Restore, System-Updates
- **Projektadmin (Rolle 2)**: Alle Read/Write-Features für zugewiesene Projekte (kein Admin-Zugriff)
- **Reporter (Rolle 3)**: Alle Read-Features (dashboard, projects_read, menu_categories_read, menus_read, guests_read, orders_read, reporting)

**Benutzerdefinierte Rollen:**
- Können beliebige Feature-Kombinationen aus der obigen Liste haben
- Features werden in `roles.php` per Checkbox ausgewählt und in `role_menu_access` gespeichert
- Jede Admin-Seite prüft automatisch mit `requireMenuAccess()` auf das entsprechende Feature
- Admin-Features (users, roles, settings_mail, update, backup_*) sind nur für Systemadmins vorgesehen

**Zugriffskontrolle:**
- Jede Admin-Seite prüft beim Aufruf mit `requireMenuAccess()` auf das entsprechende Feature
- Write-Operationen (POST, DELETE) prüfen zusätzlich auf `*_write` Features mit `$has_write_access`
- Read-only User sehen keine Edit/Delete-Buttons

---

## Support & Dokumentation

Die Anwendung wird unterstützt von:
- `db.php` - Zentrale Datenbankverbindung
- `script/auth.php` - Benutzer-Authentifizierung
- `script/mailer.php` - Email-Versand
- `nav/top_nav.php` - Konsistente Navigation

**Dokumentation:**
- `BACKUP_RESTORE_GUIDE.md` - Backup/Restore-Workflow
- `UPGRADE.md` - Upgrade-Anleitung
- `UPGRADE_CHECKLIST.md` - Checkliste für Releases
- `DEPLOY_LOCAL.md` - Deployment-Workflow

---

**Version:** 2.1.0
**Lizenz:** Proprietary  
**Datum:** 22. Februar 2026
