<?php
// Output buffering başlat - header hatalarını önlemek için
ob_start();

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

ob_end_clean(); // Buffer'ı temizle
header('Location: dashboard.php');
exit;
