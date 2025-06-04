<?php
session_start();
require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /arande-system/public/index.php');
        exit;
    }
}

function is_superadmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';
}

function require_superadmin() {
    if (!is_superadmin()) {
        http_response_code(403);
        echo "Du har inte behörighet att visa denna sida.";
        exit;
    }
}

// ...lägg till funktioner för 2FA, user lookup, etc...