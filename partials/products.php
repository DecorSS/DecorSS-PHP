<?php
require_once __DIR__ . '/../includes/config.php';

$sql = "SELECT p.name, p.price, p.price_note, p.image_path FROM products p WHERE p.is_active = 1 ORDER BY p.created_at DESC";
$stmt = secureQuery($pdo, $sql);
$products = $stmt->fetchAll();

foreach ($products as $p): ?>
    <div class="product-item">
        <img src="<?php echo encodeHtml($p['image_path']); ?>" alt="<?php echo encodeHtml($p['name']); ?>" class="product-image">
        <h3><?php echo encodeHtml($p['name']); ?></h3>
        <p><?php echo number_format((float)$p['price'], 2); ?> ₼ <?php echo $p['price_note'] ? '(' . encodeHtml($p['price_note']) . ')' : ''; ?></p>
        <button>Əlavə et</button>
    </div>
<?php endforeach; ?>

