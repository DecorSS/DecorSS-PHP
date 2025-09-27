<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
	ensureCsrf();
	$id = validateAndSanitize($_POST['id'] ?? 0, 'int');
	
	// Rate limiting
	if (!checkRateLimit('delete_product_' . $_SESSION['admin_id'], 10, 300)) {
		$error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
	} elseif ($id > 0) {
		$stmt = secureQuery($pdo, 'DELETE FROM products WHERE id = ?', [$id]);
	}
	header('Location: products.php');
	exit;
}

$q = validateAndSanitize($_GET['q'] ?? '', 'string', 100);
$sql = 'SELECT p.id, p.name, p.price, p.price_note, p.image_path, p.is_active, p.created_at, c.name AS category
		FROM products p LEFT JOIN categories c ON c.id = p.category_id';
$where = '';
$params = [];
if ($q !== '') { $where = ' WHERE p.name LIKE ?'; $params[] = "%{$q}%"; }
$order = ' ORDER BY p.created_at DESC';
$stmt = secureQuery($pdo, $sql . $where . $order, $params);
$rows = $stmt->fetchAll();
$csrf = csrfToken();
?>
<?php include __DIR__ . '/includes/layout_header.php'; ?>
  <h2>Məhsullar</h2>
  <div class="section-card">
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
      <form method="get" action="" style="display:flex;gap:8px">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Məhsul axtar..." style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px;min-width:200px">
        <button type="submit" class="btn secondary">Axtar</button>
      </form>
      <a href="product_form.php" class="btn">Yeni Məhsul</a>
    </div>
    
    <table class="table">
      <thead>
        <tr>
          <th>Şəkil</th>
          <th>Ad</th>
          <th>Qiymət</th>
          <th>Kateqoriya</th>
          <th>Status</th>
          <th>Tarix</th>
          <th>Əməliyyatlar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>
              <img src="../../<?=encodeHtml($r['image_path'])?>" alt="<?=encodeHtml($r['name'])?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px">
            </td>
            <td><?=encodeHtml($r['name'])?></td>
            <td><?=number_format((float)$r['price'], 2)?> ₼ <?= $r['price_note'] ? '<br><small style="color:var(--muted)">(' . encodeHtml($r['price_note']) . ')</small>' : '' ?></td>
            <td><?=encodeHtml($r['category'] ?? '—')?></td>
            <td>
              <span style="background:<?=$r['is_active'] ? 'var(--success)' : 'var(--danger)'?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$r['is_active'] ? 'Aktiv' : 'Deaktiv'?>
              </span>
            </td>
            <td><?=encodeHtml(date('d.m.Y', strtotime($r['created_at'])))?></td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="product_form.php?id=<?=$r['id']?>" class="btn secondary" style="padding:4px 8px;font-size:12px">Düzenle</a>
                <form method="post" action="" onsubmit="return confirm('Bu məhsulu silmək istədiyinizə əminsiniz?')" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?=$csrf?>">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php include __DIR__ . '/includes/layout_footer.php'; ?>


