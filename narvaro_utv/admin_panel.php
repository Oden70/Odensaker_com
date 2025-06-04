<?php
session_start();
require 'db.php';

// --- AJAX-endpoint för att hämta statistik för konferens ---
if (
    isset($_GET['ajax_stats']) &&
    isset($_GET['conf_id']) &&
    is_numeric($_GET['conf_id'])
) {
    $conf_id = (int)$_GET['conf_id'];
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
    $countStmt->execute([$conf_id]);
    $participantCount = (int)$countStmt->fetchColumn();

    $presentStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ? AND present = 1");
    $presentStmt->execute([$conf_id]);
    $presentCount = (int)$presentStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'participantCount' => $participantCount,
        'presentCount' => $presentCount
    ]);
    exit;
}

$deleteConferenceWarning = '';

// --- Exportera deltagarlista: MÅSTE ligga först innan någon output sker ---
if (
    isset($_POST['export_participants']) &&
    isset($_POST['export_conf_id']) &&
    isset($_POST['export_fields']) &&
    is_array($_POST['export_fields'])
) {
    $fields = $_POST['export_fields'];
    $allowed = [
        'fornamn'         => 'Förnamn',
        'efternamn'       => 'Efternamn',
        'hsaid'           => 'HSA-ID',
        'email'           => 'E-post',
        'present'         => 'Närvaro',
        'self_registered' => 'Självregistrerad'
    ];
    $selected = array_intersect_key($allowed, array_flip($fields));
    if (count($selected) > 0) {
        $conf_id = (int)$_POST['export_conf_id'];
        $columns = array_keys($selected);
        $header  = array_values($selected);

        // Hämta konferensnamn för filnamn
        $nameStmt = $pdo->prepare("SELECT name FROM nv_conferences WHERE id = ?");
        $nameStmt->execute([$conf_id]);
        $confName = $nameStmt->fetchColumn();

        // Ersätt å,ä,ö/Å,Ä,Ö med a/o/A/O och ta bort ogiltiga tecken
        $filename = $confName;
        $filename = str_replace(['å','ä','ö','Å','Ä','Ö'], ['a','a','o','A','A','O'], $filename);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $filename);

        $sql = "SELECT " . implode(", ", $columns) . " FROM nv_participants WHERE conference_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$conf_id]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');
        // Skriv BOM för UTF-8 så Excel hanterar åäö korrekt
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        fputcsv($output, $header, ";");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['present'])) {
                $row['present'] = $row['present'] ? 'Ja' : 'Nej';
            }
            if (isset($row['self_registered'])) {
                $row['self_registered'] = $row['self_registered'] ? 'Ja' : 'Nej';
            }
            fputcsv($output, array_intersect_key($row, array_flip($columns)), ";");
        }
        fclose($output);
        exit;
    }
}

$pageTitle = "Adminpanel";

// Kontrollera att användaren är admin
$stmt = $pdo->prepare("SELECT role FROM nv_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    echo "Åtkomst nekad.";
    exit;
}

// Hantera formulär för att skapa konto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];

    $stmt = $pdo->prepare(
        "INSERT INTO nv_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$username, $email, $password, $role]);

    $success = "Användare skapad!";
}

// --- Importera deltagare från CSV ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['csv_file']) &&
    isset($_POST['conference_id'])
) {
    $conferenceId = (int)$_POST['conference_id'];
    $fileTmpPath  = $_FILES['csv_file']['tmp_name'];
    $fileName     = $_FILES['csv_file']['name'];
    $fileSize     = $_FILES['csv_file']['size'];
    $fileType     = $_FILES['csv_file']['type'];
    $errorMsg     = '';

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
        // Läs in CSV-filen och förbered data för insättning i databasen
        $participants = [];
        if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
            // Hoppa över första raden (rubriker)
            fgetcsv($handle, 1000, ";");
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                // Anta att CSV-kolumnerna är i ordningen: Förnamn, Efternamn, HSA-ID, E-post, Närvaro
                if (count($data) >= 4) { // Minst 4 kolumner krävs
                    $participants[] = [
                        'conference_id' => $conferenceId,
                        'fornamn'       => $data[0],
                        'efternamn'     => $data[1],
                        'hsaid'         => $data[2],
                        'email'         => $data[3],
                        'present'       => isset($data[4]) && strtolower($data[4]) === 'ja' ? 1 : 0
                    ];
                }
            }
            fclose($handle);
        }

        // Sätt in deltagarna i databasen
        if (count($participants) > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO nv_participants (conference_id, fornamn, efternamn, hsaid, email, present) VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($participants as $participant) {
                $stmt->execute(array_values($participant));
            }
            $_SESSION['deleteConferenceWarning'] =
                "<div class='alert alert-success' style='margin-bottom:1em;'>" .
                count($participants) . " deltagare importerades!</div>";
        } else {
            $_SESSION['deleteConferenceWarning'] =
                "<div class='alert alert-danger' style='margin-bottom:1em;'>Inga deltagare importerades.</div>";
        }
    } else {
        $_SESSION['deleteConferenceWarning'] =
            "<div class='alert alert-danger' style='margin-bottom:1em;'>$errorMsg</div>";
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera uppdatering av bord på deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_bord']) &&
    isset($_POST['bord_ids']) &&
    is_array($_POST['bord_ids'])
) {
    foreach ($_POST['bord_ids'] as $pid => $bord) {
        $bordValue = (trim($bord) === '') ? null : trim($bord);
        $stmt = $pdo->prepare("UPDATE nv_participants SET bord = ? WHERE id = ?");
        $stmt->execute([$bordValue, (int)$pid]);
    }
    $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Bordsplaceringar uppdaterade.</div>';
    // Håll deltagarlistan öppen efter redirect
    $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
    header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
    exit;
}

