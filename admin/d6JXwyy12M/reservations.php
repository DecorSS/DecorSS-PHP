<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

// Tab seçimi
$tab = validateAndSanitize($_GET['tab'] ?? 'all', 'alphanumeric', 20);
$q = validateAndSanitize($_GET['q'] ?? '', 'string', 100);
$date_from = validateAndSanitize($_GET['date_from'] ?? '', 'string', 20);
$date_to = validateAndSanitize($_GET['date_to'] ?? '', 'string', 20);
$time_period = validateAndSanitize($_GET['time_period'] ?? '', 'string', 20);

// Toplu işlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ensureCsrf();
    $action = validateAndSanitize($_POST['action'], 'string', 20);
    $reservationIds = $_POST['reservation_ids'] ?? [];
    
    // Rate limiting
    if (!checkRateLimit('bulk_action_' . $_SESSION['admin_id'], 20, 300)) {
        $error = 'Çox sürətli əməliyyat. Zəhmət olmasa gözləyin.';
    } elseif (!empty($reservationIds) && is_array($reservationIds)) {
        // Input validation
        $reservationIds = array_map(function($id) {
            return validateAndSanitize($id, 'int');
        }, $reservationIds);
        $reservationIds = array_filter($reservationIds, function($id) {
            return $id > 0;
        });
        
        if (!empty($reservationIds)) {
            $placeholders = str_repeat('?,', count($reservationIds) - 1) . '?';
            
            switch ($action) {
                case 'confirm':
                    $stmt = secureQuery($pdo, "UPDATE reservations SET status = 'confirmed' WHERE id IN ($placeholders)", $reservationIds);
                    break;
                case 'cancel':
                    $stmt = secureQuery($pdo, "UPDATE reservations SET status = 'cancelled' WHERE id IN ($placeholders)", $reservationIds);
                    break;
                case 'delete':
                    $stmt = secureQuery($pdo, "DELETE FROM reservations WHERE id IN ($placeholders)", $reservationIds);
                    break;
            }
        }
    }
    header('Location: reservations.php?tab=' . urlencode($tab));
    exit;
}

// İstatistikler
$stats = [];
$stats['total'] = secureQuery($pdo, "SELECT COUNT(*) as count FROM reservations")->fetch()['count'];
$stats['today'] = secureQuery($pdo, "SELECT COUNT(*) as count FROM reservations WHERE DATE(reservation_date) = CURDATE()")->fetch()['count'];
$stats['active'] = secureQuery($pdo, "SELECT COUNT(*) as count FROM reservations WHERE reservation_date >= CURDATE()")->fetch()['count'];
$stats['expired'] = secureQuery($pdo, "SELECT COUNT(*) as count FROM reservations WHERE reservation_date < CURDATE()")->fetch()['count'];

