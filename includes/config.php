<?php
declare(strict_types=1);

// Güvenlik fonksiyonlarını dahil et
require_once __DIR__ . '/../admin/d6JXwyy12M/includes/security.php';

$dsn = 'mysql:host=13.61.139.31;port=3306;dbname=decorss;charset=utf8mb4';
$dbUser = 'root';
$dbPass = '@Bura-Girme1991';

$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false, // Gerçek prepared statements
];

$pdo = new PDO($dsn, $dbUser, $dbPass, $options);


