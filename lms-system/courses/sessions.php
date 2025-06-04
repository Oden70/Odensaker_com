<?php
// courses/sessions.php
require_once __DIR__ . '/../includes/db.php';
// Ändra till rätt språkfil: language.php istället för lang.php
require_once __DIR__ . '/../includes/language.php';

$course_id = (int)$_GET['course_id'];
$stmt = $pdo->prepare("SELECT * FROM lms_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    echo "<p>Course not found.</p>";
    return;
}

// Delete session
if (isset($_GET['delete'])) {
    $session_id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM lms_sessions WHERE id = ? AND course_id = ?")->execute([$session_id, $course_id]);
    echo "<p>" . t('session_deleted') . "</p>";
}

// Update session
if (isset($_POST['update_id'])) {
    $update_id = (int)$_POST['update_id'];
    $start = $_POST['start_datetime'] ?? '';
    $end = $_POST['end_datetime'] ?? '';
    if (!empty($start) && !empty($end)) {
        // Byt till rätt kolumnnamn, t.ex. 'start_time' och 'end_time'
        $pdo->prepare("UPDATE lms_sessions SET start_time = ?, end_time = ? WHERE id = ? AND course_id = ?")
            ->execute([$start, $end, $update_id, $course_id]);
        echo "<p>" . t('session_updated') . "</p>";
    }
}

// Create new session
if (isset($_POST['create'])) {
    $start = $_POST['start_datetime'] ?? '';
    $end = $_POST['end_datetime'] ?? '';
    if (!empty($start) && !empty($end)) {
        $insert = $pdo->prepare("INSERT INTO lms_sessions (course_id, start_time, end_time) VALUES (?, ?, ?)");
        $insert->execute([$course_id, $start, $end]);
        echo "<p>" . t('session_created') . "</p>";
    }
}

// Hämta sessioner (använd rätt kolumnnamn, t.ex. 'start_time' och 'end_time')
$sessions_stmt = $pdo->prepare("SELECT * FROM lms_sessions WHERE course_id = ? ORDER BY start_time");
$sessions_stmt->execute([$course_id]);
$session_rows = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <meta charset="UTF-8">
    <title><?= t('sessions') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<h2><?= htmlspecialchars($course['title'] ?? '') ?> - <?= t('sessions') ?></h2>

<h3><?= t('add_session') ?></h3>
<form method="post">
    <input type="hidden" name="create" value="1">
    <label><?= t('start_datetime') ?>: <input type="datetime-local" name="start_datetime" required></label><br><br>
    <label><?= t('end_datetime') ?>: <input type="datetime-local" name="end_datetime" required></label><br><br>
    <button type="submit"><?= t('save') ?></button>
</form>

<h3><?= t('existing_sessions') ?></h3>
<?php if (count($session_rows) === 0): ?>
    <p><?= t('no_sessions') ?></p>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th><?= t('start_datetime') ?></th>
            <th><?= t('end_datetime') ?></th>
            <th><?= t('actions') ?></th>
        </tr>
        <?php foreach ($session_rows as $session): ?>
            <tr>
                <form method="post">
                    <td><input type="datetime-local" name="start_datetime" value="<?= date('Y-m-d\TH:i', strtotime($session['start_time'])) ?>"></td>
                    <td><input type="datetime-local" name="end_datetime" value="<?= date('Y-m-d\TH:i', strtotime($session['end_time'])) ?>"></td>
                    <td>
                        <input type="hidden" name="update_id" value="<?= $session['id'] ?>">
                        <button type="submit"><?= t('update') ?></button>
                        <a href="?page=sessions&course_id=<?= $course_id ?>&delete=<?= $session['id'] ?>" onclick="return confirm('Are you sure?')"><?= t('delete') ?></a>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
