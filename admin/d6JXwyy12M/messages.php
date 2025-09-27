<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

// Tab seçimi
$tab = validateAndSanitize($_GET['tab'] ?? 'all', 'alphanumeric', 10);
$q = validateAndSanitize($_GET['q'] ?? '', 'string', 100);

// Toplu işlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ensureCsrf();
    $action = validateAndSanitize($_POST['action'], 'string', 20);
    $messageIds = $_POST['message_ids'] ?? [];
    
    // Rate limiting
    if (!checkRateLimit('bulk_action_' . $_SESSION['admin_id'], 20, 300)) {
        $error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
    } elseif (!empty($messageIds) && is_array($messageIds)) {
        // Input validation
        $messageIds = array_map(function($id) {
            return validateAndSanitize($id, 'int');
        }, $messageIds);
        $messageIds = array_filter($messageIds, function($id) {
            return $id > 0;
        });
        
        if (!empty($messageIds)) {
            $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';
            
            switch ($action) {
                case 'mark_read':
                    $stmt = secureQuery($pdo, "UPDATE contact_messages SET is_read = 1 WHERE id IN ($placeholders)", $messageIds);
                    break;
                case 'mark_unread':
                    $stmt = secureQuery($pdo, "UPDATE contact_messages SET is_read = 0 WHERE id IN ($placeholders)", $messageIds);
                    break;
                case 'delete':
                    $stmt = secureQuery($pdo, "DELETE FROM contact_messages WHERE id IN ($placeholders)", $messageIds);
                    break;
            }
        }
    }
    header('Location: messages.php?tab=' . urlencode($tab));
    exit;
}

// Tab'a göre sorgu
$sql = 'SELECT id, name, email, message, created_at, is_read FROM contact_messages';
$where = '';
$params = [];

if ($tab === 'read') {
    $where = ' WHERE is_read = 1';
} elseif ($tab === 'unread') {
    $where = ' WHERE is_read = 0';
}

if ($q !== '') { 
    $where .= ($where ? ' AND' : ' WHERE') . ' (name LIKE ? OR email LIKE ? OR message LIKE ?)'; 
    $params = array_merge($params, ["%{$q}%", "%{$q}%", "%{$q}%"]);
}

