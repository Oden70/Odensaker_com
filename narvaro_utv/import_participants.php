<?php
require_once 'db.php';
session_start();

// Kontrollera att användaren är inloggad och admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'admin') {
    echo "Åtkomst nekad.";
    exit;
}

// Hantera import av deltagare från CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['conference_id'])) {
    $conferenceId = (int)$_POST['conference_id'];
    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $fileName = $_FILES['csv_file']['name'];
    $fileSize = $_FILES['csv_file']['size'];
    $fileType = $_FILES['csv_file']['type'];
    $errorMsg = '';

    // Kontrollera filtyp (endast CSV)
    $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
    if (!in_array($fileType, $allowedTypes)) {
        $errorMsg = 'Endast CSV-filer är tillåtna.';
    }

    // Kontrollera filstorlek (max 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        $errorMsg = 'Filen är för stor. Maximal storlek är 5MB.';
    }

    if (empty($errorMsg)) {
        $participants = [];
        if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
            // Hoppa över första raden (rubriker)
            fgetcsv($handle, 1000, ";");
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                // Anta att CSV-kolumnerna är i ordningen: Förnamn, Efternamn, HSA-ID, E-post, Bord
                if (count($data) >= 5) {
                    $bord = isset($data[4]) && trim($data[4]) !== '' ? trim($data[4]) : null;
                    $participants[] = [
                        'conference_id' => $conferenceId,
                        'fornamn' => $data[0],
                        'efternamn' => $data[1],
                        'hsaid' => $data[2],
                        'email' => $data[3],
                        'bord' => $bord
                    ];
                }
            }
            fclose($handle);
        }

        // Sätt in deltagarna i databasen
        if (count($participants) > 0) {
            $stmt = $pdo->prepare("INSERT INTO nv_participants (conference_id, fornamn, efternamn, hsaid, email, bord) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($participants as $participant) {
                $stmt->execute([
                    $participant['conference_id'],
                    $participant['fornamn'],
                    $participant['efternamn'],
                    $participant['hsaid'],
                    $participant['email'],
                    $participant['bord']
                ]);
            }
            $importMessage = "<div class='alert alert-success' style='margin-bottom:1em;'>" . count($participants) . " deltagare importerades!</div>";
        } else {
            $importMessage = "<div class='alert alert-danger' style='margin-bottom:1em;'>Inga deltagare importerades.</div>";
        }
    } else {
        $importMessage = "<div class='alert alert-danger' style='margin-bottom:1em;'>$errorMsg</div>";
    }
}
include 'toppen.php';
?>
<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <div class="admin-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Importera deltagare från CSV</h2>
    </div>
    <div class="admin-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <?php if (isset($importMessage)) echo $importMessage; ?>
        <div class="row">
            <div class="col-md-6">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="conference_id" class="form-label">Välj konferens:</label>
                        <select name="conference_id" class="form-select" required>
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM nv_conferences ORDER BY name");
                            while ($conf = $stmt->fetch()) {
                                echo "<option value='{$conf['id']}'>" . htmlspecialchars($conf['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Välj fil:</label>
                        <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Importera CSV</button>
                </form>
            </div>
            <div class="col-md-6 d-flex flex-column align-items-center justify-content-center">
                <a href="example_participants_202506011812.csv" class="btn btn-outline-secondary btn-sm mb-3">Ladda ner exempelfil</a>
                <div style="font-size:0.95em;color:#555;">
                    <strong>CSV-format:</strong> CSV-UTF-8 (kommaavgränsad)
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'botten.php'; ?>
