<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

$role = $_SESSION['role'] ?? '';
$company_id = $_SESSION['company_id'] ?? null;

if (!in_array($role, ['superadmin', 'admin', 'booker'])) {
    echo "<div class='alert alert-danger'>Åtkomst nekad.</div>";
    exit;
}

$msg = '';
$edit_mode = false;
$edit_user = null;

// Hämta företag för superadmin
$companies = [];
if ($role === 'superadmin') {
    $companies = $pdo->query("SELECT id, name FROM boka_companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Hämta användare
if ($role === 'superadmin') {
    $users = $pdo->query("SELECT * FROM boka_users ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE company_id = ? ORDER BY last_name, first_name");
    $stmt->execute([$company_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hämta användare för redigering
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['edit'];
    if ($role === 'superadmin') {
        $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id=?");
        $stmt->execute([$edit_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id=? AND company_id=?");
        $stmt->execute([$edit_id, $company_id]);
    }
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_user) {
        $msg = "<div class='alert alert-danger'>Användaren kunde inte hittas.</div>";
        $edit_mode = false;
    }
}

// Hantera POST för redigering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $company_id_post = $_POST['company_id'] ?? $company_id;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_post = $_POST['role'] ?? 'customer';
    $password = $_POST['password'] ?? '';

    $params = [$company_id_post, $first_name, $last_name, $username, $email, $phone, $role_post, $edit_id];
    $sql = "UPDATE boka_users SET company_id=?, first_name=?, last_name=?, username=?, email=?, phone=?, role=?";

    if ($password !== '') {
        $sql .= ", password_hash=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql .= " WHERE id=?";
    if ($password !== '') {
        // flytta id sist
        $params[] = array_splice($params, -1, 1)[0];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $msg = "<div class='alert alert-success'>Användare uppdaterad!</div>";

    // Hämta uppdaterad data
    if ($role === 'superadmin') {
        $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id=?");
        $stmt->execute([$edit_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id=? AND company_id=?");
        $stmt->execute([$edit_id, $company_id]);
    }
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $edit_mode = true;
}
?>
<style>
.edit-user-content {
    max-width: 500px;
    margin: 40px auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 24px #0001;
    padding: 2em;
}
.edit-user-content label {
    display: block;
    margin-bottom: 0.5em;
    font-weight: 500;
}
.edit-user-content input,
.edit-user-content select {
    width: 100%;
    padding: 0.7em;
    margin-bottom: 1em;
    border: 1px solid #bdbdbd;
    border-radius: 5px;
}
.edit-user-content button {
    width: 100%;
    padding: 0.8em;
    background: #1a237e;
    color: #fff !important;
    border: none;
    border-radius: 5px;
    font-size: 1.1em;
    font-weight: 600;
    cursor: pointer;
}
.edit-user-content button:hover {
    background: #3949ab;
    color: #fff !important;
}
.user-list {
    max-width: 600px;
    margin: 40px auto 0 auto;
    background: #f7f7f7;
    border-radius: 10px;
    box-shadow: 0 2px 8px #0001;
    padding: 1.5em 2em;
}
.user-list ul {
    list-style: none;
    padding: 0;
}
.user-list li {
    padding: 0.5em 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.user-list .edit-link {
    background: #1976d2;
    color: #fff !important;
    border: none;
    border-radius: 4px;
    padding: 0.3em 0.9em;
    font-size: 0.95em;
    cursor: pointer;
    text-decoration: none;
    margin-left: 1em;
}
.user-list .edit-link:hover {
    background: #1565c0;
}
</style>
<div class="user-list">
    <h2>Alla användare/kunder</h2>
    <input type="text" id="userSearch" placeholder="Sök användare/kund..." style="width:100%;padding:0.6em;margin-bottom:1em;border-radius:5px;border:1px solid #bdbdbd;">
    <ul id="userList">
        <?php foreach ($users as $u): ?>
            <li>
                <span><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')') ?></span>
                <a href="dashboard.php?page=admin_edit_user&edit=<?= $u['id'] ?>" class="edit-link">Redigera</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<div class="edit-user-content">
    <h2>Redigera användare/kund</h2>
    <?= $msg ?>
    <?php if ($edit_mode && $edit_user): ?>
    <form method="post">
        <input type="hidden" name="edit_id" value="<?= $edit_user['id'] ?>">
        <?php if ($role === 'superadmin'): ?>
            <label>Företag
                <select name="company_id" required>
                    <option value="">Välj företag</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $edit_user['company_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Förnamn
            <input type="text" name="first_name" value="<?= htmlspecialchars($edit_user['first_name'] ?? '') ?>" required>
        </label>
        <label>Efternamn
            <input type="text" name="last_name" value="<?= htmlspecialchars($edit_user['last_name'] ?? '') ?>" required>
        </label>
        <label>Användarnamn
            <input type="text" name="username" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" required>
        </label>
        <label>E-post
            <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" required>
        </label>
        <label>Telefon
            <input type="text" name="phone" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>">
        </label>
        <label>Roll
            <select name="role" id="roleSelect" required>
                <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administratör</option>
                <option value="booker" <?= $edit_user['role'] === 'booker' ? 'selected' : '' ?>>Bokare</option>
                <option value="customer" <?= $edit_user['role'] === 'customer' ? 'selected' : '' ?>>Kund</option>
                <?php if ($role === 'superadmin'): ?>
                    <option value="superadmin" <?= $edit_user['role'] === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                <?php endif; ?>
            </select>
        </label>
        <label>Byt lösenord (lämna tomt för att behålla nuvarande)
            <input type="password" name="password">
        </label>
        <button type="submit">Spara ändringar</button>
        <a href="dashboard.php?page=admin_edit_user" style="margin-left:1em;">Avbryt</a>
    </form>
    <script>
    document.getElementById('userSearch').addEventListener('input', function() {
        var filter = this.value.toLowerCase();
        var items = document.querySelectorAll('#userList li');
        if (filter.length < 2) {
            items.forEach(function(li) { li.style.display = ''; });
            return;
        }
        items.forEach(function(li) {
            var name = li.textContent.toLowerCase();
            li.style.display = name.includes(filter) ? '' : 'none';
        });
    });
    document.getElementById('roleSelect')?.addEventListener('change', function() {
        if (this.value === 'superadmin') {
            var companySelect = document.querySelector('select[name="company_id"]');
            if (companySelect && companySelect.value) {
                if (!confirm('Du har valt både ett företag och rollen Superadmin. Är du säker på att användaren ska vara Superadmin och kopplad till ett företag?')) {
                    this.value = 'admin';
                }
            }
        }
    });
    </script>
    <?php else: ?>
        <div>Välj en användare ovan för att redigera.</div>
    <?php endif; ?>
</div>
