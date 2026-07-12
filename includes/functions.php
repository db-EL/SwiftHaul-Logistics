<?php
// Core helper functions
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

function require_admin() {
    if (!is_admin()) {
        redirect('/login.php');
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// --- Rider session (separate namespace from customer/admin sessions,
// so a browser could in theory be logged into a customer account and a
// rider account differently, and so rider auth bugs can't leak into
// customer/admin auth or vice versa) ---------------------------------
function is_rider_logged_in() {
    return isset($_SESSION['rider_id']);
}

function require_rider_login() {
    if (!is_rider_logged_in()) {
        redirect('/rider-login.php');
    }
}

function current_rider_id() {
    return $_SESSION['rider_id'] ?? null;
}

// --- CSRF -----------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

// --- Misc -------------------------------------------------------

/**
 * Generates an unpredictable tracking ID (e.g. SH-9K2M7QZX41).
 * Deliberately NOT sequential — a sequential ID lets anyone enumerate
 * every shipment (and its addresses/phone numbers) by guessing nearby
 * numbers against the public tracking API.
 */
function generate_tracking_id($pdo) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    do {
        $suffix = '';
        for ($i = 0; $i < 10; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $id = 'SH-' . $suffix;
        $stmt = $pdo->prepare("SELECT id FROM shipments WHERE tracking_id = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generates a 6-digit delivery confirmation code. Shown to the customer,
 * handed to the rider at drop-off, and required before a shipment can be
 * marked Delivered — so marking something "Delivered" (which triggers a
 * real bank transfer to the rider) isn't purely an admin's word.
 */
function generate_delivery_otp() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Clamp shipment weight/distance to sane bounds server-side so a
 * tampered POST can't be used to manipulate the calculated fee.
 */
function clamp_shipment_inputs(float $weightKg, float $distanceKm): array {
    $weight = max(0.1, min($weightKg, 2000));     // 100g .. 2 tonnes
    $distance = max(0.1, min($distanceKm, 1500));  // up to ~1500km
    return [$weight, $distance];
}


function flash($key, $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $out = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $out;
    }
    return null;
}

function status_progress_percent($status) {
    $order = ['Order Placed' => 8, 'Rider Assigned' => 25, 'Picked Up' => 45, 'In Transit' => 65, 'Out for Delivery' => 85, 'Delivered' => 100, 'Cancelled' => 0];
    return $order[$status] ?? 0;
}

/**
 * CSS-safe class suffix for a status badge, e.g. "Rider Assigned" -> "riderassigned".
 */
function status_badge_class($status) {
    return 'st-' . strtolower(str_replace(' ', '', $status));
}
