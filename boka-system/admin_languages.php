<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

$lang_dir = __DIR__ . '/languages/';
$msg = '';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo "<div class='alert alert-danger'>Endast superadmin kan hantera språkfiler.</div>";
    exit;
}

// Skapa ny språkfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_lang'])) {
    $new_lang = preg_replace('/[^a-z]/', '', strtolower($_POST['new_lang']));
    if ($new_lang && !file_exists($lang_dir . $new_lang . '.php')) {
        file_put_contents($lang_dir . $new_lang . '.php', "<?php\n\$lang = [\n];\n");
        $msg = "<div class='alert alert-success'>Språkfil skapad: $new_lang.php</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Ogiltigt eller redan existerande språk.</div>";
    }
}

// Spara ändringar i språkfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_lang'], $_POST['lang_content'])) {
    $edit_lang = preg_replace('/[^a-z]/', '', strtolower($_POST['edit_lang']));
    $content = $_POST['lang_content'];
    if ($edit_lang && file_exists($lang_dir . $edit_lang . '.php')) {
        // Endast tillåt PHP-array med $lang
        if (strpos($content, '$lang') !== false) {
            file_put_contents($lang_dir . $edit_lang . '.php', $content);
            $msg = "<div class='alert alert-success'>Språkfilen är uppdaterad.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Språkfilen måste innehålla \$lang-arrayen.</div>";
        }
    }
}

// Lista språkfiler
$lang_files = array_filter(scandir($lang_dir), function($f) {
    return preg_match('/^[a-z]+\.php$/', $f);
});
$selected_lang = $_GET['lang'] ?? 'sv';
$lang_content = '';
if (in_array($selected_lang . '.php', $lang_files)) {
    $lang_content = file_get_contents($lang_dir . $selected_lang . '.php');
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('language_admin') ?? 'Språkhantering' ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        textarea { width:100%; min-height:400px; font-family:monospace; font-size:1em; }
        .lang-list { margin-bottom:1em; }
        .lang-list a { margin-right:10px; }
    </style>
</head>
<body>
<?php include 'includes/menu.php'; ?>
<div class="main-area">
    <div class="topbar">
        <span><?= t('language_admin') ?? 'Språkhantering' ?></span>
        <a href="dashboard.php" style="float:right;"><?= t('dashboard') ?></a>
    </div>
    <div class="content">
        <h2><?= t('language_admin') ?? 'Språkhantering' ?></h2>
        <?= $msg ?>
        <div class="lang-list">
            <strong><?= t('existing_languages') ?? 'Befintliga språk:' ?></strong>
            <?php foreach ($lang_files as $file): 
                $code = basename($file, '.php'); ?>
                <a href="?lang=<?= $code ?>" <?= $selected_lang === $code ? 'style="font-weight:bold;"' : '' ?>><?= htmlspecialchars($code) ?></a>
            <?php endforeach; ?>
        </div>
        <form method="post" style="margin-bottom:2em;">
            <label><?= t('create_new_language') ?? 'Skapa nytt språk (t.ex. fi, de, no):' ?>
                <input type="text" name="new_lang" maxlength="10" style="width:100px;">
            </label>
            <button type="submit"><?= t('create') ?? 'Skapa' ?></button>
        </form>
        <?php if ($lang_content): ?>
        <form method="post">
            <input type="hidden" name="edit_lang" value="<?= htmlspecialchars($selected_lang) ?>">
            <label><?= t('edit_language_file') ?? 'Redigera språkfil:' ?> <?= htmlspecialchars($selected_lang) ?>.php</label>
            <textarea name="lang_content"><?= htmlspecialchars($lang_content) ?></textarea>
            <button type="submit"><?= t('save') ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
