<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/lang.php';
require_login();
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title><?= lang('dashboard') ?></title>
    <link rel="stylesheet" href="/arande-system/assets/style.css">
</head>
<body>
<div class="layout">
    <nav class="sidebar">
        <!-- Hamburgermeny med länkar -->
        <div class="menu-title"><?= lang('menu') ?></div>
        <ul>
            <li><a href="portal.php"><?= lang('dashboard') ?></a></li>
            <li><a href="cases.php"><?= lang('cases') ?></a></li>
            <li><a href="settings.php"><?= lang('settings') ?></a></li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
                <li><a href="create_user.php">Skapa användare</a></li>
            <?php endif; ?>
            <li><a href="logout.php"><?= lang('logout') ?></a></li>
        </ul>
    </nav>
    <div class="main">
        <header class="top-banner"><?= lang('welcome') ?></header>
        <div class="content">
            <?php
            // Visa rätt innehåll beroende på menyval
            $page = basename($_SERVER['PHP_SELF']);
            // Korrekt sökväg till create_user.php (ligger i ../admin/)
            if ($page === 'create_user.php' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin') {
                include dirname(__DIR__) . '/admin/create_user.php';
            } else {
                // Standard dashboard
            ?>
                <h1><?= lang('dashboard') ?></h1>
                <p>Välkommen till ärendehanteringssystemet!</p>
            <?php } ?>
        </div>
    </div>
</div>
</body>
</html>
