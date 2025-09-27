<?php
declare(strict_types=1);

if (!function_exists('ensureCsrf')) {
function ensureCsrf(): void {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
		if (!isset($_POST['_csrf'], $_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])) {
			http_response_code(400);
			exit('Invalid CSRF token');
		}
	}
}
}

if (!function_exists('csrfToken')) {
function csrfToken(): string {
	if (empty($_SESSION['_csrf'])) {
		$_SESSION['_csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['_csrf'];
}
}

if (!function_exists('isAdminLoggedIn')) {
function isAdminLoggedIn(): bool {
	return isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']);
}
}

if (!function_exists('requireAdmin')) {
function requireAdmin(): void {
	if (!isAdminLoggedIn()) {
		header('Location: login.php');
		exit;
	}
}
}

if (!function_exists('adminLogin')) {
function adminLogin(PDO $pdo, string $email, string $password): bool {
	// Parola DB'de düz metin olarak seed edildi. İlk girişte hash'lenebilir.
	$stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM admins WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	$row = $stmt->fetch();
	if (!$row || (int)$row['is_active'] !== 1) return false;

	$ok = false;
	if (preg_match('/^\$2[aby]\$|^\$argon2/i', (string)$row['password_hash'])) {
		$ok = password_verify($password, (string)$row['password_hash']);
	} else {
		// İlk seed düz metin: karşılaştır
		$ok = hash_equals((string)$row['password_hash'], $password);
		if ($ok) {
			// Başarılı ilk girişte hash'e çevir
			$newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
			$upd = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
			$upd->execute([$newHash, $row['id']]);
		}
	}

	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$pdo->prepare('INSERT INTO admin_login_attempts (email, ip_address) VALUES (?, ?)')->execute([$email, $ip]);
	if ($ok) {
		$_SESSION['admin_id'] = (int)$row['id'];
		$pdo->prepare('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $row['id']]);
	}
	return $ok;
}
}

if (!function_exists('adminLogout')) {
function adminLogout(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$p = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
	}
	session_destroy();
}
}


