<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

// Silme işlemi
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    ensureCsrf();
    $id = validateAndSanitize($_POST['id'] ?? 0, 'int');
    
    // Rate limiting
    if (!checkRateLimit('delete_user_' . $_SESSION['admin_id'], 5, 300)) {
        $error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
    } elseif ($id > 0) {
        // Ana admin kontrolü
        $stmt = secureQuery($pdo, 'SELECT email FROM admins WHERE id = ?', [$id]);
        $userEmail = $stmt->fetchColumn();
        
        if ($userEmail === 'admindecorss@gmail.com') {
            $error = 'Ana admin hesabı silinə bilməz';
        } elseif ($id === $_SESSION['admin_id']) {
            $error = 'Öz hesabınızı silə bilməzsiniz';
        } else {
            $stmt = secureQuery($pdo, 'DELETE FROM admins WHERE id = ?', [$id]);
            header('Location: users.php');
            exit;
        }
    }
}

// Arama
$q = validateAndSanitize($_GET['q'] ?? '', 'string', 100);
$sql = 'SELECT id, name, email, is_active, created_at, last_login_at FROM admins';
$where = '';
$params = [];

if ($q !== '') { 
    $where = ' WHERE name LIKE ? OR email LIKE ?'; 
    $params = ["%{$q}%", "%{$q}%"]; 
}

$order = ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql . $where . $order);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrfToken();
include __DIR__ . '/includes/layout_header.php';
?>

  <h2>İstifadəçilər</h2>
  
  <div class="section-card">
    <?php if (!empty($error)): ?>
      <div style="background:var(--danger);color:#fff;padding:12px;border-radius:8px;margin-bottom:16px">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>
    
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
      <form method="get" action="" style="display:flex;gap:8px">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Ad və ya e-poçt axtar..." style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px;min-width:250px">
        <button type="submit" class="btn secondary">Axtar</button>
      </form>
      <div style="display:flex;gap:8px">
        <a href="user_form.php" class="btn">Yeni İstifadəçi</a>
        <div style="color:var(--muted);font-size:14px;display:flex;align-items:center">
          Toplam: <?=count($rows)?> istifadəçi
        </div>
      </div>
    </div>
    
    <table class="table">
      <thead>
        <tr>
          <th>Ad</th>
          <th>E-poçt</th>
          <th>Status</th>
          <th>Qeydiyyat</th>
          <th>Son Giriş</th>
          <th>Əməliyyatlar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:32px;height:32px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold">
                  <?=strtoupper(mb_substr($r['name'], 0, 1, 'UTF-8'))?>
                </div>
                <?=encodeHtml($r['name'])?>
                <?php if ($r['email'] === 'admindecorss@gmail.com'): ?>
                  <span style="background:var(--warning);color:#fff;padding:2px 6px;border-radius:10px;font-size:10px">Ana Admin</span>
                <?php elseif ($r['id'] === $_SESSION['admin_id']): ?>
                  <span style="background:var(--info);color:#fff;padding:2px 6px;border-radius:10px;font-size:10px">Siz</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <a href="mailto:<?=encodeHtml($r['email'])?>" style="color:var(--primary)">
                <?=encodeHtml($r['email'])?>
              </a>
            </td>
            <td>
              <span style="background:<?=$r['is_active'] ? 'var(--success)' : 'var(--danger)'?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$r['is_active'] ? 'Aktiv' : 'Deaktiv'?>
              </span>
            </td>
            <td><?=date('d.m.Y H:i', strtotime($r['created_at']))?></td>
            <td>
              <?php if ($r['last_login_at']): ?>
                <span style="color:var(--success)"><?=date('d.m.Y H:i', strtotime($r['last_login_at']))?></span>
              <?php else: ?>
                <span style="color:var(--muted)">Heç vaxt</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="user_form.php?id=<?=$r['id']?>" class="btn secondary" style="padding:4px 8px;font-size:12px">Düzenle</a>
                <?php if ($r['email'] === 'admindecorss@gmail.com'): ?>
                  <span style="color:var(--muted);font-size:12px;padding:4px 8px">Silinə bilməz</span>
                <?php elseif ($r['id'] !== $_SESSION['admin_id']): ?>
                  <form method="post" action="" onsubmit="return confirm('Bu istifadəçini silmək istədiyinizə əminsiniz?')" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?=$csrf?>">
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
                  </form>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px;padding:4px 8px">Silinə bilməz</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="6" style="text-align:center;color:var(--muted);padding:40px">
              Heç bir istifadəçi tapılmadı
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
