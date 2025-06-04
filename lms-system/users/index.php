<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language.php';

// Hämta alla användare (lägg till telefon och status)
$stmt = $pdo->query("SELECT id, first_name, last_name, email, roles, phone1, phone2, status FROM lms_users ORDER BY last_name, first_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('manage_users') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .user-row:hover { background: #f0f4ff; cursor:pointer; }
        .search-input { width: 100%; padding: 0.6em; margin-bottom: 1em; border-radius: 5px; border: 1px solid #bdbdbd; font-size: 1em; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('user-search');
        const rows = Array.from(document.querySelectorAll('.user-row'));
        searchInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            if (val.length < 3) {
                rows.forEach(row => row.style.display = '');
                return;
            }
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(val) ? '' : 'none';
            });
        });
        // Klickbar rad
        rows.forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.tagName !== 'A') {
                    window.location = '?page=edit_user&user_id=' + this.dataset.userid;
                }
            });
        });
    });
    </script>
</head>
<body>
<div class="profile-container" style="max-width:900px;margin:40px auto;">
    <h2><?= t('manage_users') ?></h2>
    <input type="text" id="user-search" class="search-input" placeholder="<?= t('search_user') ?? 'Sök användare...' ?>">
    <table border="1" cellpadding="5" style="width:100%;background:#fff;">
        <tr>
            <th><?= t('first_name') ?></th>
            <th><?= t('last_name') ?></th>
            <th><?= t('email') ?></th>
            <th><?= t('roles') ?></th>
            <th><?= t('phone1') ?></th>
            <th><?= t('phone2') ?></th>
            <th><?= t('status') ?></th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr class="user-row" data-userid="<?= $user['id'] ?>">
            <td><?= htmlspecialchars($user['first_name']) ?></td>
            <td><?= htmlspecialchars($user['last_name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td>
                <?php
                $roles = explode(',', $user['roles']);
                foreach ($roles as $role) {
                    echo '<span style="background:#e3e3f7;color:#1a237e;padding:2px 8px;border-radius:4px;margin-right:4px;font-size:0.95em;">' . t('role_' . $role) . '</span>';
                }
                ?>
            </td>
            <td><?= htmlspecialchars($user['phone1'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['phone2'] ?? '') ?></td>
            <td><?= t('status_' . ($user['status'] ?? 'active')) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
