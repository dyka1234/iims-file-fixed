<?php
// admin/logout.php
session_start();

// Hapus session admin
unset($_SESSION['admin']);

// Optional: hapus seluruh session
// session_destroy();

header('Location: /admin/login.php');
exit;
