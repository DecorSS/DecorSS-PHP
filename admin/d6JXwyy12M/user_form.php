<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$id = validateAndSanitize($_GET['id'] ?? 0, 'int');
$csrf = csrfToken();

// Form işleme
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ensureCsrf();
    $id = validateAndSanitize($_POST['id'] ?? 0, 'int');
    $name = validateAndSanitize($_POST['name'] ?? '', 'string', 120);
    $email = validateAndSanitize($_POST['email'] ?? '', 'email', 190);
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $error = '';
    
    // Rate limiting
    if (!checkRateLimit('user_form_' . $_SESSION['admin_id'], 10, 300)) {
        $error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
    } elseif ($name === '' || $email === '') {
        $error = 'Ad və e-poçt boş ola bilməz';
    } elseif ($email === false) {
        $error = 'Düzgün e-poçt ünvanı daxil edin';
    } elseif ($id === 0 && $password === '') {
        $error = 'Yeni istifadəçi üçün şifrə tələb olunur';
    } elseif ($password !== '' && !validatePassword($password)) {
        $error = 'Şifrə ən azı 6 simvol olmalı və böyük hərf, kiçik hərf və rəqəm ehtiva etməlidir';
    } else {
        // E-poçt benzersizliğini kontrol et
        $checkSql = 'SELECT id FROM admins WHERE email = ?';
        $checkParams = [$email];
        if ($id > 0) {
            $checkSql .= ' AND id != ?';
            $checkParams[] = $id;
        }
        
        $stmt = secureQuery($pdo, $checkSql, $checkParams);
        if ($stmt->fetch()) {
            $error = 'Bu e-poçt ünvanı artıq istifadə olunur';
        } else {
            try {
                if ($id > 0) {
                    // Güncelleme
                    if ($password !== '') {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = secureQuery($pdo, 'UPDATE admins SET name=?, email=?, password_hash=?, is_active=? WHERE id=?', 
                                          [$name, $email, $passwordHash, $isActive, $id]);
                    } else {
                        $stmt = secureQuery($pdo, 'UPDATE admins SET name=?, email=?, is_active=? WHERE id=?', 
                                          [$name, $email, $isActive, $id]);
                    }
                } else {
                    // Yeni ekleme
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = secureQuery($pdo, 'INSERT INTO admins (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)', 
                                      [$name, $email, $passwordHash, $isActive]);
                }
                header('Location: users.php');
                exit;
            } catch (Exception $e) {
                $error = 'Veritabanı xətası';
            }
        }
    }
}

// Mevcut kullanıcı bilgilerini al
$user = [
    'id' => $id,
    'name' => '',
    'email' => '',
    'is_active' => 1
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: $user;
}

// Ana admin kontrolü
$isMainAdmin = ($user['email'] === 'admindecorss@gmail.com');

include __DIR__ . '/includes/layout_header.php';
?>

  <h2><?= $id ? 'İstifadəçi Düzenlə' : 'Yeni İstifadəçi' ?></h2>
  
  <div class="section-card">
    <?php if (!empty($error)): ?>
      <div style="background:var(--danger);color:#fff;padding:12px;border-radius:8px;margin-bottom:16px">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>
    
    <form method="post" action="" class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
      
      <div>
        <label>Ad *</label>
        <input type="text" name="name" value="<?=encodeHtml($user['name'])?>" required>
      </div>
      
      <div>
        <label>E-poçt *</label>
        <?php if ($isMainAdmin): ?>
          <input type="email" name="email" value="<?=encodeHtml($user['email'])?>" readonly style="background:#1a1a1a;color:#666">
          <small style="color:var(--warning);font-size:12px">Ana admin e-poçtu dəyişdirilə bilməz</small>
        <?php else: ?>
          <input type="email" name="email" value="<?=encodeHtml($user['email'])?>" required>
        <?php endif; ?>
      </div>
      
      <div>
        <label>Şifrə <?= $id ? '(boş buraxın, dəyişməsin)' : '*' ?></label>
        <input type="password" name="password" <?= $id ? '' : 'required' ?> placeholder="<?= $id ? 'Yalnız dəyişdirmək istəyirsinizsə daxil edin' : 'Minimum 6 simvol' ?>">
        <?php if ($id): ?>
          <small style="color:var(--muted);font-size:12px">Boş buraxarsanız, mövcud şifrə qalacaq</small>
        <?php endif; ?>
      </div>
      
      <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px">
        <?php if ($isMainAdmin): ?>
          <input type="checkbox" name="is_active" id="is_active" checked disabled>
          <label for="is_active" style="margin:0;color:var(--muted)">Aktiv istifadəçi (Ana admin həmişə aktivdir)</label>
          <input type="hidden" name="is_active" value="1">
        <?php else: ?>
          <input type="checkbox" name="is_active" id="is_active" <?=$user['is_active'] ? 'checked' : ''?>>
          <label for="is_active" style="margin:0">Aktiv istifadəçi (giriş edə bilər)</label>
        <?php endif; ?>
      </div>
      
      <div style="grid-column:1/-1;display:flex;gap:12px;margin-top:16px">
        <button type="submit" class="btn">Yadda saxla</button>
        <a href="users.php" class="btn secondary">Geri</a>
      </div>
    </form>
    
    <?php if ($id > 0): ?>
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid #111827">
      <h4 style="margin:0 0 10px 0;color:var(--muted)">İstifadəçi Məlumatları</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:14px">
        <div>
          <strong>Qeydiyyat tarixi:</strong><br>
          <?=date('d.m.Y H:i', strtotime($user['created_at']))?>
        </div>
        <div>
          <strong>Son giriş:</strong><br>
          <?php if ($user['last_login_at']): ?>
            <?=date('d.m.Y H:i', strtotime($user['last_login_at']))?>
          <?php else: ?>
            <span style="color:var(--muted)">Heç vaxt</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
