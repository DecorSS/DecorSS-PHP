<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';

// Güvenlik fonksiyonlarını dahil et
require_once __DIR__ . '/admin/d6JXwyy12M/includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

// Input validation ve sanitization
$name = validateAndSanitize($_POST['name'] ?? '', 'string', 255);
$email = validateAndSanitize($_POST['email'] ?? '', 'email', 190);
$date = validateAndSanitize($_POST['reservation_date'] ?? '', 'string', 20);
$time = validateAndSanitize($_POST['reservation_time'] ?? '', 'string', 20);

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('reservation_form_' . $clientIp, 3, 300)) { // 3 deneme, 5 dakika
	http_response_code(429);
	echo json_encode(['ok' => false, 'error' => 'Çox sürətli göndərmə. Zəhmət olmasa gözləyin.']);
	exit;
}

if ($name === '' || $email === '' || $date === '' || $time === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Eksik alanlar']);
	exit;
}

if ($email === false) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Düzgün e-poçt ünvanı daxil edin']);
	exit;
}

// Tarih ve saat formatını kontrol et
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Düzgün tarix formatı daxil edin']);
	exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Düzgün saat formatı daxil edin']);
	exit;
}

try {
	$stmt = secureQuery($pdo, 'INSERT INTO reservations (name, email, reservation_date, reservation_time) VALUES (?, ?, ?, ?)', [$name, $email, $date, $time]);
	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	error_log('Reservation form error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'DB error']);
}
exit;


