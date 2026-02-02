<?php
/**
 * admin/logout.php - Logout
 */
session_start();
session_unset();
session_destroy();
header("Location: login.php?msg=logged_out");
exit;
