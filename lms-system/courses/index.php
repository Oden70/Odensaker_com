<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language.php';
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('courses') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php
echo "<h2>" . t('courses') . "</h2>";
echo "<p><a href='?page=create_course'>" . t('add_course') . "</a></p>";

$stmt = $pdo->query("SELECT * FROM lms_courses ORDER BY created_at DESC");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($courses) === 0) {
    echo "<p>" . t('no_courses') . "</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>" . t('course_name') . "</th><th>" . t('actions') . "</th></tr>";
    foreach ($courses as $course) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['title'] ?? '') . "</td>";
        echo "<td>
            <a href='?page=sessions&course_id=" . $course['id'] . "'>" . t('manage_sessions') . "</a>
            &nbsp;|&nbsp;
            <a href='?page=edit_course&course_id=" . $course['id'] . "'>" . t('edit') . "</a>
        </td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
</body>
</html>
