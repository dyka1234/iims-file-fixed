<?php
/**
 * Email helper menggunakan PHPMailer
 * Semua pengiriman email lewat file ini
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ===============================
 * LOAD PHPMailer (NO COMPOSER)
 * =============================== */
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

/**
 * Init PHPMailer
 */
function initMailer(): PHPMailer
{
    global $config;

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = $config['smtp']['auth'];
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port       = $config['smtp']['port'];
    $mail->CharSet    = 'UTF-8';

    if (!empty($config['smtp']['debug'])) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';
    }

    $mail->setFrom(
        $config['system_email'],
        $config['system_name']
    );

    return $mail;
}

/* ======================================================
 * EMAIL KE ADMIN – REGISTRASI BARU
 * ====================================================== */
function sendAdminNewRegistration(array $userData): bool
{
    global $config;

    try {
        $mail = initMailer();
        $mail->addAddress($config['admin_email']);
        $mail->addAddress($config['admin_email2']);

        $mail->isHTML(true);
        $mail->Subject = 'Registrasi Baru - ' . htmlspecialchars($userData['name']);

        $mail->Body = '
            <h3>Registrasi Baru</h3>
            <p>Ada pendaftaran baru dengan detail berikut:</p>
            <ul>
                <li><strong>Nama:</strong> ' . htmlspecialchars($userData['name']) . '</li>
                <li><strong>Email:</strong> ' . htmlspecialchars($userData['email']) . '</li>
                <li><strong>Telepon:</strong> ' . htmlspecialchars($userData['phone']) . '</li>
                <li><strong>Handicap:</strong> ' . htmlspecialchars($userData['handicap']) . '</li>
                <li><strong>Instansi/Komunitas:</strong> ' . htmlspecialchars($userData['institution']) . '</li>
                <li><strong>Waktu:</strong> ' . date('d M Y H:i') . '</li>
            </ul>
        ';

        return $mail->send();

    } catch (Throwable $e) {
        error_log('Email admin error: ' . $e->getMessage());
        return false;
    }
}

/* ======================================================
 * EMAIL KE USER – APPROVAL & INSTRUKSI PEMBAYARAN
 * ====================================================== */
function sendUserPaymentInstruction(array $user, array $order): bool
{
    global $config;

    try {
        $mail = initMailer();
        $mail->addAddress($user['email'], $user['name']);

        $mail->isHTML(true);
        $mail->Subject = 'Registrasi Disetujui – Instruksi Pembayaran';

        $paymentUrl = rtrim($config['app']['base_url'], '/') .
            '/payment.php?order=' .
            urlencode($order['order_code']);

        $mail->Body = '
            <p>Halo <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>

            <p>
                Registrasi Anda untuk <strong>IIMS Golf Tournament</strong>
                telah <strong>disetujui oleh admin</strong>.
            </p>
            <p>
                Pendaftaran Anda berhasil.<br>
                <strong>Nomor Pendaftaran:</strong> ' . htmlspecialchars($order['order_code']) . '
            </p>

            <p>
                Silakan melanjutkan ke tahap pembayaran melalui tombol berikut: 
            </p>

            <p>
                <a href="' . $paymentUrl . '"
                   style="display:inline-block;
                          padding:12px 24px;
                          background:#f43232;
                          color:#ffffff;
                          text-decoration:none;
                          border-radius:6px;
                          font-weight:600;">
                    PROSES PEMBAYARAN
                </a>
            </p>

            <p>
                Jika tombol di atas tidak berfungsi, buka link ini:<br>
                <a href="' . $paymentUrl . '">' . $paymentUrl . '</a>
            </p>

            <p>
                Terima kasih,<br>
                <strong>IIMS Golf Tournament</strong>
            </p>
        ';

        return $mail->send();

    } catch (Throwable $e) {
        error_log('Email user error: ' . $e->getMessage());
        return false;
    }
}
