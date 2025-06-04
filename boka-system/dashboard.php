<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

// Bestäm vilken sida som ska visas i dashboard-content
$page = $_GET['page'] ?? 'dashboard';

?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('dashboard') ?></title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .layout-root {
            display: flex;
            min-height: 100vh;
            background: #f4f6fb;
        }
        .sidebar {
            width: 220px;
            background: #232f3e;
            color: #fff;
            min-height: 100vh;
            padding: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        .sidebar nav {
            width: 100%;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar li {
            width: 100%;
        }
        .sidebar a {
            display: block;
            padding: 1em 1.5em;
            color: #fff;
            text-decoration: none;
            font-size: 1.08em;
            border-left: 4px solid transparent;
            transition: background 0.2s, border-color 0.2s;
        }
        .sidebar a.active,
        .sidebar a:hover {
            background: #1a237e;
            border-left: 4px solid var(--primary-color, #90caf9);
            color: #fff;
        }
        .sidebar .sidebar-title {
            font-size: 1.15em;
            font-weight: bold;
            padding: 1.2em 1.5em 0.7em 1.5em;
            color: #b0bec5;
            letter-spacing: 1px;
        }
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.8em 2em;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 56px;
        }
        .topbar .user-info {
            font-size: 1.08em;
            color: #1a237e;
            display: flex;
            align-items: center;
            gap: 1.2em;
        }
        .topbar .user-info a {
            color: var(--primary-color, #1a237e);
            text-decoration: none;
            font-weight: 500;
        }
        .dashboard-content {
            max-width: 90vw;
            min-width: 90vw;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em 2em 1.5em 2em;
        }
        .dashboard-content h2 {
            color: var(--primary-color, #1a237e);
            margin-bottom: 0.5em;
        }
        .dashboard-content p {
            color: #333;
        }
        @media (max-width: 900px) {
            .layout-root { flex-direction: column; }
            .sidebar { width: 100vw; min-height: 0; }
            .dashboard-content { margin: 20px 5px; }
        }
    </style>
</head>
<body>
<div class="layout-root">
    <div class="sidebar">
        <div class="sidebar-title"><?= t('menu') ?? 'Meny' ?></div>
        <nav>
            <ul>
                <li><a href="dashboard.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><?= t('dashboard') ?></a></li>
                <li>
                    <span style="display:block;padding:1em 1.5em 0.5em 1.5em;font-weight:bold;color:#b0bec5;">Företagsinställningar</span>
                    <ul style="list-style:none;padding-left:0.5em;">
                        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
                            <li>
                                <a href="dashboard.php?page=admin_create_company" class="<?= $page === 'admin_create_company' ? 'active' : '' ?>">
                                    <?= t('create_company') ?? 'Skapa företag' ?>
                                </a>
                            </li>
                            <li>
                                <a href="dashboard.php?page=admin_edit_company" class="<?= $page === 'admin_edit_company' ? 'active' : '' ?>">
                                    Redigera företag
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['role'] ?? '', ['superadmin', 'admin'])): ?>
                            <li>
                                <a href="dashboard.php?page=admin_create_user" class="<?= $page === 'admin_create_user' ? 'active' : '' ?>">
                                    Skapa användare/kund
                                </a>
                            </li>
                            <li>
                                <a href="dashboard.php?page=admin_edit_user" class="<?= $page === 'admin_edit_user' ? 'active' : '' ?>">
                                    Redigera användare/kund
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li>
                    <span style="display:block;padding:1em 1.5em 0.5em 1.5em;font-weight:bold;color:#b0bec5;">Eventhantering</span>
                    <ul style="list-style:none;padding-left:0.5em;">
                        <?php if (in_array($_SESSION['role'] ?? '', ['superadmin', 'admin'])): ?>
                            <li>
                                <a href="dashboard.php?page=admin_create_category" class="<?= $page === 'admin_create_category' ? 'active' : '' ?>">
                                    Skapa kategori
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['role'] ?? '', ['superadmin', 'admin', 'booker'])): ?>
                            <li>
                                <a href="dashboard.php?page=admin_create_event" class="<?= $page === 'admin_create_event' ? 'active' : '' ?>">
                                    Skapa event
                                </a>
                            </li>
                            <li>
                                <a href="dashboard.php?page=admin_create_time" class="<?= $page === 'admin_create_time' ? 'active' : '' ?>">
                                    Skapa tid
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
    <div class="main-area">
        <div class="topbar">
            <div class="user-info">
                <?php
                $company = $pdo->prepare("SELECT name, logo FROM boka_companies WHERE id = ?");
                $company->execute([$_SESSION['company_id']]);
                $c = $company->fetch();
                if ($c && $c['logo']) {
                    echo '<img src="' . htmlspecialchars($c['logo']) . '" alt="Logo" style="height:32px;vertical-align:middle;margin-right:10px;">';
                }
                ?>
                <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                <a href="dashboard.php?page=profile"><?= t('profile') ?></a>
                <a href="logout.php"><?= t('logout') ?></a>
            </div>
        </div>
        <div class="dashboard-content">
            <?php
            switch ($page) {
                case 'profile':
                    include __DIR__ . '/profile.php';
                    break;
                case 'company_settings':
                    // Superadmin: välj företag, admin: redigera sitt företag
                    include __DIR__ . '/company_settings.php';
                    break;
                case 'admin_languages':
                    if (file_exists(__DIR__ . '/admin_languages.php')) {
                        include __DIR__ . '/admin_languages.php';
                    } else {
                        echo "<div class='alert alert-danger'>Filen admin_languages.php saknas.</div>";
                    }
                    break;
                case 'admin_create_company':
                    include __DIR__ . '/admin_create_company.php';
                    break;
                case 'admin_edit_company':
                    include __DIR__ . '/admin_edit_company.php';
                    break;
                case 'admin_create_user':
                    include __DIR__ . '/admin_create_user.php';
                    break;
                case 'admin_edit_user':
                    include __DIR__ . '/admin_edit_user.php';
                    break;
                case 'admin_create_event':
                    include __DIR__ . '/admin_create_event.php';
                    break;
                case 'admin_create_time':
                    include __DIR__ . '/admin_create_time.php';
                    break;
                case 'admin_create_category':
                    include __DIR__ . '/admin_create_category.php';
                    break;
                case 'dashboard':
                default:
                    ?>
                    <h2><?= t('dashboard') ?></h2>
                    <p><?= t('welcome') ?? 'Välkommen till bokningssystemet!' ?></p>
                    <!-- Lägg till översikt/info här -->
                    <?php
                    break;
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>