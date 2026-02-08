<?php
$title = 'Impressum';
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
    <h1>📄 Impressum</h1>

    <p><strong>Angaben gemäß § 5 Digitale-Dienste-Gesetz (DDG)</strong></p>

    <p><strong>Verantwortlicher:</strong><br>
    Olaf Schneider<br>
    Schmollerstraße 58/1<br>
    74074 Heilbronn<br>
    Deutschland</p>

    <p><strong>Kontakt:</strong><br>
    E‑Mail: <a href="mailto:admin@schneider-ret.de">admin@schneider-ret.de</a></p>

    <p><strong>Projekt:</strong><br>
    Event Menue Order System (EMOS)</p>

  </main>

<?php include __DIR__ . '/nav/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
