<?php
/**
 * Güvenlik fonksiyonları
 */

/**
 * HTML sanitization - XSS koruması
 */
function sanitizeHtml($input) {
    if (is_array($input)) {
        return array_map('sanitizeHtml', $input);
    }
    
    if (!is_string($input)) {
        return $input;
    }
    
    // HTML karakterlerini encode et
    // ENT_QUOTES: tek ve çift tırnakları encode eder
    // ENT_HTML5: HTML5 uyumlu encoding
    // ENT_SUBSTITUTE: geçersiz karakterleri değiştirir
    // ENT_DISALLOWED: izin verilmeyen karakterleri encode eder
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true);
    
    // Ek güvenlik: JavaScript ve diğer tehlikeli karakterleri temizle
    $input = preg_replace('/javascript:/i', '', $input);
    $input = preg_replace('/on\w+\s*=/i', '', $input);
    $input = preg_replace('/data:/i', '', $input);
    $input = preg_replace('/vbscript:/i', '', $input);
    
    return $input;
}

/**
 * Güçlü HTML encoding - Tüm HTML karakterlerini encode eder
 */
function encodeHtml($input) {
    if (is_array($input)) {
        return array_map('encodeHtml', $input);
    }
    
    if (!is_string($input)) {
        return $input;
    }
    
    // Tüm HTML karakterlerini encode et
    $htmlEntities = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#x27;',
        '/' => '&#x2F;',
        '`' => '&#x60;',
        '=' => '&#x3D;'
    ];
    
    $input = str_replace(array_keys($htmlEntities), array_values($htmlEntities), $input);
    
    // Ek güvenlik: JavaScript ve diğer tehlikeli karakterleri temizle
    $input = preg_replace('/javascript:/i', '', $input);
    $input = preg_replace('/on\w+\s*=/i', '', $input);
    $input = preg_replace('/data:/i', '', $input);
    $input = preg_replace('/vbscript:/i', '', $input);
    $input = preg_replace('/expression\s*\(/i', '', $input);
    
    return $input;
}

/**
 * Input validation ve sanitization
 */
function validateAndSanitize($input, $type = 'string', $maxLength = 255) {
    if (is_array($input)) {
        return array_map(function($item) use ($type, $maxLength) {
            return validateAndSanitize($item, $type, $maxLength);
        }, $input);
    }
    
    if (!is_string($input)) {
        return $input;
    }
    
    // Trim ve temizle
    $input = trim($input);
    
    // Maksimum uzunluk kontrolü
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    switch ($type) {
        case 'email':
            $input = filter_var($input, FILTER_SANITIZE_EMAIL);
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            break;
            
        case 'int':
            $input = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            if (!filter_var($input, FILTER_VALIDATE_INT)) {
                return false;
            }
            break;
            
        case 'float':
            $input = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            if (!filter_var($input, FILTER_VALIDATE_FLOAT)) {
                return false;
            }
            break;
            
        case 'url':
            $input = filter_var($input, FILTER_SANITIZE_URL);
            if (!filter_var($input, FILTER_VALIDATE_URL)) {
                return false;
            }
            break;
            
        case 'filename':
            // Dosya adı için güvenli karakterler
            $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
            break;
            
        case 'alphanumeric':
            $input = preg_replace('/[^a-zA-Z0-9]/', '', $input);
            break;
            
        case 'string':
        default:
            // Güçlü HTML encoding
            $input = encodeHtml($input);
            break;
    }
    
    return $input;
}

/**
 * Güvenli dosya yolu kontrolü
 */
function validateFilePath($path, $allowedDir = '') {
    // Null byte injection koruması
    if (strpos($path, "\0") !== false) {
        return false;
    }
    
    // Directory traversal koruması
    if (strpos($path, '..') !== false || strpos($path, './') !== false) {
        return false;
    }
    
    // Mutlak yol kontrolü
    if (strpos($path, '/') === 0) {
        return false;
    }
    
    // İzin verilen dizin kontrolü
    if ($allowedDir && !str_starts_with($path, $allowedDir)) {
        return false;
    }
    
    return true;
}

/**
 * Rate limiting kontrolü
 */
function checkRateLimit($key, $maxAttempts = 10, $timeWindow = 300) {
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key) . '.txt';
    
    $attempts = [];
    if (file_exists($cacheFile)) {
        $attempts = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    
    $now = time();
    $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    $attempts[] = $now;
    file_put_contents($cacheFile, json_encode($attempts));
    
    return true;
}

/**
 * Güvenli random string oluştur
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Password hash kontrolü
 */
function validatePassword($password) {
    if (strlen($password) < 6) {
        return false;
    }
    
    // Güçlü şifre kontrolü (opsiyonel)
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * SQL injection koruması için prepared statement wrapper
 */
function secureQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('SQL Error: ' . $e->getMessage());
        throw new Exception('Database error');
    }
}

/**
 * Content Security Policy header'ı
 */
function setSecurityHeaders() {
    // XSS koruması
    header('X-XSS-Protection: 1; mode=block');
    
    // Content type sniffing koruması
    header('X-Content-Type-Options: nosniff');
    
    // Clickjacking koruması
    header('X-Frame-Options: DENY');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; " .
           "img-src 'self' data:; " .
           "font-src 'self' cdnjs.cloudflare.com; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none';";
    
    header("Content-Security-Policy: $csp");
}

/**
 * Session güvenliği
 */
function secureSession() {
    // Session hijacking koruması
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        return false;
    }
    
    // Session fixation koruması
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 dakika
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    return true;
}
