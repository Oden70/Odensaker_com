<?php
require_once 'db.php';
session_start();

// Kontrollera att användaren är inloggad och admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'admin') {
    echo "Åtkomst nekad.";
    exit;
}

// Hantera redigering av användare
$editUser = null;
if (isset($_POST['edit_user_id'])) {
    $editId = (int)$_POST['edit_user_id'];
    $stmt = $pdo->prepare("SELECT id, username, email, role, fornamn, efternamn FROM nv_users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}
if (isset($_POST['save_user'])) {
    $editId = (int)$_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $fornamn = $_POST['fornamn'];
    $efternamn = $_POST['efternamn'];
    $role = $_POST['role'];
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE nv_users SET username=?, email=?, fornamn=?, efternamn=?, password_hash=?, role=? WHERE id=?");
        $stmt->execute([$username, $email, $fornamn, $efternamn, $password, $role, $editId]);
    } else {
        $stmt = $pdo->prepare("UPDATE nv_users SET username=?, email=?, fornamn=?, efternamn=?, role=? WHERE id=?");
        $stmt->execute([$username, $email, $fornamn, $efternamn, $role, $editId]);
    }
    $success = "Användare uppdaterad!";
    $editUser = null;
}

// Hantera formulär för att skapa konto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $fornamn = $_POST['fornamn'];
    $efternamn = $_POST['efternamn'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO nv_users (username, email, fornamn, efternamn, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $fornamn, $efternamn, $password, $role]);

    $success = "Användare skapad!";
}
include 'toppen.php';
?>
<head>
    <title>Hantera användare | Konferenssystem</title>
</head>
<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <div class="admin-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Hantera användarkonton</h2>
    </div>

    <div class="admin-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <?php if ($editUser): ?>
            <h3>Redigera konto</h3>
            <form method="POST" class="mb-4">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <input type="text" name="username" class="form-control" placeholder="Användarnamn" required value="<?= htmlspecialchars($editUser['username']) ?>">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="fornamn" class="form-control" placeholder="Förnamn" required value="<?= htmlspecialchars($editUser['fornamn']) ?>">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="efternamn" class="form-control" placeholder="Efternamn" required value="<?= htmlspecialchars($editUser['efternamn']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <input type="email" name="email" class="form-control" placeholder="E-post" required value="<?= htmlspecialchars($editUser['email']) ?>">
                        </div>
                        <div class="mb-2">
                            <input type="password" name="password" class="form-control" placeholder="Nytt lösenord (lämna tomt för att behålla)">
                        </div>
                        <div class="mb-2">
                            <select name="role" class="form-select" required>
                                <option value="" disabled <?= $editUser['role'] !== 'user' && $editUser['role'] !== 'admin' ? 'selected' : '' ?>>Välj roll</option>
                                <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>Användare</option>
                                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administratör</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-success btn-sm">Spara ändringar</button>
                        </div>
                    </div>
                </div>
            </form>
            <form method="GET" class="mb-4">
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-secondary btn-sm">Avbryt</button>
                </div>
            </form>
        <?php else: ?>
            <h3>Skapa nytt konto</h3>
            <form method="POST" class="mb-4">
                <input type="hidden" name="create_user" value="1">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <input type="text" name="username" class="form-control" placeholder="Användarnamn" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="fornamn" class="form-control" placeholder="Förnamn" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="efternamn" class="form-control" placeholder="Efternamn" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <input type="email" name="email" class="form-control" placeholder="E-post" required>
                        </div>
                        <div class="mb-2">
                            <input type="password" name="password" class="form-control" placeholder="Lösenord" required>
                        </div>
                        <div class="mb-2">
                            <select name="role" class="form-select" required>
                                <option value="" disabled selected>Välj roll</option>
                                <option value="user">Användare</option>
                                <option value="admin">Administratör</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm">Skapa konto</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php
        // Hämta alla användare
        $stmt = $pdo->query("SELECT id, username, fornamn, efternamn, email, role FROM nv_users ORDER BY username");
        echo '<h3>Användarkonton</h3>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-bordered bg-white">';
        echo '<thead class="table-light"><tr><th>Användarnamn</th><th>Förnamn</th><th>Efternamn</th><th>E-post</th><th>Roll</th><th></th></tr></thead><tbody>';
        while ($user = $stmt->fetch()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($user['username']) . '</td>';
            echo '<td>' . htmlspecialchars($user['fornamn']) . '</td>';
            echo '<td>' . htmlspecialchars($user['efternamn']) . '</td>';
            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
            echo '<td>' . htmlspecialchars($user['role']) . '</td>';
            echo '<td>
                <form method="POST" style="display:inline;">
                <input type="hidden" name="edit_user_id" value="' . $user['id'] . '">
                <button type="submit" class="btn btn-sm btn-primary">Redigera</button>
                </form>
                </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        ?>
    </div>
</div>
