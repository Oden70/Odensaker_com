<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "<div class='alert alert-danger'>Endast superadmin kan skapa företag.</div>";
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $org_number = trim($_POST['org_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');

    // Lägg till http:// om det saknas och fältet inte är tomt
    if ($website && !preg_match('#^https?://#i', $website)) {
        $website = 'http://' . $website;
    }

    $stmt = $pdo->prepare("INSERT INTO boka_companies (name, org_number, address, zip_code, city, country, email, phone, website) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$company_name, $org_number, $address, $zip_code, $city, $country, $email, $phone, $website]);
    $msg = "<div class='alert alert-success'>Företag skapat! Gå vidare och koppla administratörer.</div>";
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>Skapa företag</title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .create-company-content {
            max-width: 40vw;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em;
        }
        .create-company-content label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 500;
            text-align: left;
        }
        .create-company-content input,
        .create-company-content select {
            width: 100%;
            padding: 0.7em;
            margin-bottom: 1em;
            border: 1px solid #bdbdbd;
            border-radius: 5px;
            font-size: 1em;
            background: #fafbfc;
            text-align: left;
        }
        .create-company-content button {
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
        .create-company-content button:hover {
            background: #3949ab;
            color: #fff !important;
        }
    </style>
</head>
<body>
<div class="create-company-content">
    <h2>Skapa nytt företag</h2>
    <?= $msg ?>
    <form method="post">
        <label>Företagsnamn
            <input type="text" name="company_name" required>
        </label>
        <label>Organisationsnummer</label>
        <input type="text" name="org_number" id="org_number" maxlength="11" pattern="\d{6}-\d{4}" title="ÅÅMMDD-XXXX" required style="width:15ch;">
        <label>Adress
            <input type="text" name="address">
        </label>
        <label>Postnummer</label>
        <input type="text" name="zip_code" id="zip_code" maxlength="6" pattern="\d{3}\s\d{2}" title="NNN NN" required style="width:7ch;">
        <label>Ort
            <input type="text" name="city">
        </label>
        <label>Land
            <input type="text" name="country">
        </label>
        <label>E-post
            <input type="email" name="email">
        </label>
        <label>Telefon
            <input type="text" name="phone">
        </label>
        <label>Hemsida (utan http://)
            <input type="text" name="website" placeholder="www.dittforetag.se">
        </label>
        <button type="submit">Skapa företag</button>
    </form>
</div>
<script>
document.getElementById('org_number').addEventListener('input', function(e) {
    let val = this.value.replace(/\D/g, '').slice(0, 10);
    if (val.length > 6) {
        val = val.slice(0,6) + '-' + val.slice(6);
    }
    this.value = val;
});
document.getElementById('zip_code').addEventListener('input', function(e) {
    let val = this.value.replace(/\D/g, '').slice(0, 5);
    if (val.length > 3) {
        val = val.slice(0,3) + ' ' + val.slice(3);
    }
    this.value = val;
});
</script>
</body>
</html>
