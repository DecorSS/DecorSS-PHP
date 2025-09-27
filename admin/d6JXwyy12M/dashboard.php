<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
requireAdmin();

// Metrics - Güvenli sorgular
$totalProducts = (int)secureQuery($pdo, 'SELECT COUNT(*) FROM products')->fetchColumn();
$totalActiveProducts = (int)secureQuery($pdo, 'SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn();
$totalMessages = (int)secureQuery($pdo, 'SELECT COUNT(*) FROM contact_messages')->fetchColumn();
$last7Messages = (int)secureQuery($pdo, "SELECT COUNT(*) FROM contact_messages WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$totalReservations = (int)secureQuery($pdo, 'SELECT COUNT(*) FROM reservations')->fetchColumn();

include __DIR__ . '/includes/layout_header.php';
?>
  <h2>İdarəetmə Paneli</h2>
  <div class="cards">
    <div class="card primary">
      <h4>Ümumi Məhsul</h4>
      <p class="value"><?=$totalProducts?></p>
      <p class="hint">Sistemdəki toplam məhsul sayı</p>
    </div>
    <div class="card success">
      <h4>Aktiv Məhsul</h4>
      <p class="value"><?=$totalActiveProducts?></p>
      <p class="hint">Hal-hazırda görünən məhsullar</p>
    </div>
    <div class="card warning">
      <h4>Mesajlar (7 gün)</h4>
      <p class="value"><?=$last7Messages?></p>
      <p class="hint">Son 7 gündə gələn müraciətlər</p>
    </div>
    <div class="card info">
      <h4>Rezervasiyalar</h4>
      <p class="value"><?=$totalReservations?></p>
      <p class="hint">Toplam rezervasiya sayı</p>
    </div>
  </div>

  <div class="section-card">
    <h3 style="margin:0 0 10px 0">Son Mesajlar</h3>
    <table class="table">
      <thead>
        <tr><th>Ad</th><th>E-poçt</th><th>Mesaj</th><th>Status</th><th>Tarix</th></tr>
      </thead>
      <tbody>
        <?php
        $stmt = secureQuery($pdo, 'SELECT name,email,message,created_at,is_read FROM contact_messages ORDER BY created_at DESC LIMIT 5');
        $strLimit = function(string $s, int $len = 80): string {
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                return mb_strlen($s, 'UTF-8') > $len ? mb_substr($s, 0, $len, 'UTF-8') . '…' : $s;
            }
            return strlen($s) > $len ? substr($s, 0, $len) . '…' : $s;
        };
        foreach($stmt as $row): ?>
          <tr>
            <td><?=encodeHtml($row['name'])?></td>
            <td><?=encodeHtml($row['email'])?></td>
            <td><?=encodeHtml($strLimit($row['message'], 80))?></td>
            <td>
              <span style="background:<?=$row['is_read'] ? 'var(--success)' : 'var(--warning)'?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$row['is_read'] ? 'Oxunub' : 'Oxunmayıb'?>
              </span>
            </td>
            <td><?=encodeHtml($row['created_at'])?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="section-card">
    <h3 style="margin:0 0 10px 0">Son Rezervasiyalar</h3>
    <table class="table">
      <thead>
        <tr><th>Ad</th><th>E-poçt</th><th>Tarix</th><th>Saat</th><th>Status</th><th>Göndərilmə</th></tr>
      </thead>
      <tbody>
        <?php
        $stmt = secureQuery($pdo, 'SELECT name,email,reservation_date,reservation_time,created_at,COALESCE(status, "pending") as status FROM reservations ORDER BY created_at DESC LIMIT 5');
        foreach($stmt as $row): 
          $statusText = '';
          $statusColor = '';
          switch($row['status']) {
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
          <tr>
            <td><?=encodeHtml($row['name'])?></td>
            <td>
              <a href="mailto:<?=encodeHtml($row['email'])?>" style="color:var(--primary)">
                <?=encodeHtml($row['email'])?>
              </a>
            </td>
            <td>
              <span style="background:var(--primary);color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=encodeHtml(date('d.m.Y', strtotime($row['reservation_date'])))?>
              </span>
            </td>
            <td>
              <span style="background:var(--success);color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=encodeHtml(date('H:i', strtotime($row['reservation_time'])))?>
              </span>
            </td>
            <td>
              <span style="background:<?=$statusColor?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px">
                <?=$statusText?>
              </span>
            </td>
            <td style="font-size:12px;color:var(--muted)">
              <?=encodeHtml(date('d.m.Y H:i', strtotime($row['created_at'])))?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>


