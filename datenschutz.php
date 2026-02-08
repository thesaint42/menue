<?php
$title = 'Datenschutzerklärung';
?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($title); ?> - Event Menue Order System (EMOS)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.css">
</head>
<body>
<?php include __DIR__ . '/nav/top_nav.php'; ?>

  <main class="container" style="max-width:800px;margin:2rem auto;padding:1rem;">
    <h1>🔐 Datenschutzerklärung</h1>

    <h2>1. Verantwortlicher</h2>
    <p>
      Olaf Schneider<br>
      Schmollerstraße 58/1<br>
      74074 Heilbronn<br>
      E‑Mail: <a href="mailto:admin@schneider-ret.de">admin@schneider-ret.de</a>
    </p>

    <h2>2. Allgemeines zur Datenverarbeitung</h2>
    <p>Der Schutz personenbezogener Daten ist mir wichtig. Personenbezogene Daten werden ausschließlich im Rahmen der gesetzlichen Vorschriften der Datenschutz‑Grundverordnung (DSGVO) verarbeitet.</p>

    <h2>3. Hosting</h2>
    <p>Diese Website wird bei Host Europe GmbH, Deutschland, betrieben.</p>
    <p>Beim Aufruf der Website werden durch Host Europe automatisch sogenannte Server‑Logfiles erhoben und gespeichert. Diese beinhalten:</p>
    <ul>
      <li>IP‑Adresse</li>
      <li>Datum und Uhrzeit der Anfrage</li>
      <li>aufgerufene Seite</li>
      <li>Browsertyp und Betriebssystem</li>
    </ul>
    <p><strong>Zweck:</strong> Sicherstellung eines störungsfreien Betriebs sowie zur Fehleranalyse.</p>
    <p><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse)</p>

    <h2>4. SSL‑Verschlüsselung</h2>
    <p>Diese Website nutzt aus Sicherheitsgründen eine SSL‑Verschlüsselung. Daten, die übermittelt werden, können nicht von Dritten mitgelesen werden.</p>

    <h2>5. Cookies</h2>
    <p>Diese Website verwendet ausschließlich technisch notwendige Cookies.</p>
    <p><strong>PHPSESSID</strong><br>
    Zur Verwaltung von Sitzungen (z. B. für Administrationsfunktionen) wird ein Session‑Cookie (PHPSESSID) gesetzt.</p>
    <p><strong>Zweck:</strong> Sitzungsverwaltung<br>
    <strong>Speicherdauer:</strong> bis zum Ende der Sitzung<br>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO</p>
    <p>Es werden keine Tracking‑oder Marketing‑Cookies eingesetzt.</p>

    <h2>6. Bestellformular</h2>
    <p>Im Rahmen der Nutzung des Bestellformulars werden folgende personenbezogene Daten verarbeitet:</p>
    <ul>
      <li>Vorname, Nachname</li>
      <li>E‑Mail‑Adresse</li>
      <li>Telefonnummer</li>
      <li>Bestelldaten (Personenanzahl, Essensart, Typ)</li>
    </ul>
    <p><strong>Zweck:</strong> Abwicklung von Menübestellungen.<br>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertrag / vorvertragliche Maßnahmen)</p>

    <h2>7. Benutzerkonten</h2>
    <p>Es existieren ausschließlich administrative Benutzerkonten. Endnutzer erhalten lediglich projektbezogene Zugangscodes und keine eigenen Benutzerkonten.</p>

    <h2>8. E‑Mail‑Versand</h2>
    <p>Der Versand von E‑Mails erfolgt über ein eigenes SMTP‑Konto bei Host Europe. Es findet keine Weitergabe an Dritte statt.</p>

    <h2>9. Content Delivery Network (CDN)</h2>
    <p>Zur Auslieferung von JavaScript‑Bibliotheken wird jsDelivr verwendet. Dabei kann technisch bedingt die IP‑Adresse an Server von jsDelivr übertragen werden.</p>
    <p><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einer sicheren und effizienten Bereitstellung)</p>

    <h2>10. Rechte der betroffenen Personen</h2>
    <p>Betroffene Personen haben folgende Rechte: Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit, Widerruf erteilter Einwilligungen, Beschwerde bei einer Datenschutzaufsichtsbehörde. Anfragen können jederzeit per E‑Mail gestellt werden.</p>

    <h2>11. Verzeichnis von Verarbeitungstätigkeiten (VVT)</h2>
    <p>Ein internes Verzeichnis von Verarbeitungstätigkeiten gemäß Art. 30 DSGVO wird geführt und kann auf Anfrage eingesehen werden. Sie können das Verzeichnis hier einsehen: <a href="vvt.php">Verzeichnis von Verarbeitungstätigkeiten (VVT)</a>.</p>

  </main>

<?php include __DIR__ . '/nav/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

