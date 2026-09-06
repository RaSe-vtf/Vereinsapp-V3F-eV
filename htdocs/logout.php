<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

loescheRememberTokenUndCookie();

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
