<?php
// upload_payment.php - FINAL VERSION (hyperlink clean, forced /uploads/ URL, physical folder /upload)

ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/email.php';

function log_error($msg) {
    @file_put_contents('/tmp/upload_payment_error.log',
        "[" . date('c') . "] $msg\n",
        FILE_APPEND
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

$order_code = trim($_POST['order_code'] ?? '');
if ($order_code === '') {
    http_response_code(400);
    die('Order required');
}

// Fetch order
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.email, u.name, u.id AS user_id 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE order_code = ?
    ");
    $stmt->execute([$order_code]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    log_error("Fetch order failed: " . $e->getMessage());
    http_response_code(500);
    die('Terjadi kesalahan pada server (DB).');
}

if (!$order) {
    http_response_code(404);
    die('Order not found');
}

// Check file
if (!isset($_FILES['payment_file'])) {
    http_response_code(400);
    die('No file uploaded');
}

$file = $_FILES['payment_file'];
$maxMb = $config->max_upload_mb ?? 5;
$maxBytes = $maxMb * 1024 * 1024;

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('File upload error: ' . $file['error']);
}

if ($file['size'] > $maxBytes) {
    http_response_code(400);
    die('File terlalu besar (max ' . $maxMb . ' MB)');
}

// Validate MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg','image/png','application/pdf'];
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    die('Tipe file tidak diizinkan');
}

// Determine physical upload directory
if (!empty($config->upload_dir)) {
    $uploadDir = rtrim($config->upload_dir, '/');
} else {
    // Default: DOCUMENT_ROOT/upload
    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/upload';
}

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        log_error("Cannot create upload dir: " . $uploadDir);
        http_response_code(500);
        die('Gagal membuat folder upload');
    }
}

// Safe extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$validExt = ['jpg','jpeg','png','pdf'];

if (!in_array($ext, $validExt, true)) {
    $ext = $mime === 'image/jpeg' ? 'jpg' :
           ($mime === 'image/png' ? 'png' :
           ($mime === 'application/pdf' ? 'pdf' : 'dat'));
}

// Generate filename
$fname = 'pay_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destAbs = $uploadDir . '/' . $fname;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    log_error("move_uploaded_file failed: $destAbs");
    http_response_code(500);
    die('Upload gagal');
}
@chmod($destAbs, 0644);

// Save to DB
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO payment_uploads (order_id, user_id, file_path, uploaded_at, note)
        VALUES (?, ?, ?, NOW(), NULL)
    ");
    $stmt->execute([$order['id'], $order['user_id'], $fname]);
    $paymentId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("UPDATE orders SET status='uploaded' WHERE id=?");
    $stmt->execute([$order['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    @unlink($destAbs);
    log_error("DB insert failed: " . $e->getMessage());
    http_response_code(500);
    die('Terjadi kesalahan pada server (DB).');
}

// Build public URLs (ALWAYS USE /uploads/)
$siteUrl = rtrim($config->site_url, '/');
$fileUrl = $siteUrl . '/uploads/' . $fname;
$reviewUrl = $siteUrl . '/admin/review.php?payment_id=' . urlencode($paymentId);

// Send email to admin
$adminEmail = $config->admin_email ?? null;

if ($adminEmail) {
    $html = "
        <p>Ada unggahan bukti pembayaran baru.</p>
        <ul>
            <li><strong>Nama:</strong> " . htmlspecialchars($order['name']) . "</li>
            <li><strong>Order Code:</strong> " . htmlspecialchars($order_code) . "</li>
            <li><strong>Email:</strong> " . htmlspecialchars($order['email']) . "</li>
            <li><strong>Waktu:</strong> " . date('Y-m-d H:i:s') . "</li>
        </ul>

        <p><strong>Review Admin:</strong><br>
            <a href='{$reviewUrl}'>Klik untuk membuka halaman review</a>
        </p>

        <p><strong>Bukti Pembayaran:</strong><br>
            <a href='{$fileUrl}'>Klik untuk melihat bukti pembayaran</a>
        </p>
    ";

    $alt = "Unggahan bukti pembayaran.\nReview: {$reviewUrl}\nBukti: {$fileUrl}";

    try {
        sendEmail($adminEmail, "Konfirmasi Pembayaran Baru - {$order_code}", $html, $alt);
    } catch (Exception $e) {
        log_error("sendEmail error: " . $e->getMessage());
    }
}

// Redirect
header("Location: thankyou.php?order=" . urlencode($order_code));
exit;