$order = ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql . $where . $order);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrfToken();
include __DIR__ . '/includes/layout_header.php';
?>
  <h2>Mesajlar</h2>
  
  
  <!-- Tab Navigation -->
  <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #111827">
    <a href="?tab=all" class="tab-btn <?=$tab==='all'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Tümü</a>
    <a href="?tab=read" class="tab-btn <?=$tab==='read'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Oxunmuşlar</a>
    <a href="?tab=unread" class="tab-btn <?=$tab==='unread'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Oxunmamışlar</a>
  </div>
  
  <div class="section-card">
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
      <form method="get" action="" style="display:flex;gap:8px">
        <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Ad, e-poçt və ya mesaj axtar..." style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px;min-width:250px">
        <button type="submit" class="btn secondary">Axtar</button>
      </form>
      <div style="color:var(--muted);font-size:14px">
        Toplam: <?=count($rows)?> mesaj
      </div>
    </div>
    
    <!-- Toplu İşlemler -->
    <?php if (!empty($rows)): ?>
    <div style="margin-bottom:16px;display:flex;gap:8px;align-items:center">
      <input type="checkbox" id="select-all" onchange="toggleAll(this)">
      <label for="select-all" style="margin:0;color:var(--muted)">Hamısını seç</label>
      
      <select id="bulk-action" style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:6px 12px">
        <option value="">Əməliyyat seçin</option>
        <?php if ($tab === 'unread' || $tab === 'all'): ?>
          <option value="mark_read">Oxunmuş kimi işarələ</option>
        <?php endif; ?>
        <?php if ($tab === 'read' || $tab === 'all'): ?>
          <option value="mark_unread">Oxunmamış kimi işarələ</option>
        <?php endif; ?>
        <option value="delete">Sil</option>
      </select>
      
      <button type="button" class="btn secondary" onclick="applyBulkAction()">Tətbiq et</button>
    </div>
    <?php endif; ?>
    
    <form method="post" action="" id="bulk-form" style="display:none">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" id="hidden-action">
      <div id="hidden-checkboxes"></div>
    </form>
    
    <table class="table">
      <thead>
        <tr>
          <th style="width:40px">
            <input type="checkbox" id="header-checkbox" onchange="toggleAll(this)">
          </th>
          <th>Ad</th>
          <th>E-poçt</th>
          <th>Mesaj</th>
          <th>Status</th>
          <th>Tarix</th>
          <th>Əməliyyatlar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr style="background:<?=$r['is_read'] ? 'transparent' : '#0b1222'?>">
            <td>
              <input type="checkbox" name="message_ids[]" value="<?=$r['id']?>" class="message-checkbox">
            </td>
            <td><?=encodeHtml($r['name'])?></td>
            <td>
              <a href="mailto:<?=encodeHtml($r['email'])?>" style="color:var(--primary)">
                <?=encodeHtml($r['email'])?>
              </a>
            </td>
            <td>
              <div style="max-width:300px;word-wrap:break-word">
                <?=encodeHtml($r['message'])?>
              </div>
            </td>
            <td>
              <span style="background:<?=$r['is_read'] ? 'var(--success)' : 'var(--warning)'?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$r['is_read'] ? 'Oxunub' : 'Oxunmayıb'?>
              </span>
            </td>
            <td><?=date('d.m.Y H:i', strtotime($r['created_at']))?></td>
            <td>
              <div style="display:flex;gap:6px">
                <?php if ($tab === 'read'): ?>
                  <!-- Oxunmuşlar tabında: Oxunmamış kimi işarələ + Sil -->
                  <button onclick="markMessage(<?=$r['id']?>, 'unread')" class="btn secondary" style="padding:4px 8px;font-size:12px">Oxunmamış kimi işarələ</button>
                  <button onclick="deleteMessage(<?=$r['id']?>)" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
                <?php elseif ($tab === 'unread'): ?>
                  <!-- Oxunmamışlar tabında: Oxunmuş kimi işarələ + Sil -->
                  <button onclick="markMessage(<?=$r['id']?>, 'read')" class="btn secondary" style="padding:4px 8px;font-size:12px">Oxunmuş kimi işarələ</button>
                  <button onclick="deleteMessage(<?=$r['id']?>)" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
                <?php else: ?>
                  <!-- Tümü tabında: Mesaj durumuna göre buton + Sil -->
                  <?php if ($r['is_read']): ?>
                    <button onclick="markMessage(<?=$r['id']?>, 'unread')" class="btn secondary" style="padding:4px 8px;font-size:12px">Oxunmamış kimi işarələ</button>
                  <?php else: ?>
                    <button onclick="markMessage(<?=$r['id']?>, 'read')" class="btn secondary" style="padding:4px 8px;font-size:12px">Oxunmuş kimi işarələ</button>
                  <?php endif; ?>
                  <button onclick="deleteMessage(<?=$r['id']?>)" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="7" style="text-align:center;color:var(--muted);padding:40px">
              Heç bir mesaj tapılmadı
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function applyBulkAction() {
    const action = document.getElementById('bulk-action').value;
    const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
    
    if (!action) {
        alert('Zəhmət olmasa əməliyyat seçin');
        return;
    }
    
    if (checkedBoxes.length === 0) {
        alert('Zəhmət olmasa ən azı bir mesaj seçin');
        return;
    }
    
    let confirmMessage = '';
    switch(action) {
        case 'mark_read':
            confirmMessage = 'Seçilmiş mesajları oxunmuş kimi işarələmək istədiyinizə əminsiniz?';
            break;
        case 'mark_unread':
            confirmMessage = 'Seçilmiş mesajları oxunmamış kimi işarələmək istədiyinizə əminsiniz?';
            break;
        case 'delete':
            confirmMessage = 'Seçilmiş mesajları silmək istədiyinizə əminsiniz? Bu əməliyyat geri alına bilməz!';
            break;
    }
    
    if (confirm(confirmMessage)) {
        // Seçilen checkbox'ları gizli forma ekle
        const hiddenCheckboxes = document.getElementById('hidden-checkboxes');
        hiddenCheckboxes.innerHTML = '';
        
        checkedBoxes.forEach(cb => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'message_ids[]';
            hiddenInput.value = cb.value;
            hiddenCheckboxes.appendChild(hiddenInput);
        });
        
        // Action'ı gizli forma ekle
        document.getElementById('hidden-action').value = action;
        
        // Formu gönder
        document.getElementById('bulk-form').submit();
    }
}

// Tekil mesaj işlemleri
function markMessage(messageId, action) {
    const actionText = action === 'read' ? 'oxunmuş' : 'oxunmamış';
    if (confirm(`Bu mesajı ${actionText} kimi işarələmək istədiyinizə əminsiniz?`)) {
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        // CSRF token'ı bul
        const csrfElement = document.querySelector('input[name="_csrf"]');
        if (!csrfElement) {
            alert('CSRF token bulunamadı! Sayfayı yenileyin.');
            return;
        }
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrfElement.value;
        form.appendChild(csrfInput);
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action === 'read' ? 'mark_read' : 'mark_unread';
        form.appendChild(actionInput);
        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'message_ids[]';
        messageInput.value = messageId;
        form.appendChild(messageInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteMessage(messageId) {
    if (confirm('Bu mesajı silmək istədiyinizə əminsiniz? Bu əməliyyat geri alına bilməz!')) {
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        // CSRF token'ı bul
        const csrfElement = document.querySelector('input[name="_csrf"]');
        if (!csrfElement) {
            alert('CSRF token bulunamadı! Sayfayı yenileyin.');
            return;
        }
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrfElement.value;
        form.appendChild(csrfInput);
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'message_ids[]';
        messageInput.value = messageId;
        form.appendChild(messageInput);
        document.body.appendChild(form);
        form.submit();
    }
}


// Tab active style
document.querySelectorAll('.tab-btn').forEach(btn => {
    if (btn.classList.contains('active')) {
        btn.style.background = 'var(--primary)';
        btn.style.color = '#fff';
    } else {
        btn.style.background = 'transparent';
        btn.style.color = 'var(--muted)';
    }
});

</script>
<?php include __DIR__ . '/includes/layout_footer.php'; ?>
