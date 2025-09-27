<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Dosya yüklənmədi']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 10 * 1024 * 1024; // 10MB

// Dosya tipini kontrol et
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Yalnız şəkil faylları qəbul edilir (JPG, PNG, GIF, WebP)']);
    exit;
}

// Dosya boyutunu kontrol et
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fayl çox böyükdür (maksimum 10MB)']);
    exit;
}

// Upload klasörünü oluştur (assets/images/Dekorlar)
$uploadDir = dirname(__DIR__, 2) . '/assets/images/Dekorlar/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Upload klasörü oluşturulamadı: ' . $uploadDir]);
        exit;
    }
}

// Klasör yazma iznini kontrol et
if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Upload klasörü yazılabilir değil: ' . $uploadDir]);
    exit;
}

// Benzersiz dosya adı oluştur
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'product_' . time() . '_' . uniqid() . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Dosyayı taşı
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // Web erişilebilir yol
    $webPath = 'assets/images/Dekorlar/' . $fileName;
    echo json_encode(['ok' => true, 'path' => $webPath, 'filename' => $fileName]);
} else {
    // Detaylı hata mesajı
    $error = 'Fayl yüklənmədi';
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'Fayl çox böyükdür';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error = 'Fayl qismən yükləndi';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = 'Fayl seçilmədi';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error = 'Müvəqqəti klasör yoxdur';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error = 'Diskə yazma xətası';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error = 'PHP uzantısı tərəfindən dayandırıldı';
                break;
        }
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $error . ' (Debug: ' . $filePath . ')']);
}
exit;
