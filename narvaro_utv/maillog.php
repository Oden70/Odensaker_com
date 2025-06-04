<?php
session_start();
require 'db.php';
$pageTitle = "Mejllogg";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header("Location: login.php");
    exit;
}

// Hantera radering av enskild logg
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM nv_maillog WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: maillog.php");
    exit;
}

// Hantera rensning av alla loggar
if (isset($_POST['delete_all'])) {
    $pdo->exec("TRUNCATE TABLE nv_maillog");
    header("Location: maillog.php");
    exit;
}

// Hämta loggposter
$stmt = $pdo->query("SELECT id, to_email, subject, sent_at FROM nv_maillog ORDER BY sent_at DESC");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$show_id = isset($_GET['show']) ? (int)$_GET['show'] : null;
$mail_detail = null;
if ($show_id) {
    $stmt = $pdo->prepare("SELECT * FROM nv_maillog WHERE id = ?");
    $stmt->execute([$show_id]);
    $mail_detail = $stmt->fetch(PDO::FETCH_ASSOC);
}

include 'toppen.php';
?>
<head>
    <title>Mejllogg | Konferenssystem</title>
</head>

<div class="container" style="max-width:1100px;margin:40px auto;">
    <div class="page-header" style="background:#e9ecef;border:1px solid #bbb;border-radius:10px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px #0001;text-align:center;">
        <h2 style="margin:0;">Mejllogg</h2>
    </div>
    <div class="page-section" style="background:#f8f9fa;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px #0001;">
        <?php if ($mail_detail): ?>
            <a href="maillog.php" style="margin-bottom:1em;display:inline-block;">&larr; Tillbaka till logglistan</a>
            <h4>Mejldetaljer</h4>
            <table class="table table-bordered">
                <tr><th>Mottagare</th><td><?= htmlspecialchars($mail_detail['to_email']) ?></td></tr>
                <tr><th>Ämnesrad</th><td><?= htmlspecialchars($mail_detail['subject']) ?></td></tr>
                <tr><th>Datum & tid</th><td><?= htmlspecialchars($mail_detail['sent_at']) ?></td></tr>
                <tr><th>Meddelande</th><td style="white-space:pre-wrap;"><?= htmlspecialchars($mail_detail['body']) ?></td></tr>
            </table>
            <form method="get" action="maillog.php" style="margin-top:1em;">
                <input type="hidden" name="delete" value="<?= $mail_detail['id'] ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Vill du verkligen radera denna loggpost?')">Radera denna loggpost</button>
            </form>
        <?php else: ?>
            <form method="post" style="margin-bottom:1em;">
                <button type="submit" name="delete_all" class="btn btn-danger" onclick="return confirm('Vill du verkligen rensa alla mejlloggar?')">Rensa alla mejlloggar</button>
            </form>
            <table class="table table-striped table-hover" style="background:#fff;">
                <thead>
                    <tr>
                        <th>Mottagare</th>
                        <th>Ämnesrad</th>
                        <th>Datum & tid</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['to_email']) ?></td>
                        <td><?= htmlspecialchars($log['subject']) ?></td>
                        <td><?= htmlspecialchars($log['sent_at']) ?></td>
                        <td>
                            <a href="maillog.php?show=<?= $log['id'] ?>" class="btn btn-sm btn-primary">Se mer</a>
                        </td>
                        <td>
                            <a href="maillog.php?delete=<?= $log['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Vill du verkligen radera denna loggpost?')">Radera</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align:center;">Inga mejl har skickats än.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'botten.php'; ?>
