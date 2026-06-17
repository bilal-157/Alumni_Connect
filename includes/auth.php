<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function currentUser() {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? null,
        'role'  => $_SESSION['user_role']  ?? null,
        'image' => $_SESSION['user_image'] ?? 'default.png',
    ];
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}