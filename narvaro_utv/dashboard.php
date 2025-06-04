<?php
session_start();
require 'db.php';
$pageTitle = "Min profil";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header("Location: login.php");
    exit;
}

// Hämta användarinformation
$stmt = $pdo->prepare("SELECT fornamn, role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

include 'toppen.php';
?>

<!-- HTML och CSS för att snygga till dashboard-sidan -->
<head>
    <title>Överstikt | Konferenssystem</title>
</head>

<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <div class="page-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Administrationens startsida</h2>
    </div>
    <div class="page-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <div class="dashboard-container" style="display: flex; gap: 2rem; align-items: flex-start;">
            <div style="flex: 1;">
                <h2>Välkommen, <?= htmlspecialchars($user['fornamn']) ?>!</h2>
                <p>Du är inloggad som: <strong><?= $user['role'] === 'admin' ? 'Administratör' : 'Användare' ?></strong></p>

                <ul>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a href="manage_conferences.php">Lägg till konferens</a></li>
                        <li><a href="import_participants.php">Importera deltagare från CSV</a></li>
                        <li><a href="admin_panel.php">Hantera konferenser</a></li>
                        <li><a href="attendance.php">Närvaroregistrering</a></li>
                        <li><a href="admin_users.php">Hantera användarkonton</a></li>
                        <li><a href="maillog.php">Mejllogg</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div style="min-width: 300px; max-width: 350px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 8px #0001;">
                <h4 style="margin-top:0;">Aktuella konferenser</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                <?php
                $stmt = $pdo->query("SELECT id, name, date, public_visible FROM nv_conferences WHERE date >= CURDATE() ORDER BY date ASC");
                $found = false;
                while ($conf = $stmt->fetch()) {
                    $found = true;
                    // Hämta antal deltagare och antal med registrerad närvaro
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
                    $countStmt->execute([$conf['id']]);
                    $participantCount = $countStmt->fetchColumn();
                    $presentStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ? AND present = 1");
                    $presentStmt->execute([$conf['id']]);
                    $presentCount = $presentStmt->fetchColumn();
                    $status = $conf['public_visible'] ? '<span style="color:green;">(Aktiv)</span>' : '<span style="color:gray;">(Inaktiv)</span>';
                    if ($conf['public_visible']) {
                        $link = '<a href="attendance.php?conf_id=' . urlencode($conf['id']) . '"><strong>' . htmlspecialchars($conf['name']) . '</strong></a>';
                    } else {
                        $link = '<strong>' . htmlspecialchars($conf['name']) . '</strong>';
                    }
                    echo '<li style="margin-bottom: 1em;">' . $link . ' ' . $status .
                        '<br><span style="color:#888;font-size:0.95em;">' . $participantCount . ' deltagare, ' . $presentCount . ' har registrerat närvaro</span>' .
                        '<br><span style="color:#555;">' . date('Y-m-d', strtotime($conf['date'])) . '</span></li>';
                }
                if (!$found) {
                    echo '<li>Inga kommande konferenser.</li>';
                }
                ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
include 'botten.php';
?>