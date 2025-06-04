<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language.php';

// Hämta kursen
$course_id = (int)($_GET['course_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM lms_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    echo "<div class='alert alert-danger'>" . t('course_not_found') . "</div>";
    return;
}

// Hämta användare för kursadmin och kursansvarig
$all_users = $pdo->query("SELECT id, first_name, last_name FROM lms_users ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $short_description = substr($_POST['short_description'] ?? '', 0, 255);
    $info = $_POST['info'] ?? '';
    $show_in_catalog = isset($_POST['show_in_catalog']) ? 1 : 0;
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    $keywords = $_POST['keywords'] ?? '';
    $admin_id = $_POST['admin_id'] ?? null;
    $responsible_id = $_POST['responsible_id'] ?? null;
    $certificate_text = substr($_POST['certificate_text'] ?? '', 0, 255);
    $certificate_responsible = $_POST['certificate_responsible'] ?? null;

    // Om fältet certificate_responsible är ett textfält, spara som text, inte id
    // Kontrollera om kolumnen i databasen är VARCHAR/TEXT, annars ändra databasens kolumntyp från INT till VARCHAR(255)

    // Hantera bilduppladdning
    $image = $course['image'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = __DIR__ . '/../uploads/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = uniqid('course_', true) . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = 'uploads/' . $filename;
        }
    }

    $stmt = $pdo->prepare("UPDATE lms_courses SET 
        title = ?, short_description = ?, image = ?, info = ?, show_in_catalog = ?, date_from = ?, date_to = ?, keywords = ?, admin_id = ?, responsible_id = ?, certificate_text = ?, certificate_responsible = ?
        WHERE id = ?");
    $stmt->execute([
        $title, $short_description, $image, $info, $show_in_catalog, $date_from, $date_to, $keywords,
        $admin_id, $responsible_id, $certificate_text, $certificate_responsible, $course_id
    ]);
    echo "<div class='alert alert-success'>" . t('course_updated') . "</div>";

    // Hämta uppdaterad kurs
    $stmt = $pdo->prepare("SELECT * FROM lms_courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('edit') ?>: <?= htmlspecialchars($course['title']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        tinymce.init({
            selector: 'textarea[name="info"]',
            height: 250,
            menubar: false,
            plugins: 'lists link table code',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link table | code',
            branding: false,
            language: 'sv'
        });

        function setupUserSearch(inputId, hiddenId, userList, selectedId) {
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            const dropdown = document.createElement('div');
            dropdown.className = 'user-search-dropdown';
            dropdown.style.position = 'absolute';
            dropdown.style.background = '#fff';
            dropdown.style.border = '1px solid #ccc';
            dropdown.style.zIndex = 1000;
            dropdown.style.display = 'none';
            input.parentNode.appendChild(dropdown);

            // Förifyll om redan valt
            if (selectedId) {
                const user = userList.find(u => u.id == selectedId);
                if (user) input.value = user.first_name + ' ' + user.last_name;
                hidden.value = selectedId;
            }

            input.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase();
                dropdown.innerHTML = '';
                if (val.length < 2) {
                    dropdown.style.display = 'none';
                    return;
                }
                const matches = userList.filter(u =>
                    (u.first_name + ' ' + u.last_name).toLowerCase().includes(val)
                );
                if (matches.length === 0) {
                    dropdown.style.display = 'none';
                    return;
                }
                matches.forEach(u => {
                    const opt = document.createElement('div');
                    opt.textContent = u.first_name + ' ' + u.last_name;
                    opt.style.padding = '6px 10px';
                    opt.style.cursor = 'pointer';
                    opt.addEventListener('mousedown', function(e) {
                        input.value = u.first_name + ' ' + u.last_name;
                        hidden.value = u.id;
                        dropdown.style.display = 'none';
                        e.preventDefault();
                    });
                    dropdown.appendChild(opt);
                });
                dropdown.style.display = 'block';
            });
            input.addEventListener('blur', function() {
                setTimeout(() => dropdown.style.display = 'none', 150);
            });
        }
        const userList = <?= json_encode($all_users) ?>;
        setupUserSearch('admin_search', 'admin_id', userList, <?= json_encode($course['admin_id']) ?>);
        setupUserSearch('responsible_search', 'responsible_id', userList, <?= json_encode($course['responsible_id']) ?>);

        // Teckenräknare för kursintyg
        var certText = document.getElementById('certificate_text');
        var certCounter = document.getElementById('cert_text_counter');
        if (certText && certCounter) {
            certText.addEventListener('input', function() {
                if (this.value.length > 255) {
                    this.value = this.value.substring(0, 255);
                }
                certCounter.textContent = this.value.length + "/255";
            });
            certCounter.textContent = certText.value.length + "/255";
        }
    });
    </script>
    <style>
        .user-search-dropdown { max-height: 180px; overflow-y: auto; border-radius: 0 0 6px 6px; }
        .user-search-dropdown div:hover { background: #e3e3f7; }
        .user-search-wrap { position: relative; }
        .char-counter { font-size: 0.95em; color: #666; float: right; }
    </style>
</head>
<body>
<div class="profile-container" style="max-width:600px;margin:40px auto;">
    <h2><?= t('edit') ?>: <?= htmlspecialchars($course['title']) ?></h2>
    <form method="POST" enctype="multipart/form-data" class="login-form" autocomplete="off">
        <label><?= t('title') ?>
            <input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>" required>
        </label>
        <label><?= t('short_description') ?>
            <input type="text" name="short_description" maxlength="255" value="<?= htmlspecialchars($course['short_description'] ?? '') ?>">
        </label>
        <label><?= t('upload_image') ?>
            <?php if (!empty($course['image'])): ?>
                <br>
                <img src="../<?= htmlspecialchars($course['image']) ?>" alt="Kursbild" style="max-width:120px;max-height:80px;display:block;margin-bottom:8px;">
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
        </label>
        <label><?= t('course_info') ?>
            <textarea name="info" rows="4"><?= htmlspecialchars($course['info'] ?? '') ?></textarea>
        </label>
        <label>
            <input type="checkbox" name="show_in_catalog" value="1" <?= !empty($course['show_in_catalog']) ? 'checked' : '' ?>> <?= t('show_in_catalog') ?>
        </label>
        <label><?= t('date_from') ?>
            <input type="date" name="date_from" value="<?= htmlspecialchars($course['date_from'] ?? '') ?>">
        </label>
        <label><?= t('date_to') ?>
            <input type="date" name="date_to" value="<?= htmlspecialchars($course['date_to'] ?? '') ?>">
        </label>
        <label><?= t('keywords') ?>
            <input type="text" name="keywords" value="<?= htmlspecialchars($course['keywords'] ?? '') ?>">
        </label>
        <label><?= t('course_admin') ?>
            <div class="user-search-wrap">
                <input type="text" id="admin_search" placeholder="<?= t('search_user') ?>" autocomplete="off">
                <input type="hidden" name="admin_id" id="admin_id">
            </div>
        </label>
        <label><?= t('course_responsible') ?>
            <div class="user-search-wrap">
                <input type="text" id="responsible_search" placeholder="<?= t('search_user') ?>" autocomplete="off">
                <input type="hidden" name="responsible_id" id="responsible_id">
            </div>
        </label>
        <label><?= t('certificate_text') ?></label>
        <textarea id="certificate_text" name="certificate_text" maxlength="255" rows="3" style="resize:vertical; width:100%;"><?= htmlspecialchars($course['certificate_text'] ?? '') ?></textarea>
        <div style="text-align:right; margin-top:2px; margin-bottom:1em;">
            <span class="char-counter" id="cert_text_counter"></span>
        </div>
        <label><?= t('certificate_responsible') ?>
            <input type="text" name="certificate_responsible" maxlength="255" value="<?= htmlspecialchars($course['certificate_responsible'] ?? '') ?>">
        </label>
        <button type="submit"><?= t('save') ?></button>
    </form>
</div>
</body>
</html>
