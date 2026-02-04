# ✅ Upgrade-Checkliste v2.2.0

## Vor dem Upgrade
- [ ] Backup der kompletten Installation erstellen
- [ ] Backup der Datenbank erstellen
- [ ] Admin-Zugang testen
- [ ] Alle Gäste-Bestellungen sollten abgeschlossen sein

## Dateien aktualisieren
- [ ] `index.php` - Neue PIN-basierte Gast-Seite
- [ ] `admin/projects.php` - PIN/QR-Code Management
- [ ] `admin/generate_qrcode.php` - Neue Datei
- [ ] `migrate.php` - Existiert und enthält alle Migrationen
- [ ] `script/schema.php` - Schema mit PIN-Feld
- [ ] `nav/top_nav.php` - Migrations-Link hinzugefügt
- [ ] `README.md` - Dokumentation aktualisiert
- [ ] `UPGRADE.md` - Upgrade-Anleitung

## Datenbankmigrationen ausführen (der Reihe nach!)
1. [ ] **Migration: Familienmitglieder-Tabelle hinzufügen**
   - Status: Sollte "Ausgeführt" sein nach Durchführung
   - Effekt: Neue Tabelle `menu_family_members` erstellt

2. [ ] **Migration: age_group Spalte entfernen**
   - Status: Sollte "Ausgeführt" sein nach Durchführung
   - Effekt: Alte Spalten aus `guests` entfernt
   - Abhängig von: Familienmitglieder-Tabelle

3. [ ] **Migration: Zugangs-PIN zu Projekten hinzufügen**
   - Status: Sollte "Ausgeführt" sein nach Durchführung
   - Effekt: `access_pin` zu `projects` hinzugefügt, PINs generiert

## Nach dem Upgrade

### Admin-Tests
- [ ] Als Admin anmelden
- [ ] "Migrationen"-Seite aufrufen → Sollte alle Migrationen als "Ausgeführt" zeigen
- [ ] Alle Projekte anschauen → Sollten alle eine PIN haben
- [ ] "📱 PIN/QR" Button klicken → PIN und QR-Code anzeigen
- [ ] QR-Code downloaden → Als PNG speicherbar
- [ ] "✉️ Per E-Mail einladen" klicken → Modal öffnet sich
- [ ] Test-Email versenden → Sollte ankommen

### Gäste-Tests
- [ ] `index.php` ohne Parameter aufrufen → PIN-Eingabeformular anzeigen
- [ ] PIN eingeben → Zum Bestellformular weitergeleitet
- [ ] QR-Code mit Smartphone scannen → Link funktioniert
- [ ] Bestellung aufgeben → Bestätigungsemail kommt an
- [ ] Familienmitglieder-Details eingeben:
  - [ ] Name für jede Person
  - [ ] Typ (Erwachsen/Kind) wechselbar
  - [ ] Alter-Feld bei Kind sichtbar
  - [ ] Hochstuhl-Checkbox bei Kind sichtbar

### Admin-Reports
- [ ] Gästeübersicht anschauen → Familiendetails korrekt angezeigt
- [ ] PDF-Export → Alle Informationen enthalten
- [ ] Bestellungen → Korrekt pro Person erfasst

## Dokumentation aktualisieren
- [ ] UPGRADE.md den Kunden zugänglich machen
- [ ] PIN an alle Gäste mitteilen (per QR-Code/E-Mail)
- [ ] Alte Direct-Links aktualisieren

## Rollback-Plan (Falls nötig)
- [ ] Backup-Dateien verfügbar: `11_Menüwahl.backup`
- [ ] Backup-Datenbank verfügbar: `db_backup.sql`
- [ ] Rollback-Anleitung bereit

## Häufige Fehler

### "Migrations-Link nicht sichtbar"
✅ Lösung: 
- Seite neu laden (Ctrl+F5 für Hard Refresh)
- Prüfen: `nav/top_nav.php` hat 'migrate' im `$page_names` Array

### "Migration fehlgeschlagen"
✅ Lösung:
- Datenbankrechte prüfen
- Error-Message lesen
- Aus Backup wiederherstellen und erneut versuchen

### "Alte URLs funktionieren nicht"
✅ Erwartet! Neue URL-Format:
- Alt: `?project=1`
- Neu: `?pin=123456`

### "QR-Code zeigt sich nicht"
✅ Lösung:
- `admin/generate_qrcode.php` existiert?
- Google Charts API erreichbar? (https://chart.googleapis.com)
- Admin-Berechtigungen korrekt?

## Post-Upgrade Kommunikation

**E-Mail an Gäste:**
```
Liebe Gäste,

wir haben unser Bestellsystem aktualisiert. Sie können nun über 
eine PIN oder einen QR-Code auf unser Menü-Bestellformular zugreifen.

PIN: [HIER EINFÜGEN]

oder scannen Sie diesen QR-Code mit Ihrem Smartphone:
[BILD HIER]

Viele Grüße
```

---

## Kontakt & Support
- **Dokumentation:** Siehe `UPGRADE.md`
- **Error-Logs:** Prüfen Sie die Browser-Konsole (F12)
- **Datenbank-Fehler:** Prüfen Sie phpMyAdmin oder MySQL CLI

**Upgrade erfolgreich abgeschlossen! 🎉**
