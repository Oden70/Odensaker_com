<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header("Location: login.php");
    exit;
}

// Kontrollera att användaren är admin
$stmt = $pdo->prepare("SELECT role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    echo "Åtkomst nekad.";
    exit;
}

// Lägg till ny konferens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conference'])) {
    $name = $_POST['name'];
    $date = $_POST['date'];

    $stmt = $pdo->prepare("INSERT INTO nv_conferences (name, date) VALUES (?, ?)");
    $stmt->execute([$name, $date]);

    $success = "Konferens tillagd!";
}

// Lägg till kolumnen public_visible om den inte finns
// (Körs bara första gången, annars ignoreras felet)
try {
    $pdo->exec("ALTER TABLE nv_conferences ADD COLUMN public_visible TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}


$conferences = [];
$stmt2 = $pdo->query("SELECT id, name, date FROM nv_conferences ORDER BY date DESC");
while ($row2 = $stmt2->fetch()) {
    $conferences[] = $row2;
}

$pageTitle = "Hantera konferenser";
include 'toppen.php';
?>
<head>
    <title>Lägg till | Konferenssystem</title>
</head>

<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <div class="admin-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Lägg till ny konferens</h2>
    </div>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="admin-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 8px #0001;">
                <h3>Skapa ny konferens</h3>
                <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                <form method="POST" class="mb-3">
                    <input type="hidden" name="create_conference" value="1">
                    <div class="mb-2">
                        <input type="text" name="name" class="form-control" placeholder="Konferensnamn" required>
                    </div>
                    <div class="mb-2">
                        <input type="date" name="date" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Skapa konferens</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="admin-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 8px #0001;">
                <h3>Befintliga konferenser</h3>
                <?php if (empty($conferences)): ?>
                    <div class="alert alert-info">Inga konferenser finns ännu.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th>Namn</th>
                                    <th>Datum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conferences as $conf): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($conf['name']) ?></td>
                                        <td><?= htmlspecialchars($conf['date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'botten.php';
?>