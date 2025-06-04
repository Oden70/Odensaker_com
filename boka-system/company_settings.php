<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Kontrollera roll
$is_superadmin = ($_SESSION['role'] ?? '') === 'superadmin';
$company_id = $_SESSION['company_id'] ?? null;
$msg = '';
$company = null;

// Superadmin kan välja företag
if ($is_superadmin) {
    // Hämta alla företag
    $companies = $pdo->query("SELECT id, name FROM boka_companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    // Välj företag via GET eller POST
    if (isset($_POST['selected_company_id'])) {
        $selected_company_id = (int)$_POST['selected_company_id'];
    } elseif (isset($_GET['company_id'])) {
        $selected_company_id = (int)$_GET['company_id'];
    } else {
        $selected_company_id = $companies[0]['id'] ?? null;
    }
} else {
    $selected_company_id = $company_id;
}

// Hämta företagets data
if ($selected_company_id) {
    $stmt = $pdo->prepare("SELECT * FROM boka_companies WHERE id = ?");
    $stmt->execute([$selected_company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Hantera POST för att spara ändringar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company']) && $company) {
    $name = trim($_POST['name'] ?? '');
    $org_number = trim($_POST['org_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');

    if ($website && !preg_match('#^https?://#i', $website)) {
        $website = 'http://' . $website;
    }

    $stmt = $pdo->prepare("UPDATE boka_companies SET name=?, org_number=?, address=?, zip_code=?, city=?, country=?, email=?, phone=?, website=? WHERE id=?");
    $stmt->execute([$name, $org_number, $address, $zip_code, $city, $country, $email, $phone, $website, $selected_company_id]);
    $msg = "<div class='alert alert-success'>Företagsuppgifter uppdaterade!</div>";

    // Hämta uppdaterad data
    $stmt = $pdo->prepare("SELECT * FROM boka_companies WHERE id = ?");
    $stmt->execute([$selected_company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lägg till denna rad överst för att säkerställa att sidan kan inkluderas i dashboard.php utan egen <html> och <body>
if (basename($_SERVER['SCRIPT_NAME']) !== 'dashboard.php') {
    // Om sidan öppnas direkt, rendera som fristående sida
    ?>
    <!DOCTYPE html>
    <html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
    <head>
        <meta charset="UTF-8">
        <title><?= t('company') ?></title>
        <link rel="stylesheet" href="assets/style.css">
        <?php include 'includes/company_style.php'; ?>
    </head>
    <body>
    <?php include 'includes/menu.php'; ?>
    <div class="main-area">
        <div class="topbar">
            <span><?= t('company') ?> | <?= htmlspecialchars($company['name'] ?? '') ?></span>
            <a href="dashboard.php" style="float:right;"><?= t('dashboard') ?></a>
        </div>
        <div class="content">
    <?php
}
?>
<div class="company-settings-content" style="max-width:40vw;margin:40px auto;background:#fff;border-radius:10px;box-shadow:0 4px 24px #0001;padding:2em;">
    <h2><?= t('company_settings') ?? 'Företagsinställningar' ?></h2>
    <?= $msg ?>
    <?php if ($is_superadmin): ?>
        <form method="post" style="margin-bottom:2em;">
            <label>Välj företag:
                <select name="selected_company_id" onchange="this.form.submit()" style="width:100%;padding:0.7em;margin-bottom:1em;">
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_company_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button type="submit">Välj</button></noscript>
        </form>
    <?php endif; ?>

    <?php if ($company): ?>
        <form method="post">
            <input type="hidden" name="selected_company_id" value="<?= $selected_company_id ?>">
            <label>Företagsnamn
                <input type="text" name="name" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
            </label>
            <label>Organisationsnummer
                <input type="text" name="org_number" value="<?= htmlspecialchars($company['org_number'] ?? '') ?>" required>
            </label>
            <label>Adress
                <input type="text" name="address" value="<?= htmlspecialchars($company['address'] ?? '') ?>">
            </label>
            <label>Postnummer
                <input type="text" name="zip_code" value="<?= htmlspecialchars($company['zip_code'] ?? '') ?>">
            </label>
            <label>Ort
                <input type="text" name="city" value="<?= htmlspecialchars($company['city'] ?? '') ?>">
            </label>
            <label>Land
                <input type="text" name="country" value="<?= htmlspecialchars($company['country'] ?? '') ?>">
            </label>
            <label>E-post
                <input type="email" name="email" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
            </label>
            <label>Telefon
                <input type="text" name="phone" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
            </label>
            <label>Hemsida (utan http://)
                <input type="text" name="website" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
            </label>
            <button type="submit" name="save_company">Spara ändringar</button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">Inget företag valt eller hittat.</div>
    <?php endif; ?>
</div>
<?php
// Avsluta wrapper om sidan öppnas direkt
if (basename($_SERVER['SCRIPT_NAME']) !== 'dashboard.php') {
    ?>
        </div>
    </div>
    </body>
    </html>
    <?php
}
