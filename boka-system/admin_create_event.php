<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

$is_superadmin = ($_SESSION['role'] ?? '') === 'superadmin';
$msg = '';

// Hämta företag för superadmin
$companies = [];
if ($is_superadmin) {
    $companies = $pdo->query("SELECT id, name FROM boka_companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

if ($is_superadmin) {
    $company_id = $_POST['company_id'] ?? null;
} else {
    $company_id = $_SESSION['company_id'] ?? null;
}

if (!in_array($_SESSION['role'] ?? '', ['superadmin', 'admin', 'booker'])) {
    echo "<div class='alert alert-danger'>Endast administratör/bokare kan skapa event.</div>";
    exit;
}

// Hämta ansvariga/lärare (alla användare kopplade till företaget)
$teachers = [];
if ($company_id) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM boka_users WHERE company_id = ? ORDER BY first_name, last_name");
    $stmt->execute([$company_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hämta kategorier (lägg till tabell boka_categories vid behov)
$categories = $pdo->query("SELECT id, name FROM boka_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $teacher_id = $_POST['teacher_id'] ?? null;
    $max_participants = $_POST['max_participants'] ?? null;
    $price = $_POST['price'] ?? null;
    $price_type = $_POST['price_type'] ?? 'per_tillfalle';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $category_id = $_POST['category_id'] ?? null;
    $image_url = trim($_POST['image_url'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $extra_info = trim($_POST['extra_info'] ?? '');

    // Sätt NULL om datumfält är tomma (för att undvika MySQL date error)
    $start_date = $start_date === '' ? null : $start_date;
    $end_date = $end_date === '' ? null : $end_date;

    if ($company_id) {
        $stmt = $pdo->prepare("INSERT INTO boka_events 
            (company_id, name, description, location, teacher_id, max_participants, price, price_type, is_public, category_id, image_url, video_url, tags, start_date, end_date, extra_info)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $company_id, $event_name, $description, $location, $teacher_id, $max_participants, $price, $price_type, $is_public, $category_id, $image_url, $video_url, $tags, $start_date, $end_date, $extra_info
        ]);
        $msg = "<div class='alert alert-success'>Event skapat!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Välj företag.</div>";
    }
}
?>

<!-- Eventinnehåll för dashboard-content -->
<div class="profile-content" style="max-width:40vw;">
    <h2>Skapa nytt event</h2>
    <?= $msg ?>
    <form method="post" class="login-form">
        <?php if ($is_superadmin): ?>
            <label>Företag</label>
            <select name="company_id" required onchange="this.form.submit()" style="width:320px;max-width:100%;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;display:block;">
                <option value="">Välj företag</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= isset($company_id) && $company_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if (!$is_superadmin || $company_id): ?>
        <label>Eventnamn
            <input type="text" name="event_name" required>
        </label>
        <label>Beskrivning av innehållet</label>
        <textarea name="description" rows="6" style="display:block;width:100%;margin-bottom:1em;"></textarea>
        <label>Plats
            <input type="text" name="location">
        </label>
        <label>Lärare/ansvarig</label>
        <select name="teacher_id" style="width:320px;max-width:100%;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;display:block;">
            <option value="">Välj ansvarig</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Max antal deltagare per tillfälle
            <input type="number" name="max_participants" min="1">
        </label>
        <label>Pris
            <input type="number" name="price" min="0" step="0.01">
        </label>
        <label>Pris avser</label>
        <select name="price_type" style="min-width:180px;max-width:220px;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;">
            <option value="per_tillfalle">Per tillfälle</option>
            <option value="hela_kursen">Hela kursen</option>
        </select>
        <label>
            <input type="checkbox" name="is_public" value="1"> Ska eventet vara publikt?
        </label>
        <label>Kategori</label>
        <select name="category_id" style="min-width:180px;max-width:220px;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;">
            <option value="">Välj kategori</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Länk till bild
            <input type="text" name="image_url">
        </label>
        <label>Länk till video/extern info
            <input type="text" name="video_url">
        </label>
        <label>Taggar (kommaseparerade)
            <input type="text" name="tags">
        </label>
        <label>Startdatum
            <input type="date" name="start_date">
        </label>
        <label>Slutdatum
            <input type="date" name="end_date">
        </label>
        <label>Extra information
            <textarea name="extra_info"></textarea>
        </label>
        <button type="submit" style="background:#1a237e;color:#fff;border:none;border-radius:5px;font-size:1.1em;font-weight:600;padding:0.8em;width:100%;cursor:pointer;transition:background 0.2s;">
            Skapa event
        </button>
        <?php endif; ?>
    </form>
</div>
