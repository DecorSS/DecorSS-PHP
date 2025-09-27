<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

// Eski yüklenen görselleri temizle (24 saatten eski)
$uploadDir = dirname(__DIR__, 2) . '/assets/images/Dekorlar/';
$files = glob($uploadDir . 'product_*');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 86400) { // 24 saat
        unlink($file);
    }
}

$id = validateAndSanitize($_GET['id'] ?? 0, 'int');
$csrf = csrfToken();
$categories = secureQuery($pdo, 'SELECT id, name FROM categories ORDER BY name')->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	ensureCsrf();
	$id = validateAndSanitize($_POST['id'] ?? 0, 'int');
	$name = validateAndSanitize($_POST['name'] ?? '', 'string', 255);
	$price = validateAndSanitize($_POST['price'] ?? 0, 'float');
	$priceNote = validateAndSanitize($_POST['price_note'] ?? '', 'string', 100);
	$imagePath = validateAndSanitize($_POST['image_path'] ?? '', 'string', 500);
	$categoryId = $_POST['category_id'] !== '' ? validateAndSanitize($_POST['category_id'], 'int') : null;
	$isActive = isset($_POST['is_active']) ? 1 : 0;

	// Rate limiting
	if (!checkRateLimit('product_form_' . $_SESSION['admin_id'], 10, 300)) {
		$error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
	} elseif ($name === '' || $imagePath === '') { 
		$error = 'İsim ve görsel zorunlu'; 
	} elseif (!validateFilePath($imagePath, 'assets/images/Dekorlar/')) {
		$error = 'Güvenli olmayan görsel yolu';
	} else {
		try {
			if ($id > 0) {
				$stmt = secureQuery($pdo, 'UPDATE products SET name=?, price=?, price_note=?, image_path=?, category_id=?, is_active=? WHERE id=?', 
								  [$name, $price, $priceNote, $imagePath, $categoryId, $isActive, $id]);
			} else {
				$stmt = secureQuery($pdo, 'INSERT INTO products (name, price, price_note, image_path, category_id, is_active) VALUES (?, ?, ?, ?, ?, ?)', 
								  [$name, $price, $priceNote, $imagePath, $categoryId, $isActive]);
				$id = (int)$pdo->lastInsertId();
			}
			header('Location: products.php'); exit;
		} catch (Exception $e) {
			$error = 'Veritabanı xətası';
		}
	}
}

