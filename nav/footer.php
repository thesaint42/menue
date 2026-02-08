<?php
// nav/footer.php - zentrale Fußzeile mit Links zu Impressum und Datenschutz
$is_admin_dir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$root = $is_admin_dir ? '../' : './';
?>
<footer class="site-footer mt-5 py-3 border-top text-muted">
  <div class="container d-flex justify-content-center">
    <small>
      <a href="<?php echo $root; ?>impressum.php">Impressum</a>
      &nbsp;|&nbsp;
      <a href="<?php echo $root; ?>datenschutz.php">Datenschutzerklärung</a>
    </small>
  </div>
</footer>

<style>
  .site-footer { background: transparent; }
  .site-footer a { color: inherit; text-decoration: underline dotted; }
</style>
