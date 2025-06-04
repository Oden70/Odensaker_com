<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/lang.php';
require_login();
if (!is_superadmin() && !($user['is_admin'] ?? false)) {
    http_response_code(403);
    echo "Du har inte behörighet att visa denna sida.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title><?= lang('admin') ?></title>
    <link rel="stylesheet" href="/arande-system/assets/style.css">
</head>
<body>
<div class="layout">
    <nav class="sidebar">
        <div class="menu-title"><?= lang('menu') ?></div>
        <ul>
            <li><a href="index.php"><?= lang('dashboard') ?></a></li>
            <li><a href="companies.php"><?= lang('companies') ?></a></li>
            <li><a href="users.php"><?= lang('users') ?></a></li>
            <li><a href="settings.php"><?= lang('settings') ?></a></li>
            <li><a href="../public/logout.php"><?= lang('logout') ?></a></li>
        </ul>
    </nav>
    <div class="main">
        <header class="top-banner"><?= lang('admin') ?></header>
        <div class="content">
            <h1><?= lang('dashboard') ?></h1>
            <p>Adminpanel för ärendehanteringssystemet.</p>
        </div>
    </div>
</div>
</body>
</html>
