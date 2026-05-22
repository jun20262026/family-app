<?php
/**
 * 登出 API
 */
require_once __DIR__ . '/../config.php';

session_destroy();
jsonResponse(['success' => true]);
?>
