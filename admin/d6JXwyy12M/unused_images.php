<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

// Toplu silme işlemi
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    ensureCsrf();
    $action = validateAndSanitize($_POST['action'], 'alphanumeric', 20);
    $imagePaths = $_POST['image_paths'] ?? [];
    
    // Rate limiting
    if (!checkRateLimit('delete_images_' . $_SESSION['admin_id'], 5, 300)) {
        $error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
    } elseif ($action === 'delete_selected' && !empty($imagePaths) && is_array($imagePaths)) {
        $deletedCount = 0;
        $errors = [];
        
        foreach ($imagePaths as $imagePath) {
            // Input validation
            $imagePath = validateAndSanitize($imagePath, 'string', 500);
            
            // Güvenlik kontrolü
            if (!validateFilePath($imagePath, 'assets/images/Dekorlar/') || !str_contains($imagePath, 'product_')) {
                continue;
            }
            
            // Kullanılıp kullanılmadığını kontrol et
            $stmt = secureQuery($pdo, 'SELECT COUNT(*) FROM products WHERE image_path = ?', [$imagePath]);
            $isUsed = $stmt->fetchColumn() > 0;
            
            if (!$isUsed) {
                $fullPath = dirname(__DIR__, 2) . '/' . $imagePath;
                if (is_file($fullPath) && unlink($fullPath)) {
                    $deletedCount++;
                } else {
                    $errors[] = $imagePath;
                }
            }
        }
        
        if ($deletedCount > 0) {
            $success = "$deletedCount görsel silindi";
        }
        if (!empty($errors)) {
            $error = count($errors) . " görsel silinə bilmədi";
        }
    }
}

// Kullanılmayan görselleri bul
$uploadDir = dirname(__DIR__, 2) . '/assets/images/Dekorlar/';
$allFiles = glob($uploadDir . 'product_*');
$unusedImages = [];

foreach ($allFiles as $file) {
    $relativePath = 'assets/images/Dekorlar/' . basename($file);
    
    // Veritabanında kullanılıp kullanılmadığını kontrol et
    $stmt = secureQuery($pdo, 'SELECT COUNT(*) FROM products WHERE image_path = ?', [$relativePath]);
    $isUsed = $stmt->fetchColumn() > 0;
    
    if (!$isUsed) {
        $unusedImages[] = [
            'path' => $relativePath,
            'filename' => basename($file),
            'size' => filesize($file),
            'modified' => filemtime($file)
        ];
    }
}

// Dosya boyutuna göre sırala (büyükten küçüğe)
usort($unusedImages, function($a, $b) {
    return $b['size'] - $a['size'];
});

