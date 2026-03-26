<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
session_init();
auth_logout_user();
header('Location: ' . BASE_URL . '/index.php');
exit;