<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page = $_GET['page'] ?? 'overview';

function active($p) {
    return (isset($_GET['page']) && $_GET['page'] === $p) ? 'active' : '';
}

// För att öppna rätt meny vid sidladdning
$openMenu = '';
if (in_array($page, ['courses', 'create_course'])) $openMenu = 'course_admin';
if (in_array($page, ['users', 'create_user'])) $openMenu = 'user_admin';

// Hämta roller för inloggad användare
$user_roles = isset($_SESSION['roles']) ? explode(',', $_SESSION['roles']) : [];

// Hjälpfunktion för rollkontroll
function has_role($role) {
    global $user_roles;
    return in_array($role, $user_roles);
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>LSM - <?= t('dashboard') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- Lägg till Google Fonts import om du inte redan har det i CSS -->
    <!-- <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet"> -->
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', Arial, sans-serif;
        }
        body {
            min-height: 100vh;
            min-width: 100vw;
        }
        .layout-root {
            display: flex;
            flex-direction: row;
            height: 100vh;
            width: 100vw;
        }
        .sidebar {
            height: 100vh;
            min-height: 100vh;
        }
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
            background: #f4f4f4;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .content {
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 900px) {
            .layout-root {
                flex-direction: column;
            }
            .sidebar {
                width: 100vw;
                min-width: 0;
                height: auto;
                min-height: 0;
                flex-direction: row;
                flex-wrap: wrap;
            }
            .main-area {
                min-height: 0;
            }
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dynamisk meny: toggla submenu vid klick på menu-title
        document.querySelectorAll('.menu-title').forEach(function(title) {
            title.addEventListener('click', function() {
                var submenu = this.nextElementSibling;
                var isOpen = submenu.classList.contains('open');
                // Stäng alla andra submenyer
                document.querySelectorAll('.submenu').forEach(function(sm) {
                    sm.classList.remove('open');
                    if (sm.previousElementSibling) sm.previousElementSibling.classList.add('closed');
                });
                if (!isOpen) {
                    submenu.classList.add('open');
                    this.classList.remove('closed');
                } else {
                    submenu.classList.remove('open');
                    this.classList.add('closed');
                }
            });
        });
        // Öppna rätt meny vid sidladdning
        var openMenu = "<?= $openMenu ?>";
        if (openMenu) {
            var menuTitle = document.querySelector('.menu-title[data-menu="'+openMenu+'"]');
            var submenu = menuTitle ? menuTitle.nextElementSibling : null;
            if (submenu) {
                submenu.classList.add('open');
                menuTitle.classList.remove('closed');
            }
        }
    });
    </script>
</head>
<body>
    <div class="layout-root">
        <div class="sidebar">
            <h2>LSM</h2>
            <a href="?page=overview" class="<?= active('overview') ?>"><?= t('dashboard') ?></a>
            <?php if (has_role('superadmin')): ?>
                <!-- Superadmin ser allt -->
                <div class="menu-group">
                    <div class="menu-title<?= $openMenu !== 'course_admin' ? ' closed' : '' ?>" data-menu="course_admin"><?= t('course_admin') ?? 'Kursadministration' ?></div>
                    <div class="submenu<?= $openMenu === 'course_admin' ? ' open' : '' ?>">
                        <a href="?page=courses" class="<?= active('courses') ?>"><?= t('courses') ?></a>
                        <a href="?page=create_course" class="<?= active('create_course') ?>"><?= t('add_course') ?></a>
                    </div>
                </div>
                <div class="menu-group">
                    <div class="menu-title<?= $openMenu !== 'user_admin' ? ' closed' : '' ?>" data-menu="user_admin"><?= t('user_admin') ?? 'Användare' ?></div>
                    <div class="submenu<?= $openMenu === 'user_admin' ? ' open' : '' ?>">
                        <a href="?page=users" class="<?= active('users') ?>"><?= t('manage_users') ?? 'Hantera användare' ?></a>
                        <a href="?page=create_user" class="<?= active('create_user') ?>"><?= t('add_user') ?? 'Skapa användare' ?></a>
                    </div>
                </div>
                <a href="?page=settings" class="<?= active('settings') ?>"><?= t('settings') ?></a>
            <?php elseif (has_role('courseadmin')): ?>
                <!-- Kursadmin ser kurs- och användarmenyer men inte inställningar -->
                <div class="menu-group">
                    <div class="menu-title<?= $openMenu !== 'course_admin' ? ' closed' : '' ?>" data-menu="course_admin"><?= t('course_admin') ?? 'Kursadministration' ?></div>
                    <div class="submenu<?= $openMenu === 'course_admin' ? ' open' : '' ?>">
                        <a href="?page=courses" class="<?= active('courses') ?>"><?= t('courses') ?></a>
                        <a href="?page=create_course" class="<?= active('create_course') ?>"><?= t('add_course') ?></a>
                    </div>
                </div>
                <div class="menu-group">
                    <div class="menu-title<?= $openMenu !== 'user_admin' ? ' closed' : '' ?>" data-menu="user_admin"><?= t('user_admin') ?? 'Användare' ?></div>
                    <div class="submenu<?= $openMenu === 'user_admin' ? ' open' : '' ?>">
                        <a href="?page=users" class="<?= active('users') ?>"><?= t('manage_users') ?? 'Hantera användare' ?></a>
                        <a href="?page=create_user" class="<?= active('create_user') ?>"><?= t('add_user') ?? 'Skapa användare' ?></a>
                    </div>
                </div>
            <?php endif; ?>
            <a href="auth/logout.php"><?= t('logout') ?></a>
        </div>
        <div class="main-area">
            <div class="topbar">
                <a href="?page=profile" style="font-weight:bold; color:#1a237e; text-decoration:none;">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                </a>
            </div>
            <div class="content">
                <?php
                switch ($page) {
                    case 'courses':
                        include 'courses/index.php';
                        break;
                    case 'create_course':
                        include 'courses/create.php';
                        break;
                    case 'edit_course':
                        if (!empty($_GET['course_id'])) {
                            include 'courses/edit.php';
                        } else {
                            echo "<p>Course ID missing.</p>";
                        }
                        break;
                    case 'sessions':
                        if (!empty($_GET['course_id'])) {
                            $course_id = (int)$_GET['course_id'];
                            include 'courses/sessions.php';
                        } else {
                            echo "<p>Course ID missing.</p>";
                        }
                        break;
                    case 'users':
                        include 'users/index.php';
                        break;
                    case 'create_user':
                        include 'users/create_user.php';
                        break;
                    case 'edit_user':
                        if (!empty($_GET['user_id'])) {
                            include 'users/edit_user.php';
                        } else {
                            echo "<p>User ID missing.</p>";
                        }
                        break;
                    case 'settings':
                        echo "<h2>" . t('settings') . "</h2><p>Inställningar för ditt konto eller systemet.</p>";
                        break;
                    case 'profile':
                        include 'profile.php';
                        break;
                    case 'overview':
                    default:
                        echo "<h2>" . t('dashboard') . "</h2><p>Välkommen till LSM-systemet!</p>";
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
