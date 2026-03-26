<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
session_init(); auth_logout_admin();
header('Location: ' . ADMIN_URL . '/login.php'); exit;
