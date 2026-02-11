<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name']); ?> - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'nav/top_nav.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="mb-3" style="font-size: 3rem;">🍽️</div>
                        <h2 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h2>
                        <p class="text-muted">Was möchten Sie tun?</p>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-3">
                        <a href="index.php?pin=<?php echo htmlspecialchars($project['access_pin']); ?>&action=new" 
                           class="btn btn-primary btn-lg">
                            ➕ Neue Bestellung erstellen
                        </a>
                        
                        <div class="text-center my-2">
                            <small class="text-muted">oder</small>
                        </div>
                        
                        <button type="button" class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#editModal">
                            ✏️ Bestehende Bestellung bearbeiten
                        </button>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <a href="index.php" class="text-muted small">↩️ Zur PIN-Eingabe</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Order-ID Eingabe -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bestellung bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="get" action="index.php" id="editForm">
                    <input type="hidden" name="pin" value="<?php echo htmlspecialchars($project['access_pin']); ?>">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="mb-3">
                        <label class="form-label">Order-ID</label>
                        <input type="text" name="order_id" class="form-control font-monospace" 
                               placeholder="12345-67890" 
                               pattern="\d{5}-\d{5}" 
                               inputmode="numeric"
                               required>
                        <div class="form-text">Geben Sie die Order-ID ein, die Sie bei der Bestellung erhalten haben.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Bestellung laden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'nav/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
