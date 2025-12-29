<?php
require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/includes/config.php';
$order_code = $_GET['order'] ?? '';
$order = null;
if ($order_code) {
  $stmt = $pdo->prepare("SELECT o.*, u.name, u.email FROM orders o JOIN users u ON o.user_id=u.id WHERE o.order_code = ?");
  $stmt->execute([$order_code]);
  $order = $stmt->fetch();
  if (!$order) { die('Order not found'); }
}

$logo = is_object($config) ? ($config->logo ?? 'assets/img/img-logo-black.png') : ($config['logo'] ?? 'assets/img/img-logo-black.png');
?>
<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta charset="utf-8">
  <title>Konfirmasi Pembayaran</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Spline+Sans:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
  <link rel="stylesheet" href="assets/css/register.css">
  <style>
    .sr-only{ position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
  </style>
</head>
<body class="bg-light">

<header class="register-header">
  <img src="<?= htmlspecialchars($logo) ?>" class="reg-logo" alt="<?=htmlspecialchars($siteTitle)?>">
</header>

<div class="register-section">
    <h2 class="register-title">KONFIRMASI PEMBAYARAN</h2>
    <div class="info-card">
      <p class="info-title">Informasi Pembayaran</p>
      <div class="info-data">
        <span class="label">Bank</span>
        <span class="data-number">Danamon</span>
      </div>
      <hr>
      <div class="info-data">
        <span class="label">No. Rekening</span>
        <span class="data-number">0077 1007 7772 <button class="btn-copy" id="copyAcct">COPY</button></span>
        <span class="note-number">PT DYANDRA PROMOSINDO</span>
      </div>
      <hr>
      <div class="info-data">
        <span class="label">Jumlah</span>
        <span class="data-number">Rp 1.800.000 <button class="btn-copy" id="copyAmt">COPY</button></span>
      </div>
    </div>

    <form id="uploadForm" class="confirm-card" action="upload_payment.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="order_code" value="<?=htmlspecialchars($order_code)?>">
      <div class="confirm-info">
        <span>Drag & drop media files to Upload</span>
        <span>or</span>
        <input class="file-input" type="file" name="payment_file" accept=".jpg,.jpeg,.png,.pdf" required>
      </div>
      <button class="btn-submit">UPLOAD FILE & KONFIRMASI PEMBAYARAN</button>
    </form>
</div>

<script>
document.getElementById('copyAcct').addEventListener('click', function(){
  navigator.clipboard.writeText('007710077772');
  alert('Rekening disalin');
});
document.getElementById('copyAmt').addEventListener('click', function(){
  navigator.clipboard.writeText('1800000');
  alert('Jumlah disalin');
});
</script>
</body>
</html>
