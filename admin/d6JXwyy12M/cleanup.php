<?php
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// JSON input'u al
$input = json_decode(file_get_contents('php://input'), true);
$imagePath = $input['image_path'] ?? '';

if (empty($imagePath)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Image path required']);
    exit;
}

// Güvenlik kontrolü - sadece Dekorlar klasöründeki product_ dosyalarını sil
if (!str_starts_with($imagePath, 'assets/images/Dekorlar/') || !str_contains($imagePath, 'product_')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid image path']);
    exit;
}

// Veritabanı bağlantısı
require_once __DIR__ . '/includes/config.php';

// Bu görselin kullanılıp kullanılmadığını kontrol et
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE image_path = ?');
$stmt->execute([$imagePath]);
$isUsed = $stmt->fetchColumn() > 0;

if ($isUsed) {
    echo json_encode(['ok' => false, 'error' => 'Image is being used by a product']);
    exit;
}

// Dosya yolunu oluştur
$fullPath = dirname(__DIR__, 2) . '/' . $imagePath;

// Dosya var mı kontrol et
if (!is_file($fullPath)) {
    echo json_encode(['ok' => true, 'message' => 'File already deleted']);
    exit;
}

// Dosyayı sil
if (unlink($fullPath)) {
    echo json_encode(['ok' => true, 'message' => 'File deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to delete file']);
}
exit;
