<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

if (($_SESSION['role'] ?? '') !== 'superadmin' && ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger'>Endast superadmin eller företagsadministratör kan skapa användare.</div>";
    exit;
}

$msg = '';
$companies = $pdo->query("SELECT id, name FROM boka_companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = $_POST['company_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $password = $_POST['password'] ?? '';

    // Om användaren redan finns (på e-post), koppla till företag och roll om inte redan kopplad
    $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Uppdatera company_id och roll om det är en ny koppling
        if ($existing['company_id'] != $company_id || $existing['role'] != $role) {
            $stmt = $pdo->prepare("UPDATE boka_users SET company_id=?, role=? WHERE id=?");
            $stmt->execute([$company_id, $role, $existing['id']]);
            $msg = "<div class='alert alert-success'>Användaren kopplad till företag och roll uppdaterad.</div>";
        } else {
            $msg = "<div class='alert alert-info'>Användaren finns redan med denna koppling.</div>";
        }
    } else {
        // Skapa ny användare
        $stmt = $pdo->prepare("INSERT INTO boka_users (company_id, first_name, last_name, username, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $company_id, $first_name, $last_name, $username, $email, $phone,
            password_hash($password, PASSWORD_DEFAULT), $role
        ]);
        $msg = "<div class='alert alert-success'>Ny användare skapad och kopplad till företag.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>Skapa användare/kund</title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .create-company-form { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 24px #0001; padding: 2em; }
        .create-company-form label { display: block; margin-bottom: 0.5em; font-weight: 500; }
        .create-company-form input, .create-company-form select { width: 100%; padding: 0.7em; margin-bottom: 1em; border: 1px solid #bdbdbd; border-radius: 5px; }
        .create-company-form button { width: 100%; padding: 0.8em; background: #1a237e; color: #fff !important; border: none; border-radius: 5px; font-size: 1.1em; font-weight: 600; cursor: pointer; }
        .create-company-form button:hover { background: #3949ab; color: #fff !important; }
    </style>
</head>
<body>
<div class="create-company-form">
    <h2>Skapa användare/kund</h2>
    <?= $msg ?>
    <form method="post">
        <label>Företag
            <select name="company_id" required>
                <option value="">Välj företag</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Förnamn
            <input type="text" name="first_name" required>
        </label>
        <label>Efternamn
            <input type="text" name="last_name" required>
        </label>
        <label>Användarnamn
            <input type="text" name="username" required>
        </label>
        <label>E-post
            <input type="email" name="email" required>
        </label>
        <label>Telefon
            <input type="text" name="phone">
        </label>
        <label>Roll
            <select name="role" id="roleSelect" required>
                <option value="admin">Administratör</option>
                <option value="booker">Bokare</option>
                <option value="customer" selected>Kund</option>
                <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
                    <option value="superadmin">Superadmin</option>
                <?php endif; ?>
            </select>
        </label>
        <label>Lösenord
            <input type="password" name="password" required>
        </label>
        <button type="submit" id="createUserBtn">Skapa användare/kund</button>
    </form>
</div>
<script>
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
</body>
</html>
