<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Eingabe - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'nav/top_nav.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card border-0 shadow bg-dark text-light">
                <div class="card-body p-5 text-center">
                    <div class="mb-4" style="font-size: 4rem;">🍽️</div>
                    <h2 class="card-title mb-4">Event Menue Order System (EMOS)</h2>
                    
                    <!-- PIN Eingabe -->
                    <div id="pinSection">
                        <p class="text-muted mb-4">Bitte geben Sie die Projekt-PIN ein:</p>
                        <form method="get" action="index.php">
                            <div class="mb-3">
                                <input type="text" name="pin" class="form-control form-control-lg text-center fs-5 fw-bold" 
                                       placeholder="000000" maxlength="10" required autofocus 
                                       style="letter-spacing: 0.5em; font-family: monospace;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Zugang</button>
                        </form>
                        
                        <div class="text-center my-3">
                            <small class="text-muted">oder</small>
                        </div>
                        
                        <button type="button" class="btn btn-outline-primary btn-lg w-100" onclick="toggleOrderIdInput()">
                            📝 Bestellung mit Order-ID bearbeiten
                        </button>
                    </div>
                    
                    <!-- Order-ID Eingabe (hidden by default) -->
                    <div id="orderIdSection" style="display: none;">
                        <p class="text-muted mb-4">Geben Sie Ihre Order-ID ein:</p>
                        <form method="get" action="index.php">
                            <input type="hidden" name="action" value="edit">
                            <div class="mb-3">
                                <input type="text" name="order_id" class="form-control form-control-lg text-center fs-5 fw-bold" 
                                       placeholder="12345-67890" pattern="\d{5}-\d{5}" inputmode="numeric"
                                       style="letter-spacing: 0.3em; font-family: monospace;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Bestellung laden</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-link text-muted" onclick="toggleOrderIdInput()">
                                ← Zurück zur PIN-Eingabe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info text-center mt-4 small">
                💡 Tipp: Sie können auch den QR-Code mit einem Smartphone scannen
            </div>
        </div>
    </div>
</div>

<?php include 'nav/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleOrderIdInput() {
    const pinSection = document.getElementById('pinSection');
    const orderIdSection = document.getElementById('orderIdSection');
    
    if (pinSection.style.display === 'none') {
        pinSection.style.display = 'block';
        orderIdSection.style.display = 'none';
        // Focus auf PIN-Eingabe
        setTimeout(() => {
            const pinInput = pinSection.querySelector('input[name="pin"]');
            if (pinInput) pinInput.focus();
        }, 100);
    } else {
        pinSection.style.display = 'none';
        orderIdSection.style.display = 'block';
        // Focus auf Order-ID-Eingabe
        setTimeout(() => {
            const orderInput = orderIdSection.querySelector('input[name="order_id"]');
            if (orderInput) orderInput.focus();
        }, 100);
    }
}
</script>
</body>
</html>
