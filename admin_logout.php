<?php
require_once __DIR__ . '/auth.php';
logout();
header('Location: admin_login.php');
exit;