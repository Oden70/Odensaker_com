<?php
// ...existing code for authentication and DB connection...

// Lägg till PDO-anslutning här om den inte redan finns
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=din_databas;charset=utf8',
            'användare',
            'lösenord',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Exception $e) {
        die("<div style='color:red'>Kunde inte ansluta till databasen: " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

// Hämta alla företag från databasen
$companies = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM companies ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div style='color:red'>Kunde inte hämta företag: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Hämta företagets data om id är valt
$company_id = $_GET['id'] ?? null;
$company = null;
if ($company_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "<div style='color:red'>Kunde inte hämta företag: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Spara ändringar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $company_id) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $orgnr = $_POST['orgnr'] ?? '';
    $address = $_POST['address'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';
    $city = $_POST['city'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    try {
        $stmt = $pdo->prepare("UPDATE companies SET name=?, description=?, orgnr=?, address=?, zipcode=?, city=?, phone=?, email=? WHERE id=?");
        $stmt->execute([$name, $description, $orgnr, $address, $zipcode, $city, $phone, $email, $company_id]);
        echo "<div>Företaget sparat!</div>";
        // Hämta uppdaterad data
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "<div style='color:red'>Kunde inte spara företag: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redigera företag</title>
</head>
<body>
    <h1>Redigera företag</h1>
    <?php if (!$company_id): ?>
        <h2>Välj företag att redigera:</h2>
        <ul>
            <?php foreach ($companies as $c): ?>
                <li>
                    <a href="edit_company.php?id=<?php echo $c['id']; ?>">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif ($company): ?>
        <form method="post">
            <label>
                Namn:<br>
                <input type="text" name="name" value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
            </label>
            <br>
            <label>
                Beskrivning:<br>
                <textarea name="description"><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
            </label>
            <br>
            <label>
                Organisationsnummer:<br>
                <input type="text" name="orgnr" value="<?php echo htmlspecialchars($company['orgnr'] ?? ''); ?>">
            </label>
            <br>
            <label>
                Adress:<br>
                <input type="text" name="address" value="<?php echo htmlspecialchars($company['address'] ?? ''); ?>">
            </label>
            <br>
            <label>
                Postnummer:<br>
                <input type="text" name="zipcode" value="<?php echo htmlspecialchars($company['zipcode'] ?? ''); ?>">
            </label>
            <br>
            <label>
                Ort:<br>
                <input type="text" name="city" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
            </label>
            <br>
            <label>
                Telefon:<br>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
            </label>
            <br>
            <label>
                E-post:<br>
                <input type="email" name="email" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
            </label>
            <br>
            <button type="submit">Spara</button>
        </form>
        <p><a href="edit_company.php">Tillbaka till företagslista</a></p>
    <?php else: ?>
        <div style="color:red">Företaget kunde inte hittas.</div>
        <p><a href="edit_company.php">Tillbaka till företagslista</a></p>
    <?php endif; ?>
</body>
</html>
