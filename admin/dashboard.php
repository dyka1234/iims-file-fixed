<?php
// admin/dashboard.php
session_start();

/* ===============================
 * AUTH
 * =============================== */
if (empty($_SESSION['admin_logged_in'])) {
    $cur = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
    header('Location: login.php?next=' . urlencode($cur));
    exit;
}

/* ===============================
 * INCLUDES
 * =============================== */
require __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';

/* ===============================
 * HELPERS
 * =============================== */
function buildPublicFileUrl($fname, $config) {
    return rtrim($config['app']['base_url'], '/') . '/uploads/' . $fname;
}
function statusBadge($s) {
    $cls = 'secondary';
    if ($s === 'pending') $cls = 'warning';
    elseif ($s === 'uploaded') $cls = 'info';
    elseif ($s === 'paid') $cls = 'success';
    elseif ($s === 'rejected') $cls = 'danger';
    return "<span class='badge bg-{$cls}'>" . htmlspecialchars(strtoupper($s)) . "</span>";
}

/* =====================================================
 * TAB 1 — REGISTRASI BARU
 * ===================================================== */
$stmtNew = $pdo->query("
    SELECT u.id user_id, u.name, u.email,
           o.id order_id, o.order_code, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN payment_uploads p ON p.order_id = o.id
    WHERE o.status = 'pending'
      AND p.id IS NULL
    ORDER BY o.created_at DESC
");
$newRegs = $stmtNew->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * TAB 2 — SUDAH DI-APPROVE (BELUM BAYAR)
 * ===================================================== */
$stmtApproved = $pdo->query("
    SELECT u.name, u.email, o.order_code, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'pending'
      AND o.id NOT IN (SELECT order_id FROM payment_uploads)
");
$approvedRegs = $stmtApproved->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * TAB 4 — LUNAS (PAID)
 * ===================================================== */
$stmtPaid = $pdo->query("
    SELECT u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'paid'
    ORDER BY o.created_at DESC
");
$paidUsers = $stmtPaid->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * TAB 3 — PEMBAYARAN (LOGIC LAMA)
 * ===================================================== */
$limit = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(o.order_code LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
if ($statusFilter !== '' && $statusFilter !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT p.id payment_id, p.file_path, p.uploaded_at,
           o.order_code, o.status order_status,
           u.name user_name, u.email user_email
    FROM payment_uploads p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    $whereSql
    ORDER BY p.uploaded_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * VIEW
 * ===================================================== */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f9fafc; }
.container-box { max-width:1200px; margin:30px auto; }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold">Admin Panel</span>
    <ul class="navbar-nav me-auto">
      <li class="nav-item"><span class="nav-link active">Dashboard</span></li>
      <li class="nav-item"><a class="nav-link" href="/admin/doorprizes.php">Doorprize</a></li>
    </ul>
    <a href="/admin/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
  </div>
</nav>

<div class="container-box">

<!-- TABS -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-new">
      Registrasi Baru
      <?php if(count($newRegs)): ?><span class="badge bg-danger"><?=count($newRegs)?></span><?php endif; ?>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-approved">Sudah Di-approve</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payment">Pembayaran</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-paid">Lunas</button>
  </li>
</ul>

<div class="tab-content">

<!-- TAB REGISTRASI BARU -->
<div class="tab-pane fade show active" id="tab-new">
<table class="table table-hover">
<?php foreach($newRegs as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['order_code'])?></td>
  <td><?=htmlspecialchars($r['name'])?></td>
  <td><?=htmlspecialchars($r['email'])?></td>
  <td class="text-end">
    <form method="post" action="approve_registration.php">
      <input type="hidden" name="user_id" value="<?=$r['user_id']?>">
      <input type="hidden" name="order_id" value="<?=$r['order_id']?>">
      <button class="btn btn-success btn-sm">Approve</button>
    </form>
  </td>
</tr>
<?php endforeach ?>
</table>
</div>

<!-- TAB APPROVED -->
<div class="tab-pane fade" id="tab-approved">
<table class="table table-sm">
<?php foreach($approvedRegs as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['order_code'])?></td>
  <td><?=htmlspecialchars($r['name'])?></td>
  <td><?=htmlspecialchars($r['email'])?></td>
  <td><span class="badge bg-warning text-dark">Menunggu Pembayaran</span></td>
</tr>
<?php endforeach ?>
</table>
</div>

<!-- TAB PEMBAYARAN -->
<div class="tab-pane fade" id="tab-payment">
<table class="table table-hover">
<?php foreach($rows as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['order_code'])?></td>
  <td><?=htmlspecialchars($r['user_name'])?></td>
  <td><?=statusBadge($r['order_status'])?></td>
  <td class="text-end">
    <a href="/admin/review.php?payment_id=<?=$r['payment_id']?>" class="btn btn-sm btn-outline-primary">Review</a>
  </td>
</tr>
<?php endforeach ?>
</table>
</div>

<!-- TAB PAID -->
<div class="tab-pane fade" id="tab-paid">
<a href="/admin/export_paid.php" class="btn btn-success mb-3">Export Excel</a>
<table class="table table-sm">
<?php foreach($paidUsers as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['order_code'])?></td>
  <td><?=htmlspecialchars($r['name'])?></td>
  <td><?=htmlspecialchars($r['email'])?></td>
  <td><?=htmlspecialchars($r['institution'])?></td>
</tr>
<?php endforeach ?>
</table>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
