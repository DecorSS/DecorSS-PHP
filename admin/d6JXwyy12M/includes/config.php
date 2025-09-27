<?php
declare(strict_types=1);

// Güvenlik fonksiyonlarını dahil et
require_once __DIR__ . '/security.php';

// Güvenlik header'larını ayarla
setSecurityHeaders();

$dsn = 'mysql:host=13.61.139.31;port=3306;dbname=decorss;charset=utf8mb4';
$dbUser = 'render_user';
$dbPass = 'Girme1991-@Bura';

$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false, // Gerçek prepared statements
];

$pdo = new PDO($dsn, $dbUser, $dbPass, $options);

// Session ayarlarını session başlatılmadan önce yap
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_name('decorss_admin');
session_start();

// Session güvenliğini kontrol et
if (!secureSession()) {
    // Buffer varsa temizle
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}
