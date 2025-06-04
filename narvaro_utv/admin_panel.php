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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    <div class="mb-2">
        <input type="text" class="form-control form-control-sm" id="search_input_<?php echo $conf_id; ?>" placeholder="Sök förnamn, efternamn eller HSA-ID..." autocomplete="off" value="<?php echo $searchValue; ?>">
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
    // Dynamisk sökfunktion - event bindas här för att alltid fungera efter AJAX-refresh
    (function() {
        let searchInput = document.getElementById("search_input_<?php echo $conf_id; ?>");
        let searchTimeout = null;
        if (searchInput) {
            searchInput.oninput = function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshParticipants_<?php echo $conf_id; ?>();
                }, 200);
            };
        }
    })();

    function refreshParticipants_<?php echo $conf_id; ?>() {
        var container = document.getElementById("participants_<?php echo $conf_id; ?>");
        var search = document.getElementById("search_input_<?php echo $conf_id; ?>");
        var searchVal = search ? encodeURIComponent(search.value) : '';
        var url = "admin_panel.php?ajax_participants=1&conf_id=<?php echo $conf_id; ?>&search=" + searchVal;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                container.innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    }
    function toggleAllCheckboxes_<?php echo $conf_id; ?>() {
        var c = document.getElementById("checkall_<?php echo $conf_id; ?>");
        var boxes = document.querySelectorAll("#participants_<?php echo $conf_id; ?> input[name='participant_ids[]']");
        for (var i = 0; i < boxes.length; i++) { boxes[i].checked = c.checked; }
    }
    </script>
    <?php
    exit;
}

// --- Hantera formulär för att skapa konto
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
    header('Location: ' .