$csrf = csrfToken();
include __DIR__ . '/includes/layout_header.php';
?>

  <h2>İstifadə Olunmayan Görseller</h2>
  
  <div class="section-card">
    <?php if (!empty($success)): ?>
      <div style="background:var(--success);color:#fff;padding:12px;border-radius:8px;margin-bottom:16px">
        <?=htmlspecialchars($success)?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div style="background:var(--danger);color:#fff;padding:12px;border-radius:8px;margin-bottom:16px">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>
    
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
      <div style="color:var(--muted);font-size:14px;display:flex;align-items:center">
        Toplam: <?=count($unusedImages)?> istifadə olunmayan görsel
        <?php if (!empty($unusedImages)): ?>
          (<?=number_format(array_sum(array_column($unusedImages, 'size')) / 1024 / 1024, 2)?> MB)
        <?php endif; ?>
      </div>
      <?php if (!empty($unusedImages)): ?>
        <form method="post" action="" onsubmit="return confirm('Seçilmiş görselleri silmək istədiyinizə əminsiniz?')">
          <input type="hidden" name="_csrf" value="<?=$csrf?>">
          <input type="hidden" name="action" value="delete_selected">
          <div id="selected-images"></div>
          <button type="submit" class="btn danger" id="delete-selected-btn" style="display:none">Seçilmişləri Sil</button>
        </form>
      <?php endif; ?>
    </div>
    
    <?php if (empty($unusedImages)): ?>
      <div style="text-align:center;color:var(--muted);padding:40px">
        <div style="font-size:48px;margin-bottom:16px">🎉</div>
        <h3>Əla! İstifadə olunmayan görsel yoxdur</h3>
        <p>Sistem tamamilə təmizdir</p>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:16px">
        <?php foreach($unusedImages as $img): ?>
          <div class="image-card" style="border:1px solid #111827;border-radius:8px;overflow:hidden;background:#0b1222">
            <div style="position:relative">
              <img src="../../<?=encodeHtml($img['path'])?>" alt="<?=encodeHtml($img['filename'])?>" 
                   style="width:100%;height:150px;object-fit:cover;cursor:pointer" 
                   onclick="openImageModal('../../<?=encodeHtml($img['path'])?>', '<?=encodeHtml($img['filename'])?>')">
              <input type="checkbox" class="image-checkbox" value="<?=encodeHtml($img['path'])?>" 
                     style="position:absolute;top:8px;left:8px;transform:scale(1.2)">
            </div>
            <div style="padding:12px">
              <div style="font-size:12px;color:var(--muted);margin-bottom:4px">
                <?=encodeHtml($img['filename'])?>
              </div>
              <div style="font-size:11px;color:var(--muted)">
                <?=number_format($img['size'] / 1024, 1)?> KB
              </div>
              <div style="font-size:11px;color:var(--muted)">
                <?=encodeHtml(date('d.m.Y H:i', $img['modified']))?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Image Modal -->
  <div id="imageModal" class="modal" style="display:none;position:fixed;z-index:1000;padding-top:100px;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.9);align-items:center;justify-content:center;opacity:0;transition:opacity 0.5s ease">
    <span class="close" style="position:absolute;top:15px;right:35px;color:#fff;font-size:40px;font-weight:bold;transition:0.3s;cursor:pointer">&times;</span>
    <img class="modal-content" id="modalImg" style="margin:auto;display:block;width:80%;max-width:700px;border-radius:15px;object-fit:contain;transform:scale(0.8);transition:transform 0.4s ease">
    <div id="modalCaption" style="margin:auto;display:block;width:80%;max-width:700px;text-align:center;color:#ccc;padding:10px 0;height:150px"></div>
  </div>

<script>
// Checkbox işlemleri
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.image-checkbox');
    const deleteBtn = document.getElementById('delete-selected-btn');
    const selectedDiv = document.getElementById('selected-images');
    
    function updateSelected() {
        const checked = document.querySelectorAll('.image-checkbox:checked');
        if (checked.length > 0) {
            deleteBtn.style.display = 'inline-block';
            selectedDiv.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'image_paths[]';
                input.value = cb.value;
                selectedDiv.appendChild(input);
            });
        } else {
            deleteBtn.style.display = 'none';
            selectedDiv.innerHTML = '';
        }
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelected);
    });
    
    // Select all checkbox
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'btn secondary';
    selectAllBtn.textContent = 'Hamısını Seç';
    selectAllBtn.style.marginRight = '8px';
    selectAllBtn.onclick = function() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        updateSelected();
        this.textContent = allChecked ? 'Hamısını Seç' : 'Seçimi Ləğv Et';
    };
    
    if (deleteBtn && deleteBtn.parentNode) {
        deleteBtn.parentNode.insertBefore(selectAllBtn, deleteBtn);
    }
});

// Modal functionality
function openImageModal(imgSrc, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImg');
    const modalCaption = document.getElementById('modalCaption');
    
    modalImg.src = imgSrc;
    modalCaption.innerHTML = caption;
    modal.style.display = 'flex';
    setTimeout(() => { 
        modal.style.opacity = '1';
        modalImg.style.transform = 'scale(1)';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImg');
    modal.style.opacity = '0';
    modalImg.style.transform = 'scale(0.8)';
    setTimeout(() => { modal.style.display = 'none'; }, 400);
}

// Close modal events
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

document.getElementById('imageModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

document.querySelector('.close').addEventListener('click', closeModal);
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
