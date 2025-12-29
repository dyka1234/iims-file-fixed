<?php
// admin/approve_registration.php
session_start();

/* ===============================
 * REQUIRE LOGIN (KONSISTEN DENGAN DASHBOARD)
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
require __DIR__ . '/../includes/email.php';

/* ===============================
 * VALIDASI REQUEST
 * =============================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$user_id  = (int)($_POST['user_id']  ?? 0);
$order_id = (int)($_POST['order_id'] ?? 0);

if ($user_id <= 0 || $order_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

try {
    // Ambil data user + order (PASTI ADA)
    $stmt = $pdo->prepare("
        SELECT 
            u.id   AS user_id,
            u.name AS user_name,
            u.email AS user_email,
            o.id   AS order_id,
            o.order_code,
            o.status
        FROM users u
        JOIN orders o ON o.user_id = u.id
        WHERE u.id = ? AND o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: dashboard.php');
        exit;
    }

    // OPTIONAL: jika mau ubah status setelah approve (mis. tetap pending)
    // Di sini TIDAK mengubah status agar flow lama tidak rusak
    // Jika mau: UPDATE orders SET status='pending' WHERE id=?

    // Kirim email instruksi pembayaran ke user
    $user = [
        'name'  => $row['user_name'],
        'email' => $row['user_email'],
    ];
    $order = [
        'order_code' => $row['order_code'],
    ];

    // UPDATE STATUS SETELAH APPROVE REGISTRASI
    $upd = $pdo->prepare("
        UPDATE orders
        SET status = 'waiting_payment'
        WHERE id = ?
    ");
    $upd->execute([$order_id]);

    // Kirim email (hasil true/false tidak menghalangi redirect)
    sendUserPaymentInstruction($user, $order);

} catch (Throwable $e) {
    error_log('approve_registration error: ' . $e->getMessage());
    // Tetap redirect ke dashboard (tanpa bocor error ke UI)
}

/* ===============================
 * REDIRECT (INI YANG PENTING)
 * =============================== */
// PASTI ke dashboard, bukan users.php / index.php
header('Location: dashboard.php?approved=1');
exit;