$product = [
  'id'=>$id, 'name'=>'', 'price'=>'0.00', 'price_note'=>'', 'image_path'=>'', 'category_id'=>'', 'is_active'=>1
];
if ($id > 0) {
	$stmt = secureQuery($pdo, 'SELECT * FROM products WHERE id = ?', [$id]);
	$product = $stmt->fetch() ?: $product;
}
?>
<?php include __DIR__ . '/includes/layout_header.php'; ?>
  <h2><?= $id ? 'Məhsul Düzenlə' : 'Yeni Məhsul' ?></h2>
  
  <div class="section-card">
    <?php if (!empty($error)): ?>
      <div style="background:var(--danger);color:#fff;padding:12px;border-radius:8px;margin-bottom:16px">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>
    
    <form method="post" action="" class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
      
      <div>
        <label>Ad *</label>
        <input type="text" name="name" value="<?=encodeHtml($product['name'])?>" required>
      </div>
      
      <div>
        <label>Qiymət *</label>
        <input type="number" step="0.01" min="0" name="price" value="<?=encodeHtml((string)$product['price'])?>" required>
      </div>
      
      <div>
        <label>Qiymət Qeydi</label>
        <input type="text" name="price_note" value="<?=encodeHtml($product['price_note'])?>" placeholder="Məsələn: 1 ədədi, 12-li dəst">
      </div>
      
      <div>
        <label>Kateqoriya</label>
        <select name="category_id">
          <option value="">— Kateqoriya seçin</option>
          <?php foreach($categories as $c): ?>
            <option value="<?=encodeHtml($c['id'])?>" <?=$product['category_id']==$c['id']?'selected':''?>><?=encodeHtml($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div style="grid-column:1/-1">
        <label>Şəkil *</label>
        <div id="image-upload-area" style="border:2px dashed #111827;border-radius:8px;padding:20px;text-align:center;cursor:pointer;background:#0b1222;transition:border-color 0.3s" onclick="document.getElementById('image-input').click()">
          <div id="upload-text">
            <div style="font-size:24px;margin-bottom:8px">📁</div>
            <div>Şəkil yükləmək üçün bura klikləyin və ya sürükləyin</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">JPG, PNG, GIF, WebP (maksimum 10MB)</div>
          </div>
        </div>
        <input type="file" id="image-input" accept="image/*" style="display:none">
            <input type="hidden" name="image_path" id="image-path" value="<?=encodeHtml($product['image_path'])?>" required>
        
        <!-- Yeni yüklenen görsel için ayrı div -->
        <div id="new-image-preview" style="margin-top:12px;display:none">
          <div style="font-size:16px;color:var(--success);margin-bottom:8px">✓ Yeni şəkil yükləndi</div>
          <img id="preview-img" style="max-width:400px;max-height:400px;border-radius:6px;border:1px solid var(--success);cursor:pointer">
        </div>
      </div>
      
      <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="is_active" id="is_active" <?=$product['is_active'] ? 'checked' : ''?>>
        <label for="is_active" style="margin:0">Aktiv məhsul (səhifədə görünsün)</label>
      </div>
      
      <div style="grid-column:1/-1;display:flex;gap:12px;margin-top:16px">
        <button type="submit" class="btn">Yadda saxla</button>
        <a href="products.php" class="btn secondary">Geri</a>
      </div>
    </form>
    
    <div id="current-image-preview" style="margin-top:20px;padding-top:20px;border-top:1px solid #111827;display:none">
      <h4 style="margin:0 0 10px 0;color:var(--muted)">Cari şəkil:</h4>
      <img id="current-img" style="max-width:400px;border-radius:8px;border:1px solid #111827;cursor:pointer">
    </div>
  </div>

  <!-- Modal for image preview -->
  <div id="imageModal" class="modal" style="display:none;position:fixed;z-index:1000;padding-top:100px;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.9);align-items:center;justify-content:center;opacity:0;transition:opacity 0.5s ease">
    <span class="close" style="position:absolute;top:15px;right:35px;color:#fff;font-size:40px;font-weight:bold;transition:0.3s;cursor:pointer">&times;</span>
    <img class="modal-content" id="modalImg" style="margin:auto;display:block;width:80%;max-width:700px;border-radius:15px;object-fit:contain;transform:scale(0.8);transition:transform 0.4s ease">
    <div id="modalCaption" style="margin:auto;display:block;width:80%;max-width:700px;text-align:center;color:#ccc;padding:10px 0;height:150px"></div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('image-upload-area');
    const fileInput = document.getElementById('image-input');
    const imagePath = document.getElementById('image-path');
    const uploadText = document.getElementById('upload-text');
    const newImagePreview = document.getElementById('new-image-preview');
    const previewImg = document.getElementById('preview-img');
    const currentPreview = document.getElementById('current-image-preview');
    const currentImg = document.getElementById('current-img');
    
    // Modal elements
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImg');
    const modalCaption = document.getElementById('modalCaption');
    const closeBtn = document.querySelector('.close');
    
    // Mevcut resim varsa göster
    const currentPath = imagePath.value;
    if (currentPath) {
        currentImg.src = '../../' + currentPath;
        currentPreview.style.display = 'block';
    }
    
    // Drag & drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary)';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#111827';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#111827';
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });
    
    function handleFile(file) {
        // Dosya tipini kontrol et
        if (!file.type.startsWith('image/')) {
            alert('Yalnız şəkil faylları qəbul edilir');
            return;
        }
        
        // Dosya boyutunu kontrol et (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Fayl çox böyükdür (maksimum 10MB)');
            return;
        }
        
        // FormData oluştur
        const formData = new FormData();
        formData.append('image', file);
        
        // Upload
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                // Eski yüklenen görseli temizle
                if (imagePath.value && imagePath.value !== currentPath) {
                    cleanupImage(imagePath.value);
                }
                
                imagePath.value = data.path;
                previewImg.src = '../../' + data.path;
                uploadedImagePath = data.path; // Yüklenen görseli takip et
                // Yeni görsel önizlemesini göster (File Upload alanının altında)
                newImagePreview.style.display = 'block';
            } else {
                alert('Xəta: ' + data.error);
            }
        })
        .catch(error => {
            alert('Xəta: Şəbəkə problemi');
        });
    }
    
    // Görsel temizleme fonksiyonu
    function cleanupImage(imagePath) {
        if (imagePath && imagePath.startsWith('assets/images/Dekorlar/')) {
            fetch('cleanup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({image_path: imagePath})
            }).catch(error => {
                console.log('Cleanup error:', error);
            });
        }
    }
    
    // Sayfa terk edildiğinde yüklenen görseli temizle
    let uploadedImagePath = null;
    
    // Form submit edildiğinde temizleme yapma
    document.querySelector('form').addEventListener('submit', function() {
        uploadedImagePath = null; // Form submit edildi, temizleme yapma
    });
    
    // Sayfa terk edildiğinde temizlik
    window.addEventListener('beforeunload', function() {
        if (uploadedImagePath && imagePath.value === uploadedImagePath) {
            cleanupImage(uploadedImagePath);
        }
    });
    
    // Modal functionality
    function openModal(imgSrc, caption) {
        modalImg.src = imgSrc;
        modalCaption.innerHTML = caption;
        modal.style.display = 'flex';
        setTimeout(() => { 
            modal.style.opacity = '1';
            modalImg.style.transform = 'scale(1)';
        }, 10);
    }
    
    function closeModal() {
        modal.style.opacity = '0';
        modalImg.style.transform = 'scale(0.8)';
        setTimeout(() => { modal.style.display = 'none'; }, 400);
    }
    
    // Click events for images
    if (previewImg) {
        previewImg.addEventListener('click', function() {
            openModal(this.src, 'Yeni yüklənən şəkil');
        });
    }
    
    if (currentImg) {
        currentImg.addEventListener('click', function() {
            openModal(this.src, 'Cari şəkil');
        });
    }
    
    // Close modal events
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    // ESC key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>