// --- Hantera borttagning av deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_participants'], $_POST['conf_id']) &&
    !empty($_POST['participant_ids']) &&
    is_array($_POST['participant_ids'])
) {
    $ids = array_map('intval', $_POST['participant_ids']);
    if (count($ids) > 0) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM nv_participants WHERE id IN ($in)");
        $stmt->execute($ids);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">' . count($ids) . ' deltagare borttagna.</div>';
        // Håll deltagarlistan öppen efter redirect
        $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
        exit;
    }
}

// --- Hantera borttagning av konferens ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conference'], $_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    // Hämta konferensnamn för meddelande
    $stmt = $pdo->prepare("SELECT name FROM nv_conferences WHERE id = ?");
    $stmt->execute([$conf_id]);
    $confName = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
    $stmt->execute([$conf_id]);
    $participantCount = $stmt->fetchColumn();
    if ($participantCount > 0) {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-danger" style="margin-bottom:1em;">Det går inte att ta bort konferensen <strong>' . htmlspecialchars($confName) . '</strong> eftersom det finns deltagare kopplade till den. Ta bort alla deltagare först.</div>';
    } else {
        $stmt = $pdo->prepare("DELETE FROM nv_conferences WHERE id = ?");
        $stmt->execute([$conf_id]);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Konferensen <strong>' . htmlspecialchars($confName) . '</strong> har tagits bort.</div>';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera ändring av synlighet ---
if (isset($_POST['set_public']) && isset($_POST['conf_id']) && isset($_POST['public_visible'])) {
    $stmt = $pdo->prepare("UPDATE nv_conferences SET public_visible = ? WHERE id = ?");
    $stmt->execute([$_POST['public_visible'], $_POST['conf_id']]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera val av sökbara fält för publik närvaroregistrering ---
if (isset($_POST['save_public_search_fields']) && isset($_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    $fields = isset($_POST['public_search_fields']) ? $_POST['public_search_fields'] : [];
    $fields_str = implode(',', $fields);
    $stmt = $pdo->prepare("UPDATE nv_conferences SET public_search_fields = ? WHERE id = ?");
    $stmt->execute([$fields_str, $conf_id]);
    $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Sökbara fält för publik registrering uppdaterade!</div>';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera val om "bord" ska skickas med i mejl ---
if (isset($_POST['save_bord_mail_option']) && isset($_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    $send_bord = isset($_POST['send_bord_in_mail']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE nv_conferences SET send_bord_in_mail = ? WHERE id = ?");
    $stmt->execute([$send_bord, $conf_id]);
    // Visa tydligt i feedback om bordsplacering skickas eller ej
    if ($send_bord) {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Inställning uppdaterad: <strong>Bordsplacering kommer att skickas med i bekräftelsemejl.</strong></div>';
    } else {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Inställning uppdaterad: <strong>Bordsplacering kommer <u>inte</u> att skickas med i bekräftelsemejl.</strong></div>';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- AJAX-endpoint för att hämta deltagarlistan för en konferens ---
if (
    isset($_GET['ajax_participants']) &&
    isset($_GET['conf_id']) &&
    is_numeric($_GET['conf_id'])
) {
    $conf_id = (int)$_GET['conf_id'];
    $search = trim($_GET['search'] ?? '');
    $params = [$conf_id];
    $where = '';
    if ($search !== '') {
        $where = "AND (fornamn LIKE ? OR efternamn LIKE ? OR hsaid LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql = "SELECT id, fornamn, efternamn, hsaid, email, present, self_registered, bord FROM nv_participants WHERE conference_id = ? $where ORDER BY fornamn, efternamn";
    $partStmt = $pdo->prepare($sql);
    $partStmt->execute($params);
    $participants = $partStmt->fetchAll();
    $searchValue = htmlspecialchars($search, ENT_QUOTES);
    ?>
    <!-- Dynamiskt sökfält överst -->
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" oninput="searchParticipants_<?php echo $conf_id; ?>()" value="<?php echo $searchValue; ?>">
    </div>
    <form method="POST" id="delete_participants_form_<?php echo $conf_id; ?>" onsubmit="return confirm('Är du säker på att du vill ta bort markerade deltagare?');">
        <input type="hidden" name="delete_participants" value="1">
        <input type="hidden" name="conf_id" value="<?php echo $conf_id; ?>">
        <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white">
                <thead class="table-light"><tr>
                    <th style="width:2em;"><input type="checkbox" id="checkall_<?php echo $conf_id; ?>" onclick="toggleAllCheckboxes_<?php echo $conf_id; ?>()"></th>
                    <th>Förnamn</th><th>Efternamn</th><th>HSA-ID</th><th>E-post</th><th>Närvaro</th><th>Självregistrerad</th><th>Bord</th>
                </tr></thead>
                <tbody>
                <?php foreach ($participants as $p):
                    $hasBord = trim($p['bord']) !== '';
                    $inputId = 'bord_input_' . $p['id'];
                    $btnId = 'edit_bord_btn_' . $p['id'];
                    $isPresent = (int)$p['present'] === 1;
                ?>
                    <tr>
                        <td><input type="checkbox" name="participant_ids[]" value="<?php echo $p['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($p['fornamn']); ?></td>
                        <td><?php echo htmlspecialchars($p['efternamn']); ?></td>
                        <td><?php echo htmlspecialchars($p['hsaid']); ?></td>
                        <td><?php echo htmlspecialchars($p['email']); ?></td>
                        <td><?php echo $isPresent ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nej</span>'; ?></td>
                        <td><?php echo $p['self_registered'] ? 'Ja' : 'Nej'; ?></td>
                        <td style="min-width:120px;">
                            <div class="input-group input-group-sm">
                                <input type="text" name="bord_ids[<?php echo $p['id']; ?>]" id="<?php echo $inputId; ?>" value="<?php echo htmlspecialchars($p['bord']); ?>" class="form-control" style="min-width:60px;max-width:120px;" <?php echo $hasBord ? 'readonly style="background:#eee;cursor:not-allowed;"' : ''; ?>>
                                <button type="button" class="btn btn-outline-secondary" id="<?php echo $btnId; ?>" onclick="unlockBordField('<?php echo $inputId; ?>', '<?php echo $btnId; ?>')" <?php echo !$hasBord ? 'style="display:none;"' : ''; ?> title="Redigera"><span class="bi bi-pencil"></span> &#9998;</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button type="submit" class="btn btn-danger btn-sm" name="delete_participants" value="1" onclick="return confirm('Är du säker på att du vill ta bort markerade deltagare?');">Ta bort markerade</button>
                <button type="button" class="btn btn-outline-secondary btn-sm mx-2" onclick="refreshParticipants_<?php echo $conf_id; ?>()">Uppdatera lista</button>
                <button type="submit" class="btn btn-primary btn-sm" name="update_bord" value="1" style="margin-left:auto;" onclick="this.form.onsubmit=null;">Spara bordsplaceringar</button>
            </div>
        </div>
    </form>
    <script>
    let searchTimeout_<?php echo $conf_id; ?> = null;
    function searchParticipants_<?php echo $conf_id; ?>() {
        clearTimeout(searchTimeout_<?php echo $conf_id; ?>);
        searchTimeout_<?php echo $conf_id; ?> = setTimeout(function() {
            refreshParticipants_<?php echo $conf_id; ?>();
        }, 200);
    }
    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                // Spara nuvarande sökvärde
                var currentValue = search.value;
                container.innerHTML = xhr.responseText;
                // Återställ sökvärdet efter AJAX-refresh
                var newSearch = document.getElementById("search_input_<?php echo $conf_id; ?>");
                if (newSearch) {
                    newSearch.value = currentValue;
                    newSearch.focus();
                    newSearch.setSelectionRange(currentValue.length, currentValue.length);
                }
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>(){var c=document.getElementById("checkall_<?php echo $conf_id; ?>");var boxes=document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");for(var i=0;i<boxes.length;i++){boxes[i].checked=c.checked;}}
    </script>
    <?php
    exit;
}

// --- Hantera uppdatering av bord på deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_bord']) &&
    isset($_POST['bord_ids']) &&
    is_array($_POST['bord_ids'])
) {
    foreach ($_POST['bord_ids'] as $pid => $bord) {
        $bordValue = (trim($bord) === '') ? null : trim($bord);
        $stmt = $pdo->prepare("UPDATE nv_participants SET bord = ? WHERE id = ?");
        $stmt->execute([$bordValue, (int)$pid]);
    }
    $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Bordsplaceringar uppdaterade.</div>';
    // Håll deltagarlistan öppen efter redirect
    $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
    header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
    exit;
}

// --- Hantera borttagning av deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_participants'], $_POST['conf_id']) &&
    !empty($_POST['participant_ids']) &&
    is_array($_POST['participant_ids'])
) {
    $ids = array_map('intval', $_POST['participant_ids']);
    if (count($ids) > 0) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM nv_participants WHERE id IN ($in)");
        $stmt->execute($ids);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">' . count($ids) . ' deltagare borttagna.</div>';
        // Håll deltagarlistan öppen efter redirect
        $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
        exit;
    }
}

// --- Hantera borttagning av konferens ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conference'], $_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    // Hämta konferensnamn för meddelande
    $stmt = $pdo->prepare("SELECT name FROM nv_conferences WHERE id = ?");
    $stmt->execute([$conf_id]);
    $confName = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
    $stmt->execute([$conf_id]);
    $participantCount = $stmt->fetchColumn();
    if ($participantCount > 0) {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-danger" style="margin-bottom:1em;">Det går inte att ta bort konferensen <strong>' . htmlspecialchars($confName) . '</strong> eftersom det finns deltagare kopplade till den. Ta bort alla deltagare först.</div>';
    } else {
        $stmt = $pdo->prepare("DELETE FROM nv_conferences WHERE id = ?");
        $stmt->execute([$conf_id]);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Konferensen <strong>' . htmlspecialchars($confName) . '</strong> har tagits bort.</div>';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera ändring av synlighet ---
if (isset($_POST['set_public']) && isset($_POST['conf_id']) && isset($_POST['public_visible'])) {
    $stmt = $pdo->prepare("UPDATE nv_conferences SET public_visible = ? WHERE id = ?");
    $stmt->execute([$_POST['public_visible'], $_POST['conf_id']]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera val av sökbara fält för publik närvaroregistrering ---
if (isset($_POST['save_public_search_fields']) && isset($_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    $fields = isset($_POST['public_search_fields']) ? $_POST['public_search_fields'] : [];
    $fields_str = implode(',', $fields);
    $stmt = $pdo->prepare("UPDATE nv_conferences SET public_search_fields = ? WHERE id = ?");
    $stmt->execute([$fields_str, $conf_id]);
    $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Sökbara fält för publik registrering uppdaterade!</div>';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Hantera val om "bord" ska skickas med i mejl ---
if (isset($_POST['save_bord_mail_option']) && isset($_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    $send_bord = isset($_POST['send_bord_in_mail']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE nv_conferences SET send_bord_in_mail = ? WHERE id = ?");
    $stmt->execute([$send_bord, $conf_id]);
    // Visa tydligt i feedback om bordsplacering skickas eller ej
    if ($send_bord) {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Inställning uppdaterad: <strong>Bordsplacering kommer att skickas med i bekräftelsemejl.</strong></div>';
    } else {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success">Inställning uppdaterad: <strong>Bordsplacering kommer <u>inte</u> att skickas med i bekräftelsemejl.</strong></div>';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- AJAX-endpoint för att hämta deltagarlistan för en konferens ---
if (
    isset($_GET['ajax_participants']) &&
    isset($_GET['conf_id']) &&
    is_numeric($_GET['conf_id'])
) {
    $conf_id = (int)$_GET['conf_id'];
    $search = trim($_GET['search'] ?? '');
    $params = [$conf_id];
    $where = '';
    if ($search !== '') {
        $where = "AND (fornamn LIKE ? OR efternamn LIKE ? OR hsaid LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql = "SELECT id, fornamn, efternamn, hsaid, email, present, self_registered, bord FROM nv_participants WHERE conference_id = ? $where ORDER BY fornamn, efternamn";
    $partStmt = $pdo->prepare($sql);
    $partStmt->execute($params);
    $participants = $partStmt->fetchAll();
    $searchValue = htmlspecialchars($search, ENT_QUOTES);
    ?>
    <!-- Dynamiskt sökfält överst -->
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" oninput="searchParticipants_<?php echo $conf_id; ?>()" value="<?php echo $searchValue; ?>">
    </div>
    <form method="POST" id="delete_participants_form_<?php echo $conf_id; ?>" onsubmit="return confirm('Är du säker på att du vill ta bort markerade deltagare?');">
        <input type="hidden" name="delete_participants" value="1">
        <input type="hidden" name="conf_id" value="<?php echo $conf_id; ?>">
        <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white">
                <thead class="table-light"><tr>
                    <th style="width:2em;"><input type="checkbox" id="checkall_<?php echo $conf_id; ?>" onclick="toggleAllCheckboxes_<?php echo $conf_id; ?>()"></th>
                    <th>Förnamn</th><th>Efternamn</th><th>HSA-ID</th><th>E-post</th><th>Närvaro</th><th>Självregistrerad</th><th>Bord</th>
                </tr></thead>
                <tbody>
                <?php foreach ($participants as $p):
                    $hasBord = trim($p['bord']) !== '';
                    $inputId = 'bord_input_' . $p['id'];
                    $btnId = 'edit_bord_btn_' . $p['id'];
                    $isPresent = (int)$p['present'] === 1;
                ?>
                    <tr>
                        <td><input type="checkbox" name="participant_ids[]" value="<?php echo $p['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($p['fornamn']); ?></td>
                        <td><?php echo htmlspecialchars($p['efternamn']); ?></td>
                        <td><?php echo htmlspecialchars($p['hsaid']); ?></td>
                        <td><?php echo htmlspecialchars($p['email']); ?></td>
                        <td><?php echo $isPresent ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nej</span>'; ?></td>
                        <td><?php echo $p['self_registered'] ? 'Ja' : 'Nej'; ?></td>
                        <td style="min-width:120px;">
                            <div class="input-group input-group-sm">
                                <input type="text" name="bord_ids[<?php echo $p['id']; ?>]" id="<?php echo $inputId; ?>" value="<?php echo htmlspecialchars($p['bord']); ?>" class="form-control" style="min-width:60px;max-width:120px;" <?php echo $hasBord ? 'readonly style="background:#eee;cursor:not-allowed;"' : ''; ?>>
                                <button type="button" class="btn btn-outline-secondary" id="<?php echo $btnId; ?>" onclick="unlockBordField('<?php echo $inputId; ?>', '<?php echo $btnId; ?>')" <?php echo !$hasBord ? 'style="display:none;"' : ''; ?> title="Redigera"><span class="bi bi-pencil"></span> &#9998;</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button type="submit" class="btn btn-danger btn-sm" name="delete_participants" value="1" onclick="return confirm('Är du säker på att du vill ta bort markerade deltagare?');">Ta bort markerade</button>
                <button type="button" class="btn btn-outline-secondary btn-sm mx-2" onclick="refreshParticipants_<?php echo $conf_id; ?>()">Uppdatera lista</button>
                <button type="submit" class="btn btn-primary btn-sm" name="update_bord" value="1" style="margin-left:auto;" onclick="this.form.onsubmit=null;">Spara bordsplaceringar</button>
            </div>
        </div>
    </form>
    <script>
    let searchTimeout_<?php echo $conf_id; ?> = null;
    function searchParticipants_<?php echo $conf_id; ?>() {
        clearTimeout(searchTimeout_<?php echo $conf_id; ?>);
        searchTimeout_<?php echo $conf_id; ?> = setTimeout(function() {
            refreshParticipants_<?php echo $conf_id; ?>();
        }, 200);
    }
    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                // Spara nuvarande sökvärde
                var currentValue = search.value;
                container.innerHTML = xhr.responseText;
                // Återställ sökvärdet efter AJAX-refresh
                var newSearch = document.getElementById("search_input_<?php echo $conf_id; ?>");
                if (newSearch) {
                    newSearch.value = currentValue;
                    newSearch.focus();
                    newSearch.setSelectionRange(currentValue.length, currentValue.length);
                }
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>(){var c=document.getElementById("checkall_<?php echo $conf_id; ?>");var boxes=document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");for(var i=0;i<boxes.length;i++){boxes[i].checked=c.checked;}}
    </script>
    <?php
    exit;
}

// --- Hantera uppdatering av bord på deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_bord']) &&
    isset($_POST['bord_ids']) &&
    is_array($_POST['bord_ids'])
) {
    foreach ($_POST['bord_ids'] as $pid => $bord) {
        $bordValue = (trim($bord) === '') ? null : trim($bord);
        $stmt = $pdo->prepare("UPDATE nv_participants SET bord = ? WHERE id = ?");
        $stmt->execute([$bordValue, (int)$pid]);
    }
    $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Bordsplaceringar uppdaterade.</div>';
    // Håll deltagarlistan öppen efter redirect
    $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
    header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
    exit;
}

// --- Hantera borttagning av deltagare ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_participants'], $_POST['conf_id']) &&
    !empty($_POST['participant_ids']) &&
    is_array($_POST['participant_ids'])
) {
    $ids = array_map('intval', $_POST['participant_ids']);
    if (count($ids) > 0) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM nv_participants WHERE id IN ($in)");
        $stmt->execute($ids);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">' . count($ids) . ' deltagare borttagna.</div>';
        // Håll deltagarlistan öppen efter redirect
        $openId = isset($_POST['conf_id']) ? (int)$_POST['conf_id'] : 0;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?open_participants=' . $openId);
        exit;
    }
}

// --- Hantera borttagning av konferens ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conference'], $_POST['conf_id'])) {
    $conf_id = (int)$_POST['conf_id'];
    // Hämta konferensnamn för meddelande
    $stmt = $pdo->prepare("SELECT name FROM nv_conferences WHERE id = ?");
    $stmt->execute([$conf_id]);
    $confName = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
    $stmt->execute([$conf_id]);
    $participantCount = $stmt->fetchColumn();
    if ($participantCount > 0) {
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-danger" style="margin-bottom:1em;">Det går inte att ta bort konferensen <strong>' . htmlspecialchars($confName) . '</strong> eftersom det finns deltagare kopplade till den. Ta bort alla deltagare först.</div>';
    } else {
        $stmt = $pdo->prepare("DELETE FROM nv_conferences WHERE id = ?");
        $stmt->execute([$conf_id]);
        $_SESSION['deleteConferenceWarning'] = '<div class="alert alert-success" style="margin-bottom:1em;">Konferensen <strong>' . htmlspecialchars($confName) . '</strong> har tagits bort.</div>';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Visa feedbackmeddelande om det finns
if (isset($_SESSION['deleteConferenceWarning'])) {
    $deleteConferenceWarning = $_SESSION['deleteConferenceWarning'];
    unset($_SESSION['deleteConferenceWarning']);
} else {
    // Visa namn på aktuell konferens om det finns i POST (t.ex. efter ändring av synlighet eller sökbara fält)
    if (isset($_POST['conf_id'])) {
        $conf_id = (int)$_POST['conf_id'];
        $stmt = $pdo->prepare("SELECT name FROM nv_conferences WHERE id = ?");
        $stmt->execute([$conf_id]);
        $confName = $stmt->fetchColumn();
        if ($confName) {
            $deleteConferenceWarning = '<div class="alert alert-info" style="margin-bottom:1em;">Aktuell konferens: <strong>' . htmlspecialchars($confName) . '</strong></div>';
        }
    }
}

include 'toppen.php';
?>
<head>
    <title>Hantera konferenser | Konferenssystem</title>
</head>

<div class="container" style="max-width: 1300px; margin: 0 auto;">
    <div class="admin-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Hantera konferenser</h2>
    </div>

    <!-- Importera deltagare från CSV är borttaget här -->

    <div class="admin-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <!--<h3>Hantera konferenser</h3>-->
        <?php
        // Hantera ändring av synlighet
        if (isset($_POST['set_public']) && isset($_POST['conf_id']) && isset($_POST['public_visible'])) {
            $stmt = $pdo->prepare("UPDATE nv_conferences SET public_visible = ? WHERE id = ?");
            $stmt->execute([$_POST['public_visible'], $_POST['conf_id']]);
            // Uppdatera sidan för att visa ändringen direkt
            echo '<meta http-equiv="refresh" content="0">';
            exit;
        }

        // Hantera val av sökbara fält för publik närvaroregistrering
        if (isset($_POST['save_public_search_fields']) && isset($_POST['conf_id'])) {
            $conf_id = (int)$_POST['conf_id'];
            $fields = isset($_POST['public_search_fields']) ? $_POST['public_search_fields'] : [];
            $fields_str = implode(',', $fields);
            $stmt = $pdo->prepare("UPDATE nv_conferences SET public_search_fields = ? WHERE id = ?");
            $stmt->execute([$fields_str, $conf_id]);
            echo '<div class="alert alert-success">Sökbara fält för publik registrering uppdaterade!</div>';
        }

        // Hantera sortering
        $sortable = ['name', 'date', 'public_visible'];
        $sort = isset($_GET['sort']) && in_array($_GET['sort'], $sortable) ? $_GET['sort'] : 'date';
        // Default till ASC för datum, annars DESC
        if (!isset($_GET['order'])) {
            $order = ($sort === 'date') ? 'ASC' : 'DESC';
        } else {
            $order = strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
        }

        // Visa QR-kod och nedladdningslänk för varje konferens
        $stmt = $pdo->query("SELECT id, name, date, public_visible, public_search_fields, send_bord_in_mail FROM nv_conferences ORDER BY $sort $order");
        //echo '<h3>Hantera konferenser</h3>';
        if (!empty($deleteConferenceWarning)) echo $deleteConferenceWarning;

        // Gör varje konferens till en helrad (col-12) för full bredd
        echo '<div class="row row-cols-1 g-4">';
        while ($row = $stmt->fetch()) {
            $activeBg = $row['public_visible'] ? 'background: #e6f9ea;' : 'background: #ffeaea;';
            $confId = (int)$row['id'];
            echo '<div class="col">';
            echo '<div class="card shadow-sm mb-4" style="border-radius:14px;">';
            echo '<div class="card-body">';
            echo '<div class="d-flex justify-content-between align-items-center mb-2">';
            echo '<div>';
            echo '<h5 class="card-title mb-0" style="color:#1a237e;">' . htmlspecialchars($row['name']) . '</h5>';
            echo '<div class="text-muted" style="font-size:0.95em;">' . htmlspecialchars($row['date']) . '</div>';
            echo '</div>';
            // Synlighetskontroll
            echo '<form method="POST" class="ms-2" style="display:inline;' . $activeBg . '">';
            echo '<input type="hidden" name="conf_id" value="' . $row['id'] . '">';
            echo '<input type="hidden" name="set_public" value="1">';
            echo '<select name="public_visible" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto; display:inline-block;' . $activeBg . '">';
            echo '<option value="1"' . ($row['public_visible'] ? ' selected' : '') . '>Aktiv</option>';
            echo '<option value="0"' . (!$row['public_visible'] ? ' selected' : '') . '>Inaktiv</option>';
            echo '</select>';
            echo '</form>';
            // Ta bort konferens-knapp
            echo '<form method="POST" class="ms-2" style="display:inline;' . $activeBg . '" onsubmit="return confirm(\'Är du säker på att du vill ta bort hela konferensen och ALLA dess deltagare? Detta går inte att ångra!\');">';
            echo '<input type="hidden" name="delete_conference" value="1">';
            echo '<input type="hidden" name="conf_id" value="' . $row['id'] . '">';
            echo '<button type="submit" class="btn btn-danger btn-sm ms-1">Ta bort</button>';
            echo '</form>';
            echo '</div>'; // d-flex

            echo '<hr class="my-2">';

            // Flytta statistikräkning hit, innan den används!
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ?");
            $countStmt->execute([$row['id']]);
            $participantCount = $countStmt->fetchColumn();
            $presentStmt = $pdo->prepare("SELECT COUNT(*) FROM nv_participants WHERE conference_id = ? AND present = 1");
            $presentStmt->execute([$row['id']]);
            $presentCount = $presentStmt->fetchColumn();

            echo '<div class="row g-4 align-items-stretch mb-3">';
            // QR och publik registrering, Statistik och Exportera CSV
            echo '<div class="row g-4 align-items-stretch mb-3">';
            // QR och publik registrering
            echo '<div class="col-md-4 col-12">';
            echo '<div class="p-3 h-100 border rounded bg-light">';
            if ($row['public_visible']) {
                $qr_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/public_attendance.php?conf_id=" . $row['id'];
                $qr_img_url = "generate_qr.php?conf_id=" . $row['id'];
                echo '<div class="mb-2 text-center">';
                echo '<a href="' . htmlspecialchars($qr_url) . '" target="_blank" class="btn btn-outline-primary btn-sm mb-2 w-100">Publik registrering</a><br>';
                echo '<img src="' . htmlspecialchars($qr_img_url) . '" alt="QR-kod" style="width:70px;height:70px;display:block;margin:0 auto 0.5em auto;box-shadow:0 1px 4px #0002;" onerror="this.style.display=\'none\';">';
                echo '<a href="' . htmlspecialchars($qr_img_url) . '&download=1" download="qr_konferens_' . $row['id'] . '.jpg" class="btn btn-outline-primary btn-sm w-100">Ladda ner QR</a>';
                echo '</div>';
            } else {
                echo '<div class="text-muted text-center">Publik registrering: Inaktiv</div>';
            }
            echo '</div>';
            echo '</div>';
            // Statistik
            echo '<div class="col-md-4 col-12">';
            echo '<div class="p-3 h-100 border rounded bg-light">';
            // Lägg till id-attribut för JavaScript-uppdatering
            echo '<div class="mb-1 fs-5 text-center"><span class="fw-bold" id="participantCount_' . $confId . '">' . $participantCount . '</span> deltagare</div>';
            echo '<div class="mb-2 fs-6 text-center"><span class="fw-bold" id="presentCount_' . $confId . '">' . $presentCount . '</span> har registrerat närvaro</div>';
            echo '</div>';
            echo '</div>';
            // Exportera CSV
            echo '<div class="col-md-4 col-12">';
            echo '<div class="p-3 h-100 border rounded bg-light d-flex flex-column justify-content-center align-items-center">';
            echo '<form method="POST" class="w-100">';
            echo '<input type="hidden" name="export_participants" value="1">';
            echo '<input type="hidden" name="export_conf_id" value="' . $row['id'] . '">';
            echo '<input type="hidden" name="export_fields[]" value="fornamn">';
            echo '<input type="hidden" name="export_fields[]" value="efternamn">';
            echo '<input type="hidden" name="export_fields[]" value="hsaid">';
            echo '<input type="hidden" name="export_fields[]" value="email">';
            echo '<input type="hidden" name="export_fields[]" value="present">';
            echo '<input type="hidden" name="export_fields[]" value="self_registered">';
            echo '<button type="submit" class="btn btn-outline-success btn-sm w-100">Exportera CSV</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>'; // row

            // --- Deltagartabell med checkboxar för borttagning, nu kollapsbar ---
            $partStmt = $pdo->prepare("SELECT id, fornamn, efternamn, hsaid, email, present, self_registered, bord FROM nv_participants WHERE conference_id = ? ORDER BY fornamn, efternamn");
            $partStmt->execute([$row['id']]);
            $participants = $partStmt->fetchAll();
            $collapseId = 'participants_' . $row['id'];
            $openCollapse = false;
            if (
                (isset($_GET['open_participants']) && $_GET['open_participants'] == $row['id'])
            ) {
                $openCollapse = true;
            }
            if (count($participants) > 0) {
                echo '<div class="mt-3">';
                // Lägg till open/close logik
                echo '<button type="button" class="btn btn-outline-secondary btn-sm mb-2" onclick="toggleCollapse(\'' . $collapseId . '\', this)">' . ($openCollapse ? 'Dölj deltagare' : 'Visa deltagare') . '</button>';
                echo '<div id="' . $collapseId . '" style="display:' . ($openCollapse ? 'block' : 'none') . ';">';
                // Sökfält
                echo '<div class="mb-2">';
                echo '<input type="text" class="form-control form-control-sm" id="search_input_' . $row['id'] . '" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" oninput="searchParticipants_' . $row['id'] . '()">';
                echo '</div>';
                // Deltagarlistan (AJAX laddar om denna div)
                echo '<div id="participants_' . $row['id'] . '">';
                echo '<form method="POST" id="delete_participants_form_' . $row['id'] . '" onsubmit="return confirm(\'Är du säker på att du vill ta bort markerade deltagare?\');">';
                echo '<input type="hidden" name="delete_participants" value="1">';
                echo '<input type="hidden" name="conf_id" value="' . $row['id'] . '">';
                // Formulär för uppdatering av bordsplaceringar (läggs inuti samma formulär)
                echo '<div class="table-responsive">';
                echo '<table class="table table-sm table-bordered bg-white">';
                echo '<thead class="table-light"><tr>';
                echo '<th style="width:2em;"><input type="checkbox" id="checkall_' . $row['id'] . '" onclick="toggleAllCheckboxes_' . $row['id'] . '()"></th>';
                echo '<th>Förnamn</th><th>Efternamn</th><th>HSA-ID</th><th>E-post</th><th>Närvaro</th><th>Självregistrerad</th><th>Bord</th>';
                echo '</tr></thead><tbody>';
                foreach ($participants as $p) {
                    $hasBord = trim($p['bord']) !== '';
                    $inputId = 'bord_input_' . $p['id'];
                    $btnId = 'edit_bord_btn_' . $p['id'];
                    $presentId = 'present_input_' . $p['id'];
                    $presentBtnId = 'present_btn_' . $p['id'];
                    $isPresent = (int)$p['present'] === 1;
                    echo '<tr>';
                    echo '<td><input type="checkbox" name="participant_ids[]" value="' . $p['id'] . '"></td>';
                    echo '<td>' . htmlspecialchars($p['fornamn']) . '</td>';
                    echo '<td>' . htmlspecialchars($p['efternamn']) . '</td>';
                    echo '<td>' . htmlspecialchars($p['hsaid']) . '</td>';
                    echo '<td>' . htmlspecialchars($p['email']) . '</td>';
                    // Närvaro: visa endast Ja/Nej, ej checkbox eller knapp
                    echo '<td>';
                    if ($isPresent) {
                        echo '<span class="badge bg-success">Ja</span>';
                    } else {
                        echo '<span class="badge bg-secondary">Nej</span>';
                    }
                    echo '</td>';
                    echo '<td>' . ($p['self_registered'] ? 'Ja' : 'Nej') . '</td>';
                    // Bord-fält: låst och grå om det finns värde, annars redigerbar
                    echo '<td style="min-width:120px;">';
                    echo '<div class="input-group input-group-sm">';
                    echo '<input type="text" name="bord_ids[' . $p['id'] . ']" id="' . $inputId . '" value="' . htmlspecialchars($p['bord']) . '" class="form-control" style="min-width:60px;max-width:120px;" ' . ($hasBord ? 'readonly style="background:#eee;cursor:not-allowed;"' : '') . '>';
                    echo '<button type="button" class="btn btn-outline-secondary" id="' . $btnId . '" onclick="unlockBordField(\'' . $inputId . '\', \'' . $btnId . '\')" ' . (!$hasBord ? 'style="display:none;"' : '') . ' title="Redigera"><span class="bi bi-pencil"></span> &#9998;</button>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                // Lägg knapparna i en rad under tabellen, vänster/höger + uppdatera-knapp i mitten
                echo '<div class="d-flex justify-content-between align-items-center mt-2">';
                // Ta bort-knapp längst till vänster
                echo '<button type="submit" class="btn btn-danger btn-sm" name="delete_participants" value="1" onclick="return confirm(\'Är du säker på att du vill ta bort markerade deltagare?\');">Ta bort markerade</button>';
                // Uppdatera deltagarlista-knapp i mitten
                echo '<button type="button" class="btn btn-outline-secondary btn-sm mx-2" onclick="refreshParticipants_' . $row['id'] . '()">Uppdatera lista</button>';
                // Spara bordsplaceringar-knapp längst till höger
                echo '<button type="submit" class="btn btn-primary btn-sm" name="update_bord" value="1" style="margin-left:auto;" onclick="this.form.onsubmit=null;">Spara bordsplaceringar</button>';
                echo '</div>';
                echo '</div>'; // table-responsive
                echo '</form>';
                // JS-funktion för att uppdatera deltagarlistan via AJAX
                ?>
                <script>
                function refreshParticipants_<?php echo $row['id']; ?>() {
                    var container = document.getElementById("participants_<?php echo $row['id']; ?>");
                    var search = document.getElementById("search_input_<?php echo $row['id']; ?>");
                    var searchVal = search ? encodeURIComponent(search.value) : '';
                    var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $row['id']; ?>&search=" + searchVal;
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", url, true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState == 4 && xhr.status == 200) {
                            container.innerHTML = xhr.responseText;
                        }
                    };
                    xhr.send();
                }
                </script>
                <?php
                echo '<script>function toggleAllCheckboxes_' . $row['id'] . '(){var c=document.getElementById("checkall_' . $row['id'] . '");var boxes=document.querySelectorAll("#' . $collapseId . ' input[name=\\"participant_ids[]\\"]");for(var i=0;i<boxes.length;i++){boxes[i].checked=c.checked;}}</script>';
                echo '</div>'; // end collapse
                echo '</div>'; // mt-3
            }

            // Formulär för val av sökbara fält för publik registrering
            echo '<div class="d-flex flex-wrap align-items-end mt-3 gap-3">';
            // Formulär för val av sökbara fält för publik registrering
            echo '<form method="POST" class="mb-0">';
            echo '<input type="hidden" name="save_public_search_fields" value="1">';
            echo '<div style="font-weight:bold; color:#333; margin-bottom:0.5em;">Sökbara fält för publik registrering:</div>';
            echo '<input type="hidden" name="conf_id" value="' . $row['id'] . '">';
            $currentFields = isset($row['public_search_fields']) && $row['public_search_fields'] !== '' ? explode(',', $row['public_search_fields']) : ['fornamn','efternamn','hsaid','email'];
            $searchFields = [
                'fornamn' => 'Förnamn',
                'efternamn' => 'Efternamn',
                'hsaid' => 'HSA-ID',
                'email' => 'E-post'
            ];
            echo '<div class="mb-2">';
            foreach ($searchFields as $key => $label) {
                $checked = in_array($key, $currentFields) ? 'checked' : '';
                echo '<div class="form-check form-check-inline" style="margin-right:0.5rem;">';
                echo '<input class="form-check-input" type="checkbox" name="public_search_fields[]" value="' . $key . '" ' . $checked . '>';
                echo '<label class="form-check-label" style="font-weight:normal;">' . $label . '</label>';
                echo '</div>';
            }
            echo '</div>';
            echo '<button type="submit" class="btn btn-primary btn-sm">Spara sökbara fält</button>';
            echo '</form>';

            // Formulär för val om "bord" ska skickas med i mejl (till höger)
            echo '<form method="POST" class="mb-0 ms-auto">';
            echo '<input type="hidden" name="save_bord_mail_option" value="1">';
            echo '<input type="hidden" name="conf_id" value="' . $row['id'] . '">';
            $sendBordChecked = !empty($row['send_bord_in_mail']) ? 'checked' : '';
            echo '<div class="form-check mb-2">';
            echo '<input class="form-check-input" type="checkbox" name="send_bord_in_mail" id="send_bord_in_mail_' . $row['id'] . '" value="1" ' . $sendBordChecked . '>';
            echo '<label class="form-check-label" for="send_bord_in_mail_' . $row['id'] . '">Skicka bordsplacering i bekräftelsemejl</label>';
            echo '</div>';
            echo '<button type="submit" class="btn btn-secondary btn-sm">Spara inställning</button>';
            echo '</form>';
            echo '</div>';
            // --- SLUT PÅ INNEHÅLL FÖR EN KONFERENS ---

            echo '</div>'; // card-body
            echo '</div>'; // card
            echo '</div>'; // col
        }
        echo '</div>'; // row row-cols-1 g-4
        ?>
    </div>

    <div class="text-center" style="margin-bottom:2rem;">
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Tillbaka till startsidan</a>
    </div>
</div>

<script>
function toggleCollapse(collapseId, btn) {
    var content = document.getElementById(collapseId);
    if (content.style.display === "none" || content.style.display === "") {
        content.style.display = "block";
        btn.innerHTML = "Dölj deltagare";
    } else {
        content.style.display = "none";
        btn.innerHTML = "Visa deltagare";
    }
}

function unlockBordField(inputId, btnId) {
    var input = document.getElementById(inputId);
    var btn = document.getElementById(btnId);
    input.removeAttribute("readonly");
    input.style.background = "#fff";
    input.focus();
    btn.style.display = "none";
}
</script>

<?php
// --- Automatisk uppdatering av statistik för varje konferens (utan att ladda om sidan) ---
$confIds = [];
$stmt = $pdo->query("SELECT id FROM nv_conferences");
while ($row = $stmt->fetch()) {
    $confIds[] = $row['id'];
}
?>
<script>
var confIds = <?php echo json_encode($confIds); ?>;
function updateStats() {
    for (var i = 0; i < confIds.length; i++) {
        (function(confId) {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "?ajax_stats=1&conf_id=" + confId, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);
                    document.getElementById("participantCount_" + confId).innerText = response.participantCount;
                    document.getElementById("presentCount_" + confId).innerText = response.presentCount;
                }
            };
            xhr.send();
        })(confIds[i]);
    }
}
// Uppdatera statistik var 10:e sekund
setInterval(updateStats, 10000);
</script>
