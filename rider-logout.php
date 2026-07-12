<?php
require_once __DIR__ . '/config/config.php';
unset($_SESSION['rider_id'], $_SESSION['rider_name']);
header('Location: ' . BASE_URL . '/index.php');
exit;