// Popüler saatler
$popular_hours = secureQuery($pdo, "
    SELECT HOUR(reservation_time) as hour, COUNT(*) as count 
    FROM reservations 
    WHERE reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(reservation_time) 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll();

// Tab'a göre sorgu
$sql = 'SELECT id, name, email, reservation_date, reservation_time, notes, created_at, 
        CASE 
            WHEN reservation_date < CURDATE() THEN "expired"
            WHEN reservation_date = CURDATE() THEN "today"
            ELSE "future"
        END as time_status,
        COALESCE(status, "pending") as status
        FROM reservations';
$where = '';
$params = [];

// Tab filtreleri
if ($tab === 'active') {
    $where = ' WHERE reservation_date >= CURDATE()';
} elseif ($tab === 'expired') {
    $where = ' WHERE reservation_date < CURDATE()';
} elseif ($tab === 'today') {
    $where = ' WHERE DATE(reservation_date) = CURDATE()';
} elseif ($tab === 'confirmed') {
    $where = ' WHERE COALESCE(status, "pending") = "confirmed"';
} elseif ($tab === 'pending') {
    $where = ' WHERE COALESCE(status, "pending") = "pending"';
} elseif ($tab === 'cancelled') {
    $where = ' WHERE COALESCE(status, "pending") = "cancelled"';
}

// Arama filtresi
if ($q !== '') { 
    $where .= ($where ? ' AND' : ' WHERE') . ' (name LIKE ? OR email LIKE ? OR notes LIKE ?)'; 
    $params = array_merge($params, ["%{$q}%", "%{$q}%", "%{$q}%"]);
}

// Tarih aralığı filtresi
if ($date_from !== '') {
    $where .= ($where ? ' AND' : ' WHERE') . ' reservation_date >= ?';
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where .= ($where ? ' AND' : ' WHERE') . ' reservation_date <= ?';
    $params[] = $date_to;
}

// Saat aralığı filtresi
if ($time_period !== '') {
    switch ($time_period) {
        case 'morning':
            $where .= ($where ? ' AND' : ' WHERE') . ' HOUR(reservation_time) BETWEEN 6 AND 11';
            break;
        case 'afternoon':
            $where .= ($where ? ' AND' : ' WHERE') . ' HOUR(reservation_time) BETWEEN 12 AND 17';
            break;
        case 'evening':
            $where .= ($where ? ' AND' : ' WHERE') . ' HOUR(reservation_time) BETWEEN 18 AND 23';
            break;
    }
}

$order = ' ORDER BY reservation_date DESC, reservation_time DESC';
$stmt = $pdo->prepare($sql . $where . $order);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrfToken();
include __DIR__ . '/includes/layout_header.php';
?>
  <h2>Rezervasiyalar</h2>
  
  <!-- İstatistikler -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    <div class="section-card" style="text-align:center">
      <div style="font-size:24px;font-weight:bold;color:var(--primary)"><?=$stats['total']?></div>
      <div style="color:var(--muted);font-size:14px">Toplam</div>
    </div>
    <div class="section-card" style="text-align:center">
      <div style="font-size:24px;font-weight:bold;color:var(--success)"><?=$stats['today']?></div>
      <div style="color:var(--muted);font-size:14px">Bugün</div>
    </div>
    <div class="section-card" style="text-align:center">
      <div style="font-size:24px;font-weight:bold;color:var(--warning)"><?=$stats['active']?></div>
      <div style="color:var(--muted);font-size:14px">Aktiv</div>
    </div>
    <div class="section-card" style="text-align:center">
      <div style="font-size:24px;font-weight:bold;color:var(--muted)"><?=$stats['expired']?></div>
      <div style="color:var(--muted);font-size:14px">Vaxtı Keçmiş</div>
    </div>
  </div>

  <!-- Popüler Saatler -->
  <?php if (!empty($popular_hours)): ?>
  <div class="section-card" style="margin-bottom:24px">
    <h3 style="margin:0 0 16px 0">Popüler Saatler (Son 30 Gün)</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php foreach($popular_hours as $hour): ?>
        <div style="background:var(--primary);color:#fff;padding:8px 12px;border-radius:8px;font-size:14px">
          <?=sprintf('%02d:00', $hour['hour'])?> (<?=$hour['count']?>)
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Tab Navigation -->
  <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #111827;flex-wrap:wrap">
    <a href="?tab=all" class="tab-btn <?=$tab==='all'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Tümü</a>
    <a href="?tab=active" class="tab-btn <?=$tab==='active'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Aktiv</a>
    <a href="?tab=expired" class="tab-btn <?=$tab==='expired'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Vaxtı Keçmiş</a>
    <a href="?tab=today" class="tab-btn <?=$tab==='today'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Bugün</a>
    <a href="?tab=confirmed" class="tab-btn <?=$tab==='confirmed'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Təsdiqlənmiş</a>
    <a href="?tab=pending" class="tab-btn <?=$tab==='pending'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Gözləyən</a>
    <a href="?tab=cancelled" class="tab-btn <?=$tab==='cancelled'?'active':''?>" style="padding:8px 16px;border-radius:8px 8px 0 0;text-decoration:none;color:var(--muted);transition:0.3s">Ləğv edilmiş</a>
  </div>
  
  <div class="section-card">
    <!-- Gelişmiş Filtreler -->
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
      <form method="get" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
        
        <!-- Arama -->
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Ad, e-poçt və ya qeyd axtar..." style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px;min-width:200px">
        
        <!-- Tarih Aralığı -->
        <input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>" placeholder="Başlangıç" style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px">
        <input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>" placeholder="Bitiş" style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px">
        
        <!-- Saat Aralığı -->
        <select name="time_period" style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 12px">
          <option value="">Bütün saatlar</option>
          <option value="morning" <?=$time_period==='morning'?'selected':''?>>Səhər (06:00-11:59)</option>
          <option value="afternoon" <?=$time_period==='afternoon'?'selected':''?>>Günorta (12:00-17:59)</option>
          <option value="evening" <?=$time_period==='evening'?'selected':''?>>Axşam (18:00-23:59)</option>
        </select>
        
        <button type="submit" class="btn secondary">Axtar</button>
        <a href="?" class="btn secondary">Təmizlə</a>
      </form>
      
      <div style="color:var(--muted);font-size:14px;display:flex;align-items:center">
        Toplam: <?=count($rows)?> rezervasiya
      </div>
    </div>
    
    <!-- Toplu İşlemler -->
    <?php if (!empty($rows)): ?>
    <div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="checkbox" id="select-all" onchange="toggleAll(this)">
      <label for="select-all" style="margin:0;color:var(--muted)">Hamısını seç</label>
      
      <select id="bulk-action" style="background:#0b1222;color:#fff;border:1px solid #111827;border-radius:8px;padding:6px 12px">
        <option value="">Əməliyyat seçin</option>
        <option value="confirm">Təsdiqlə</option>
        <option value="cancel">Ləğv et</option>
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
          <th>Tarix</th>
          <th>Saat</th>
          <th>Status</th>
          <th>Qeydlər</th>
          <th>Göndərilmə</th>
          <th>Əməliyyatlar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <?php
          $isToday = $r['time_status'] === 'today';
          $isExpired = $r['time_status'] === 'expired';
          $isFuture = $r['time_status'] === 'future';
          $status = $r['status'];
          
          // Satır rengi
          $rowBg = 'transparent';
          if ($isToday) $rowBg = '#0b1222';
          if ($isExpired) $rowBg = '#1a0b0b';
          if ($status === 'cancelled') $rowBg = '#0b1a0b';
          ?>
          <tr style="background:<?=$rowBg?>">
            <td>
              <input type="checkbox" name="reservation_ids[]" value="<?=$r['id']?>" class="reservation-checkbox">
            </td>
            <td><?=encodeHtml($r['name'])?></td>
            <td>
              <a href="mailto:<?=encodeHtml($r['email'])?>" style="color:var(--primary)">
                <?=encodeHtml($r['email'])?>
              </a>
            </td>
            <td>
              <?php
              $dateColor = 'var(--primary)';
              if ($isToday) $dateColor = 'var(--warning)';
              if ($isExpired) $dateColor = 'var(--muted)';
              ?>
              <span style="background:<?=$dateColor?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=encodeHtml(date('d.m.Y', strtotime($r['reservation_date'])))?>
                <?php if ($isToday): ?>
                  <span style="font-size:10px">(BUGÜN)</span>
                <?php endif; ?>
              </span>
            </td>
            <td>
              <span style="background:var(--success);color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=encodeHtml(date('H:i', strtotime($r['reservation_time'])))?>
              </span>
            </td>
            <td>
              <?php
              $statusText = '';
              $statusColor = '';
              switch($status) {
                  case 'confirmed':
                      $statusText = 'Təsdiqlənmiş';
                      $statusColor = 'var(--success)';
                      break;
                  case 'cancelled':
                      $statusText = 'Ləğv edilmiş';
                      $statusColor = 'var(--danger)';
                      break;
                  default:
                      $statusText = 'Gözləyən';
                      $statusColor = 'var(--warning)';
                      break;
              }
              ?>
              <span style="background:<?=$statusColor?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$statusText?>
              </span>
            </td>
            <td>
              <?php if ($r['notes']): ?>
                <div style="max-width:200px;word-wrap:break-word;color:var(--muted)">
                  <?=encodeHtml($r['notes'])?>
                </div>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--muted)">
              <?=encodeHtml(date('d.m.Y H:i', strtotime($r['created_at'])))?>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if ($status !== 'confirmed'): ?>
                  <button onclick="updateReservationStatus(<?=$r['id']?>, 'confirmed')" class="btn secondary" style="padding:4px 8px;font-size:12px">Təsdiqlə</button>
                <?php endif; ?>
                <?php if ($status !== 'cancelled'): ?>
                  <button onclick="updateReservationStatus(<?=$r['id']?>, 'cancelled')" class="btn secondary" style="padding:4px 8px;font-size:12px">Ləğv et</button>
                <?php endif; ?>
                <button onclick="deleteReservation(<?=$r['id']?>)" class="btn danger" style="padding:4px 8px;font-size:12px">Sil</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="9" style="text-align:center;color:var(--muted);padding:40px">
              Heç bir rezervasiya tapılmadı
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.reservation-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function applyBulkAction() {
    const action = document.getElementById('bulk-action').value;
    const checkedBoxes = document.querySelectorAll('.reservation-checkbox:checked');
    
    if (!action) {
        alert('Zəhmət olmasa əməliyyat seçin');
        return;
    }
    
    if (checkedBoxes.length === 0) {
        alert('Zəhmət olmasa ən azı bir rezervasiya seçin');
        return;
    }
    
    let confirmMessage = '';
    switch(action) {
        case 'confirm':
            confirmMessage = 'Seçilmiş rezervasiyaları təsdiqləmək istədiyinizə əminsiniz?';
            break;
        case 'cancel':
            confirmMessage = 'Seçilmiş rezervasiyaları ləğv etmək istədiyinizə əminsiniz?';
            break;
        case 'delete':
            confirmMessage = 'Seçilmiş rezervasiyaları silmək istədiyinizə əminsiniz? Bu əməliyyat geri alına bilməz!';
            break;
    }
    
    if (confirm(confirmMessage)) {
        // Seçilen checkbox'ları gizli forma ekle
        const hiddenCheckboxes = document.getElementById('hidden-checkboxes');
        hiddenCheckboxes.innerHTML = '';
        
        checkedBoxes.forEach(cb => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'reservation_ids[]';
            hiddenInput.value = cb.value;
            hiddenCheckboxes.appendChild(hiddenInput);
        });
        
        // Action'ı gizli forma ekle
        document.getElementById('hidden-action').value = action;
        
        // Formu gönder
        document.getElementById('bulk-form').submit();
    }
}

// Tekil rezervasyon işlemleri
function updateReservationStatus(reservationId, status) {
    const statusText = status === 'confirmed' ? 'təsdiqləmək' : 'ləğv etmək';
    if (confirm(`Bu rezervasiyanı ${statusText} istədiyinizə əminsiniz?`)) {
        
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
        actionInput.value = status === 'confirmed' ? 'confirm' : 'cancel';
        form.appendChild(actionInput);
        
        const reservationInput = document.createElement('input');
        reservationInput.type = 'hidden';
        reservationInput.name = 'reservation_ids[]';
        reservationInput.value = reservationId;
        form.appendChild(reservationInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteReservation(reservationId) {
    if (confirm('Bu rezervasiyanı silmək istədiyinizə əminsiniz? Bu əməliyyat geri alına bilməz!')) {
        
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
        
        const reservationInput = document.createElement('input');
        reservationInput.type = 'hidden';
        reservationInput.name = 'reservation_ids[]';
        reservationInput.value = reservationId;
        form.appendChild(reservationInput);
        
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