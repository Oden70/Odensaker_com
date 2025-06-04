<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "<div class='alert alert-danger'>Endast superadmin kan redigera företag.</div>";
    exit;
}

$msg = '';
$edit_mode = false;
$edit_company = null;

// Hämta alla företag för listning
$companies = $pdo->query("SELECT * FROM boka_companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Om id skickas in, hämta företagets data för redigering
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM boka_companies WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit_company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_company) {
        $msg = "<div class='alert alert-danger'>Företaget kunde inte hittas.</div>";
        $edit_mode = false;
    }
}

// Hantera POST för redigering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $company_name = trim($_POST['company_name'] ?? '');
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
    $stmt->execute([$company_name, $org_number, $address, $zip_code, $city, $country, $email, $phone, $website, $edit_id]);
    $msg = "<div class='alert alert-success'>Företag uppdaterat!</div>";
    // Hämta uppdaterad data
    $stmt = $pdo->prepare("SELECT * FROM boka_companies WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit_company = $stmt->fetch(PDO::FETCH_ASSOC);
    $edit_mode = true;
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>Redigera företag</title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .edit-company-content {
            max-width: 40vw;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em;
        }
        .edit-company-content label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 500;
            text-align: left;
        }
        .edit-company-content input,
        .edit-company-content select {
            width: 100%;
            padding: 0.7em;
            margin-bottom: 1em;
            border: 1px solid #bdbdbd;
            border-radius: 5px;
            font-size: 1em;
            background: #fafbfc;
            text-align: left;
        }
        .edit-company-content button {
            width: 100%;
            padding: 0.8em;
            background: #1a237e;
            color: #fff !important;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .edit-company-content button:hover {
            background: #3949ab;
            color: #fff !important;
        }
        .company-list {
            max-width: 40vw;
            margin: 40px auto 0 auto;
            background: #f7f7f7;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0001;
            padding: 1.5em 2em;
        }
        .company-list ul {
            list-style: none;
            padding: 0;
        }
        .company-list li {
            padding: 0.5em 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .company-list .edit-link {
            background: #1976d2;
            color: #fff !important;
            border: none;
            border-radius: 4px;
            padding: 0.3em 0.9em;
            font-size: 0.95em;
            cursor: pointer;
            text-decoration: none;
            margin-left: 1em;
        }
        .company-list .edit-link:hover {
            background: #1565c0;
        }
    </style>
</head>
<body>
<div class="company-list">
    <h2>Befintliga företag</h2>
    <input type="text" id="companySearch" placeholder="Sök företag..." style="width:100%;padding:0.6em;margin-bottom:1em;border-radius:5px;border:1px solid #bdbdbd;">
    <ul id="companyList">
        <?php foreach ($companies as $c): ?>
            <li>
                <?= htmlspecialchars($c['name']) ?>
                <a href="dashboard.php?page=admin_edit_company&edit=<?= $c['id'] ?>" class="edit-link">Redigera</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<div class="edit-company-content">
    <h2>Redigera företag</h2>
    <?= $msg ?>
    <?php if ($edit_mode && $edit_company): ?>
    <form method="post" id="editCompanyForm">
        <input type="hidden" name="edit_id" value="<?= $edit_company['id'] ?>">
        <label>Företagsnamn
            <input type="text" name="company_name" value="<?= htmlspecialchars($edit_company['name'] ?? '') ?>" required>
        </label>
        <label style="display:flex;align-items:center;gap:0.5em;">
            Organisationsnummer
            <input
                type="text"
                name="org_number"
                id="org_number"
                maxlength="11"
                pattern="\d{6}-\d{4}"
                title="ÅÅMMDD-XXXX"
                required
                style="width:20ch;background:#e0e0e0;flex:0 0 auto;"
                value="<?= htmlspecialchars($edit_company['org_number'] ?? '') ?>"
                readonly
            >
            <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
                <button type="button" id="unlockOrgNumber" style="padding:0.2em 0.15em;min-width:unset;margin-left:0.5em;font-size:1em;" title="Lås upp">&#128274;</button>
            <?php endif; ?>
        </label>
        <label>Adress
            <input type="text" name="address" value="<?= htmlspecialchars($edit_company['address'] ?? '') ?>">
        </label>
        <label>Postnummer</label>
        <input type="text" name="zip_code" id="zip_code" maxlength="6" pattern="\d{3}\s\d{2}" title="NNN NN" required style="width:7ch;" value="<?= htmlspecialchars($edit_company['zip_code'] ?? '') ?>">
        <label>Ort
            <input type="text" name="city" value="<?= htmlspecialchars($edit_company['city'] ?? '') ?>">
        </label>
        <label>Land
            <input type="text" name="country" value="<?= htmlspecialchars($edit_company['country'] ?? '') ?>">
        </label>
        <label>E-post
            <input type="email" name="email" value="<?= htmlspecialchars($edit_company['email'] ?? '') ?>">
        </label>
        <label>Telefon
            <input type="text" name="phone" value="<?= htmlspecialchars($edit_company['phone'] ?? '') ?>">
        </label>
        <label>Hemsida (utan http://)
            <input type="text" name="website" placeholder="www.dittforetag.se" value="<?= htmlspecialchars($edit_company['website'] ?? '') ?>">
        </label>
        <button type="submit">Spara ändringar</button>
        <a href="admin_edit_company.php" style="margin-left:1em;">Avbryt</a>
    </form>
    <script>
    document.getElementById('org_number')?.addEventListener('input', function(e) {
        let val = this.value.replace(/\D/g, '').slice(0, 10);
        if (val.length > 6) {
            val = val.slice(0,6) + '-' + val.slice(6);
        }
        this.value = val;
    });
    document.getElementById('zip_code')?.addEventListener('input', function(e) {
        let val = this.value.replace(/\D/g, '').slice(0, 5);
        if (val.length > 3) {
            val = val.slice(0,3) + ' ' + val.slice(3);
        }
        this.value = val;
    });
    <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
    document.getElementById('unlockOrgNumber')?.addEventListener('click', function() {
        var orgInput = document.getElementById('org_number');
        orgInput.readOnly = false;
        orgInput.style.background = "#fff";
        orgInput.focus();
        this.disabled = true;
        this.innerText = 'Upplåst';
    });
    <?php endif; ?>
    // Sökfunktion för företag
    document.getElementById('companySearch').addEventListener('input', function() {
        var filter = this.value.toLowerCase();
        var items = document.querySelectorAll('#companyList li');
        if (filter.length < 2) {
            items.forEach(function(li) { li.style.display = ''; });
            return;
        }
        items.forEach(function(li) {
            var name = li.textContent.toLowerCase();
            li.style.display = name.includes(filter) ? '' : 'none';
        });
    });
    </script>
    <?php else: ?>
        <div>Välj ett företag ovan för att redigera.</div>
    <?php endif; ?>
</div>
</body>
</html>
