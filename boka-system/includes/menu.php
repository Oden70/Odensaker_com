<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/language.php';
$user_role = $_SESSION['role'] ?? '';
$company_id = $_SESSION['company_id'] ?? null;
$menu = $pdo->prepare("SELECT * FROM boka_menu WHERE min_role <= :role ORDER BY sort_order");
$menu->execute(['role' => $user_role]);
$menu_items = $menu->fetchAll();
?>
<nav class="sidebar">
    <button class="hamburger" onclick="document.body.classList.toggle('menu-open')">&#9776;</button>
    <ul>
        <?php foreach ($menu_items as $item): ?>
            <li><a href="?page=<?= htmlspecialchars($item['page']) ?>"><?= htmlspecialchars($item['label_' . $lang_code]) ?></a></li>
        <?php endforeach; ?>
        <li><a href="company_settings.php"><?= t('company_settings') ?? 'Företagsinställningar' ?></a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="admin_languages.php"><?= t('language_admin') ?? 'Språkhantering' ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
