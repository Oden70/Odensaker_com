<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header("Location: login.php");
    exit;
}

// Kontrollera att användaren är admin
$stmt = $pdo->prepare("SELECT role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    echo "Åtkomst nekad.";
    exit;
}

// --- QR code download mode ---
if (isset($_GET['conf_id']) && isset($_GET['download'])) {
    $conf_id = (int)$_GET['conf_id'];
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $url = $protocol . $host . $dir . "/public_attendance.php?conf_id=$conf_id";
    // Byt till JPEG istället för PNG
    $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($url) . '&size=500x500&format=jpg';
    
    $qr_image = file_get_contents($qr_api_url);
    if ($qr_image === false) {
        http_response_code(500);
        echo "Kunde inte generera QR-kod.";
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="qr_konferens_' . $conf_id . '.jpg"');
    header('Content-Length: ' . strlen($qr_image));
    echo $qr_image;
    exit;
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<h2>Generera QR-kod för konferens</h2>

<form method="GET">
    <label for="conf_id">Välj konferens:</label>
    <select name="conf_id" required>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM nv_conferences ORDER BY name");
        while ($conf = $stmt->fetch()) {
            echo "<option value='{$conf['id']}'>" . htmlspecialchars($conf['name']) . "</option>";
        }
        ?>
    </select>
    <button type="submit">Generera QR-kod</button>
</form>

<?php
if (isset($_GET['conf_id']) && !isset($_GET['download'])) {
    $conf_id = (int)$_GET['conf_id'];
    // Dynamiskt generera URL baserat på serverns domän och protokoll
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $url = $protocol . $host . "/attendance.php?conf_id=$conf_id";

    echo "<h3>Skanna för närvaroregistrering:</h3>";
    echo "<img src='https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($url) . "&size=200x200' alt='QR-kod'>";
    // Update download link to use local PHP endpoint
    echo "<p><a href='generate_qr.php?conf_id=$conf_id&download=1'>Ladda ner QR-kod (PNG)</a></p>";
}
?>
