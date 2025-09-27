<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';

if (isAdminLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	ensureCsrf();
	$email = validateAndSanitize($_POST['email'] ?? '', 'email', 190);
	$password = $_POST['password'] ?? '';
	
	// Rate limiting
	$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if (!checkRateLimit('login_' . $clientIp, 5, 900)) { // 5 deneme, 15 dakika
		$error = 'Çox sürətli giriş cəhdi. 15 dəqiqə gözləyin.';
	} elseif ($email === '' || $password === '') {
		$error = 'E-poçt və şifrə mütləqdir';
	} elseif ($email === false) {
		$error = 'Düzgün e-poçt ünvanı daxil edin';
	} else if (adminLogin($pdo, $email, $password)) {
		header('Location: index.php');
		exit;
	} else {
		$error = 'Yanlış məlumatlar';
	}
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <title>Admin Giriş</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: var(--panel);
            border: 1px solid #111827;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-header h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }
        
        .login-header p {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            background: #0b1222;
            color: #fff;
            border: 1px solid #111827;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group input::placeholder {
            color: var(--muted);
        }
        
        .login-btn {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        
        .login-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #111827;
        }
        
        .login-footer p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Giriş</h1>
            <p>İdarəetmə paneline daxil olun</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?=encodeHtml($error)?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?=$csrf?>">
            
            <div class="form-group">
                <label for="email">E-poçt ünvanı</label>
                <input type="email" id="email" name="email" placeholder="admin@example.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifrə</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="login-btn">Giriş Et</button>
        </form>
        
        <div class="login-footer">
            <p>© 2024 Admin Panel. Bütün hüquqlar qorunur.</p>
        </div>
    </div>
</body>
</html>


