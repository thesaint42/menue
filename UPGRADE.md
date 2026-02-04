# 🚀 Upgrade-Anleitung für Menüwahl

## Aktuelle Version: 2.2.0

Diese Version enthält:
- ✅ Familienmitglieder mit Details (Namen, Alter, Hochstuhl)
- ✅ PIN-basierter Zugang statt direkter URL-Parameter
- ✅ QR-Code Generierung
- ✅ E-Mail Einladungs-System

---

## 📋 Upgrade-Schritte (für bestehende Installationen)

### Schritt 1: Backup erstellen
```bash
# Backup der kompletten Installation
cp -r 11_Menüwahl 11_Menüwahl.backup

# Backup der Datenbank
mysqldump -u[USER] -p[PASSWORD] [DBNAME] > db_backup.sql
```

### Schritt 2: Dateien aktualisieren
- Ersetzen Sie alle PHP-Dateien mit der neuen Version
- Die `script/schema.php` ist aktualisiert
- Neue Datei: `admin/generate_qrcode.php`
- Neue Datei: `migrate.php`

### Schritt 3: Migrationen ausführen
1. **Anmelden** als Admin
2. Im Menü **"Migrationen"** aufrufen
3. Alle ausstehenden Migrationen ausführen (oben nach unten):
   - ✅ Familienmitglieder-Tabelle hinzufügen (2.1.0)
   - ✅ age_group Spalte entfernen (2.1.0) - abhängig von vorheriger
   - ✅ Zugangs-PIN zu Projekten hinzufügen (2.2.0)

### Schritt 4: URLs aktualisieren
**Alte URL:**
```
https://example.com/menue/index.php?project=1
```

**Neue URL (mit PIN - z.B. PIN ist 123456):**
```
https://example.com/menue/index.php?pin=123456
```

Oder: Gäste erhalten die PIN/QR-Code per E-Mail

---

## 🔧 Technische Details - Was wird migriert?

### Migration 1: Familienmitglieder-Tabelle (2.1.0)
**Neue Tabelle:** `menu_family_members`
```sql
- id: INT
- guest_id: INT (FK zu guests)
- name: VARCHAR(100) - Name der Person
- member_type: ENUM('adult', 'child')
- child_age: INT - Alter des Kindes
- highchair_needed: TINYINT(1) - Hochstuhl benötigt
```

**Status:** Alte Spalten `age_group` und `child_age` in `guests` sind noch vorhanden

### Migration 2: age_group entfernen (2.1.0)
**Abhängig von:** Migration 1
**Gelöschte Spalten aus `guests`:**
- `age_group` (ENUM)
- `child_age` (INT)

**Grund:** Alle Gast-Altersinformationen sind jetzt in `family_members`

### Migration 3: Zugangs-PIN hinzufügen (2.2.0)
**Neue Spalte in `projects`:**
- `access_pin`: VARCHAR(10) UNIQUE

**Was passiert:** Für alle bestehenden Projekte werden automatisch 6-stellige PINs generiert

---

## ⚠️ Wichtige Hinweise

1. **Keine Datenverluste** - Alle Migrationen sind nicht-destruktiv
2. **Alte URLs funktionieren nicht mehr** - Müssen auf PIN-basiert umgestellt werden
3. **Bestehende Bestellungen** - Bleiben erhalten
4. **Gäste-Daten** - Werden beibehalten, nur erweitert

---

## 🆘 Troubleshooting

### Problem: "Migration bereits ausgeführt"
**Lösung:** Das ist normal - Migration wird nur einmal pro Datenbank ausgeführt

### Problem: "Fehler bei Migration"
**Lösung:** 
1. Prüfen Sie Datenbankrechte
2. Stellen Sie aus Backup wieder her: `mysql -u[USER] -p[PASSWORD] [DBNAME] < db_backup.sql`
3. Versuchen Sie es erneut

### Problem: "Migrations-Link fehlt in der Navigation"
**Lösung:** 
- Prüfen Sie `nav/top_nav.php` ob "migrate" im `$page_names` Array vorhanden ist
- Seite neu laden (Browser-Cache leeren)

---

## 📧 Nach dem Upgrade

### Admin-Aufgaben:
1. Alle **Projekte anschauen** - PINs wurden automatisch generiert
2. Button **"📱 PIN/QR"** klicken um PIN zu sehen
3. Optional: **QR-Code downloaden** und ausdrucken
4. Gäste **per E-Mail einladen** mit dem neuen System

### Gäste-Zugang:
- Alte Links funktionieren nicht mehr
- Gäste müssen PIN eingeben oder QR-Code scannen
- PIN wird per E-Mail verschickt

---

## 🔄 Rollback (Falls nötig)

```bash
# Datenbank zurückstellen
mysql -u[USER] -p[PASSWORD] [DBNAME] < db_backup.sql

# Dateien zurückstellen
rm -rf 11_Menüwahl
mv 11_Menüwahl.backup 11_Menüwahl
```

---

## ✅ Upgrade erfolgreich?

Nach dem Upgrade sollten Sie sehen:
- ✅ Migrations-Seite ist erreichbar
- ✅ Alle Migrationen sind "Ausgeführt"
- ✅ Alle Projekte haben eine PIN
- ✅ QR-Code kann generiert werden
- ✅ E-Mail Versand funktioniert

---

**Support:** Bei Fragen zur Installation kontaktieren Sie den Administrator.
