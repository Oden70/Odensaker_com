<?php
// Lägg till denna fil och inkludera den i <head> på alla sidor
require_once __DIR__ . '/db.php';
$company_id = $_SESSION['company_id'] ?? null;
$style = [];
if ($company_id) {
    $stmt = $pdo->prepare("SELECT style_json FROM boka_companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $style = json_decode($stmt->fetchColumn() ?: '', true) ?: [];
}
?>
<style>
:root {
    --primary-color: <?= htmlspecialchars($style['primary_color'] ?? '#1a237e') ?>;
    --font-family: <?= htmlspecialchars($style['font_family'] ?? 'Roboto, Arial, sans-serif') ?>;
}
body, html {
    font-family: var(--font-family);
}
a, .topbar, .sidebar, .main-area button[type="submit"] {
    color: var(--primary-color);
}
button[type="submit"], .sidebar a.active, .sidebar a:hover {
    background: var(--primary-color);
}
</style>
