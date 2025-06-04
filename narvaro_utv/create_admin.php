<?php
require 'db.php';

$username = 'admin';
$email = 'fredrik@odensaker.com';
$password = '?Jijomali70'; // Byt gärna till något säkrare!
$role = 'admin';

// Redigera användare
$editUser = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM nv_users WHERE id = ?");
    $stmt->execute([$_POST['edit_user_id']]);
    $editUser = $stmt->fetch();
}

// Spara ändringar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = (int)$_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $stmt = $pdo->prepare("UPDATE nv_users SET username = ?, email = ?, role = ? WHERE id = ?");
    $stmt->execute([$username, $email, $role, $id]);
    $success = "Användare uppdaterad!";
}

// Kontrollera om användaren redan finns
$stmt = $pdo->prepare("SELECT id FROM nv_users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    echo "Användaren finns redan.";
    exit;
}

// Lägg till användaren i databasen
$stmt = $pdo->prepare("INSERT INTO nv_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt->execute([$username, $email, $passwordHash, $role]);

// Bekräfta att användaren har lagts till
if ($stmt->rowCount() > 0) {
    echo "Användaren har lagts till i databasen.";
} else {
    echo "Ett fel inträffade. Användaren kunde inte läggas till.";
}

// Hämta alla användare
$stmt = $pdo->query("SELECT id, username, email, role FROM nv_users ORDER BY username");
echo '<h3>Användarkonton</h3>';
echo '<table class="table table-sm">';
echo '<thead><tr><th>Användarnamn</th><th>E-post</th><th>Roll</th><th></th></tr></thead><tbody>';
while ($user = $stmt->fetch()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($user['username']) . '</td>';
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
?>
