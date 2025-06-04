<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['conference_id'])) {
    $conferenceId = $_POST['conference_id'];
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== false) {
        $headers = fgetcsv($handle, 1000, ",");
        $columns = array_map('trim', $headers);
        $columns[] = 'conference_id'; // Lägg till konferens-ID som extra kolumn

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $columnList = implode(',', $columns);

        $stmt = $pdo->prepare("INSERT INTO nv_participants ($columnList) VALUES ($placeholders)");

        $imported = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $data = array_map('trim', $data);
            $data[] = $conferenceId; // Lägg till konferens-ID till varje rad
            $stmt->execute($data);
            $imported++;
        }

        fclose($handle);
        echo "<p style='color:green;'>$imported deltagare importerades.</p>";
    } else {
        echo "<p style='color:red;'>Kunde inte läsa CSV-filen.</p>";
    }
}
?>

<h3>Importera deltagare från CSV</h3>
<form method="POST" enctype="multipart/form-data">
    <label for="conference_id">Välj konferens:</label>
    <select name="conference_id" required>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM nv_conferences ORDER BY name");
        while ($conf = $stmt->fetch()) {
            echo "<option value='{$conf['id']}'>" . htmlspecialchars($conf['name']) . "</option>";
        }
        ?>
    </select><br><br>

    <input type="file" name="csv_file" accept=".csv" required><br><br>
    <button type="submit">Importera CSV</button>
</form>
