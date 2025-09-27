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
$message = validateAndSanitize($_POST['message'] ?? '', 'string', 2000);

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('contact_form_' . $clientIp, 3, 300)) { // 3 deneme, 5 dakika
	http_response_code(429);
	echo json_encode(['ok' => false, 'error' => 'Çox sürətli göndərmə. Zəhmət olmasa gözləyin.']);
	exit;
}

if ($name === '' || $email === '' || $message === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Eksik alanlar']);
	exit;
}

if ($email === false) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Düzgün e-poçt ünvanı daxil edin']);
	exit;
}

try {
	$stmt = secureQuery($pdo, 'INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)', [$name, $email, $message]);
	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	error_log('Contact form error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'DB error']);
}
exit;
