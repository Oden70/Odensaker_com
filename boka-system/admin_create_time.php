<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

if (!in_array($_SESSION['role'] ?? '', ['superadmin', 'admin', 'booker'])) {
    echo "<div class='alert alert-danger'>Endast administratör/bokare kan skapa tider.</div>";
    exit;
}

$msg = '';
$company_id = $_SESSION['company_id'] ?? null;
$events = $pdo->prepare("SELECT id, name FROM boka_events WHERE company_id = ? ORDER BY start_date DESC");
$events->execute([$company_id]);
$events = $events->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'] ?? null;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $max_participants = $_POST['max_participants'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO boka_times (company_id, event_id, start, end, max_participants) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$company_id, $event_id, $start_time, $end_time, $max_participants]);
    $msg = "<div class='alert alert-success'>Tid skapad!</div>";
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>Skapa tid</title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .create-time-form { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 24px #0001; padding: 2em; }
        .create-time-form label { display: block; margin-bottom: 0.5em; font-weight: 500; }
        .create-time-form input, .create-time-form select { width: 100%; padding: 0.7em; margin-bottom: 1em; border: 1px solid #bdbdbd; border-radius: 5px; }
        .create-time-form button { width: 100%; padding: 0.8em; background: #1a237e; color: #fff !important; border: none; border-radius: 5px; font-size: 1.1em; font-weight: 600; cursor: pointer; }
        .create-time-form button:hover { background: #3949ab; color: #fff !important; }
    </style>
</head>
<body>
<div class="create-time-form">
    <h2>Skapa ny tid</h2>
    <?= $msg ?>
    <form method="post">
        <label>Event
            <select name="event_id" required>
                <option value="">Välj event</option>
                <?php foreach ($events as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Starttid
            <input type="datetime-local" name="start_time" required>
        </label>
        <label>Sluttid
            <input type="datetime-local" name="end_time" required>
        </label>
        <label>Max antal deltagare
            <input type="number" name="max_participants" min="1">
        </label>
        <button type="submit">Skapa tid</button>
    </form>
</div>
</body>
</html>
