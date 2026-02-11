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
                    <p class="text-muted mb-4">Bitte geben Sie die Projekt-PIN ein:</p>
                    
                    <form method="get" action="index.php">
                        <div class="mb-3">
                            <input type="text" name="pin" class="form-control form-control-lg text-center fs-5 fw-bold" 
                                   placeholder="000000" maxlength="10" required autofocus 
                                   style="letter-spacing: 0.5em; font-family: monospace;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Zugang</button>
                    </form>
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
</body>
</html>
