<?php
// admin/review.php (FINAL with transparent navbar)

session_start();

// Require login
if (empty($_SESSION['admin_logged_in'])) {
    $cur = $_SERVER['REQUEST_URI'] ?? '/admin/review.php';
    header('Location: login.php?next=' . urlencode($cur));
    exit;
}

require __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/email.php';

function buildPublicFileUrl($fname, $config) {
    return rtrim($config->site_url, '/') . '/uploads/' . $fname;
}

// GET id
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) die("Invalid payment ID");

// Handle action
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve','reject'])) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT p.*, o.id AS order_id, o.order_code, u.email AS user_email, u.name AS user_name
                FROM payment_uploads p
                JOIN orders o ON p.order_id = o.id
                JOIN users u ON o.user_id = u.id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmt->execute([$payment_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception("Data tidak ditemukan.");

            $orderId   = (int)$row['order_id'];
            $orderCode = $row['order_code'];
            $userEmail = $row['user_email'];
            $userName  = $row['user_name'];
            $fileUrl   = buildPublicFileUrl($row['file_path'], $config);

            if ($action === 'approve') {
                $pdo->prepare("UPDATE orders SET status='paid' WHERE id=?")->execute([$orderId]);

                // Email to user
                if ($userEmail) {
                    $html = "<p>Halo {$userName},</p>
                             <p>Pembayaran untuk order <strong>{$orderCode}</strong> telah dikonfirmasi.</p>
                             <p><a href='{$fileUrl}'>Lihat bukti pembayaran</a></p>";
                    sendEmail($userEmail, "Pembayaran Dikonfirmasi - {$orderCode}", $html, strip_tags($html));
                }

                $flash = "Order telah APPROVE → PAID.";
            } else {
                $pdo->prepare("UPDATE orders SET status='rejected' WHERE id=?")->execute([$orderId]);

                if ($userEmail) {
                    $html = "<p>Halo {$userName},</p>
                             <p>Bukti pembayaran untuk order <strong>{$orderCode}</strong> ditolak. Silakan upload ulang.</p>";
                    sendEmail($userEmail, "Pembayaran Ditolak - {$orderCode}", $html, strip_tags($html));
                }

                $flash = "Order telah REJECTED.";
            }

            $pdo->commit();

            header("Location: review.php?payment_id={$payment_id}&updated=1&msg=" . urlencode($flash));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $flash = "Terjadi kesalahan server.";
        }
    }
}

// Fetch detail
$stmt = $pdo->prepare("
    SELECT p.*, o.order_code, o.status AS order_status, u.name AS user_name, u.email AS user_email
    FROM payment_uploads p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) die("Data tidak ditemukan.");

$fileUrl = buildPublicFileUrl($payment['file_path'], $config);
$ext = strtolower(pathinfo($payment['file_path'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png']);

$adminName = $_SESSION['admin_username'] ?? "Admin";
$msg = $_GET['msg'] ?? $flash;
$updated = isset($_GET['updated']);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Review Pembayaran</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f9fafc; }
    .navbar-transparent {
        background: transparent !important;
        box-shadow: none !important;
    }
    .navbar-transparent .nav-link, 
    .navbar-transparent .navbar-brand,
    .navbar-transparent .navbar-text {
        color:#333 !important;
    }
    .container-box { max-width:1000px; margin:30px auto; }
</style>
</head>
<body>

<!-- TRANSPARENT NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-transparent">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/admin/dashboard.php">Admin Panel</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navArea">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div id="navArea" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li><a class="nav-link" href="/admin/dashboard.php">Dashboard</a></li>
        <li><a class="nav-link" href="/admin/list.php">Review List</a></li>
      </ul>

      <span class="navbar-text me-3">
        Logged in as: <strong><?php echo htmlspecialchars($adminName); ?></strong>
      </span>
      <a href="/admin/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container-box">

    <?php if ($updated): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <h4>Review Bukti Pembayaran</h4>
        <hr>

        <div class="row g-4">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><th width="150">Payment ID</th><td><?=htmlspecialchars($payment['id'])?></td></tr>
                    <tr><th>Order Code</th><td><?=htmlspecialchars($payment['order_code'])?></td></tr>
                    <tr><th>User</th><td><?=htmlspecialchars($payment['user_name'])?><br><small><?=htmlspecialchars($payment['user_email'])?></small></td></tr>
                    <tr><th>Diupload</th><td><?=htmlspecialchars($payment['uploaded_at'])?></td></tr>
                    <tr><th>Status Order</th><td><span class="badge bg-secondary"><?=htmlspecialchars($payment['order_status'])?></span></td></tr>
                </table>

                <form method="post" onsubmit="return confirm('Yakin?');">
                    <button name="action" value="approve" class="btn btn-primary me-2">Approve (PAID)</button>
                    <button name="action" value="reject" class="btn btn-danger">Reject</button>
                </form>
            </div>

            <div class="col-md-6 text-center">
                <?php if ($isImage): ?>
                    <img src="<?=$fileUrl?>" class="img-fluid rounded shadow" style="max-height:500px">
                    <p class="mt-2"><a href="<?=$fileUrl?>" target="_blank">Buka gambar full</a></p>
                <?php else: ?>
                    <p><a href="<?=$fileUrl?>" class="btn btn-outline-primary" target="_blank">Download / Buka File</a></p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
