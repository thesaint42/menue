<?php
$title = 'Verzeichnis von Verarbeitungstätigkeiten (VVT)';
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
    <h1>Verzeichnis von Verarbeitungstätigkeiten (VVT)</h1>
    <p class="page-intro">(gem. Art. 30 DSGVO)</p>

    <h2>Verarbeitung: Bestellabwicklung</h2>
    <ul>
      <li><strong>Zweck:</strong> Menübestellungen</li>
      <li><strong>Betroffene Personen:</strong> Besteller</li>
      <li><strong>Datenkategorien:</strong> Name, E‑Mail, Telefonnummer, Bestelldaten</li>
      <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO</li>
      <li><strong>Speicherfrist:</strong> projektbezogen bzw. gesetzliche Aufbewahrungspflichten</li>
    </ul>

    <h2>Verarbeitung: Server‑Logfiles</h2>
    <ul>
      <li><strong>Zweck:</strong> Sicherheit und Betrieb</li>
      <li><strong>Datenkategorien:</strong> IP‑Adresse, Zugriffsdaten</li>
      <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO</li>
      <li><strong>Speicherfrist:</strong> gemäß Hosting‑Vorgaben</li>
    </ul>

    <h2>Verarbeitung: Sitzungsverwaltung</h2>
    <ul>
      <li><strong>Zweck:</strong> Admin‑Login</li>
      <li><strong>Datenkategorien:</strong> Session‑ID (PHPSESSID)</li>
      <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO</li>
    </ul>

  </main>

<?php include __DIR__ . '/nav/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
