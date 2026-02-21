# 🍽️ Event Menue Order System (EMOS)

Ein vollständiges PHP-basiertes System zur Verwaltung von Menüauswahl für Gäste mit Admin-Dashboard, PDF-Export und E-Mail-Integration.

**Aktuelle Version:** 1.7.0

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
  - QR-Code Generator und Download
  - E-Mail Einladung versenden
- Menüverwaltung (5 Kategorien: Vorspeise, Hauptspeise, Beilage, Salat, Nachspeise)
- Gästeübersicht mit Statistiken
- Bestellungshistorie
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
http://localhost/11_Menüwahl/install.php
```

Folgen Sie den Installationsschritten:
1. **Umgebungsprüfung** - System überprüft Anforderungen
2. **Datenbank-Verbindung** - Geben Sie DB-Zugangsdaten und Tabellenpräfix ein
3. **Admin-Benutzer** - Erstellen Sie den ersten Administrator
4. **SMTP-Konfiguration** - Richten Sie Mail-Versand ein

### 3. Nach der Installation

Admin-Login:
```
http://localhost/11_Menüwahl/admin/login.php
```

Gast-Formular:
```
http://localhost/11_Menüwahl/index.php?project=1
```

---

## Projektstruktur

```
11_Menüwahl/
├── index.php                 # Gast-Formular
├── install.php              # Installations-Assistent
├── db.php                   # Zentrale DB-Verbindung
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
│   ├── projects.php         # Projektenverwaltung
│   ├── dishes.php           # Menüverwaltung
│   ├── guests.php           # Gästeübersicht
│   ├── export_pdf.php       # PDF Export
│   ├── settings_mail.php    # Mail-Einstellungen
│   ├── profile.php          # Admin Profil
│   └── logout.php           # Logout
├── nav/
│   └── top_nav.php          # Hauptnavigation
├── assets/
│   ├── css/
│   │   └── style.css        # Hauptstyles
│   └── js/
├── storage/
│   ├── logs/                # Log-Dateien
│   ├── pdfs/                # Exportierte PDFs
│   └── tmp/                 # Temporäre Dateien
└── img/                     # Logo und Bilder
```

---

## Datenbankschema

### Haupttabellen

**`menu_users`** - Administrator-Benutzer
```sql
id, firstname, lastname, email, password_hash, role_id, is_active, created_at
```

**`menu_projects`** - Veranstaltungen/Projekte
```sql
id, name, description, location, contact_person, contact_phone, contact_email, 
max_guests, admin_email, is_active, created_by, created_at
```

**`menu_dishes`** - Menü-Gerichte
```sql
id, project_id, category_id, name, description, sort_order, is_active, created_at
```

**`menu_guests`** - Gäste mit Bestellinformationen
```sql
id, project_id, firstname, lastname, email, phone, guest_type (individual|family), 
age_group (adult|child), child_age, family_size, order_status, created_at
```

**`menu_orders`** - Einzelne Menübestellungen pro Gast
```sql
id, guest_id, dish_id, quantity, created_at
```

**`menu_smtp_config`** - SMTP Server-Konfiguration
```sql
id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, sender_email, sender_name
```

**`menu_mail_logs`** - Versandhistorie
```sql
id, sender, recipient, subject, sent_at, status, error_message
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
- Passwort: App-Passwort
- Verschlüsselung: TLS

**Strato:**
- Host: `smtp.strato.de`
- Port: 587
- Verschlüsselung: TLS

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

### Log-Eintrag erstellen

```php
logAction($pdo, $prefix, 'action_name', 'Details');
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

**Emails werden nicht versendet:**
- Überprüfen Sie SMTP-Konfiguration
- Klicken Sie auf "Test-Email versenden"
- Überprüfen Sie Mail-Logs im Admin-Bereich

**Installation wird nicht angezeigt:**
- Überprüfen Sie, dass `script/` Schreibrechte hat
- Überprüfen Sie PHP Error Logs

---

## Sicherheit

- ✅ Passwörter mit `password_hash()` verschlüsselt
- ✅ Prepared Statements gegen SQL-Injection
- ✅ Session-basierte Authentifizierung
- ✅ Admin-Funktionen nur für authorisierte Benutzer
- ⚠️ SSL/HTTPS empfohlen für Production
- ⚠️ Regelmäßige Backups durchführen

---

## Changelog

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

## Support & Dokumentation

Die Anwendung wird unterstützt von:
- `db.php` - Zentrale Datenbankverbindung
- `script/auth.php` - Benutzer-Authentifizierung
- `script/mailer.php` - Email-Versand
- `nav/top_nav.php` - Konsistente Navigation

---

**Version:** 1.7.0
**Lizenz:** Proprietary  
**Datum:** 21. Februar 2026
