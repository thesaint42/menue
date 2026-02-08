<?php
/**
 * admin/generate_qrcode.php - QR-Code Generator für Projekt-PINs
 * Erzeugt QR-Code für die PIN-basierte Projektzugriff
 */

// Keine Session/Auth-Prüfung für Bild-Ausgabe
// Das Bild wird direkt in <img> tags geladen, die keinen Session-Context haben

if (!isset($_GET['project']) || !isset($_GET['pin'])) {
    http_response_code(400);
    die("Parameter fehlen");
}

$project_id = (int)$_GET['project'];
$pin = $_GET['pin'];

// Validiere PIN Format (6 Ziffern)
if (!preg_match('/^\d{6}$/', $pin)) {
    http_response_code(400);
    die("Ungültige PIN");
}

// Größe mit Validierung
$qr_size = isset($_GET['size']) ? (int)$_GET['size'] : 300;
if ($qr_size < 100 || $qr_size > 800) {
    $qr_size = 300;
}

// Generiere die URL für den QR-Code
// QR enthält: PIN und Link zum Zugriff
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$access_url = $base_url . dirname($_SERVER['PHP_SELF'], 2) . '/index.php?pin=' . urlencode($pin);

// Nutze qrserver.com API (zuverlässig, keine Auth nötig, schnell)
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}x{$qr_size}&data=" . urlencode($access_url);

// Stream-Context mit Timeout für HTTPS
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'EventMenueOrderSystem/2.2.0'
    ],
    'https' => [
        'timeout' => 5,
        'user_agent' => 'Menüwahl-System/2.2.0',
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

// QR-Code vom Service abrufen
$qr_image = @file_get_contents($qr_api_url, false, $context);

// Download-Modus?
if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Disposition: attachment; filename="PIN_' . $pin . '.png"');
} else {
    header('Content-Disposition: inline');
}

// Response-Header für PNG-Bild
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Pragma: public');

if ($qr_image !== false) {
    header('Content-Length: ' . strlen($qr_image));
    echo $qr_image;
} else {
    // Fallback: Fehler wenn API nicht erreichbar
    http_response_code(500);
    header('Content-Type: text/plain');
    die('QR-Code konnte nicht generiert werden. Bitte versuchen Sie es später erneut.');
}

