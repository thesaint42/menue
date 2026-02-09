<?php
// tools/inspect_db.php
// Read-only debug endpoint to fetch recent guests, family_members, orders and dishes for a project PIN.
// Usage: https://.../tools/inspect_db.php?pin=534417&limit=10

require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
$prefix = $config['database']['prefix'] ?? 'menu_';
$pin = $_GET['pin'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$cleanup = isset($_GET['cleanup']) ? true : false;

if (!$pin) {
    echo json_encode(['error' => 'missing_pin']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM {$prefix}projects WHERE access_pin = ? AND is_active = 1");
    $stmt->execute([$pin]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo json_encode(['error' => 'invalid_pin']);
        exit;
    }
    $project_id = (int)$project['id'];

    // Dishes
    $stmt = $pdo->prepare("SELECT id, name, category_id, is_active FROM {$prefix}dishes WHERE project_id = ? ORDER BY sort_order LIMIT 1000");
    $stmt->execute([$project_id]);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent guests
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $project_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each guest, fetch family members and orders
    $guest_ids = array_column($guests, 'id');
    $family = [];
    $orders = [];
    if (!empty($guest_ids)) {
        $in  = str_repeat('?,', count($guest_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}family_members WHERE guest_id IN ($in)");
        $stmt->execute($guest_ids);
        $familyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM {$prefix}orders WHERE guest_id IN ($in)");
        $stmt->execute($guest_ids);
        $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($familyRows as $r) {
            $family[$r['guest_id']][] = $r;
        }
        foreach ($orderRows as $r) {
            $orders[$r['guest_id']][] = $r;
        }
    }

    $result = [
        'project' => $project,
        'dishes' => $dishes,
        'guests' => $guests,
        'family_members' => $family,
        'orders' => $orders,
    ];

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($cleanup && file_exists(__FILE__)) {
        @unlink(__FILE__);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
}
