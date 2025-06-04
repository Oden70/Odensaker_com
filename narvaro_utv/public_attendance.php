<?php
// public_attendance.php
require 'db.php';
session_start(); // Lägg till denna rad högst upp om den saknas

// Lägg till: Funktion för att logga mejl
function logMail($pdo, $to, $subject, $body) {
    $stmt = $pdo->prepare("INSERT INTO nv_maillog (to_email, subject, body, sent_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$to, $subject, $body]);
}

// Hämta konferens-id och kontrollera synlighet
$conf_id = $_GET['conf_id'] ?? null;
if (!$conf_id) {
    echo '<p>Ingen konferens angiven.</p>';
    exit;
}
$stmt = $pdo->prepare("SELECT name, date, public_visible, public_search_fields, send_bord_in_mail FROM nv_conferences WHERE id = ?");
$stmt->execute([$conf_id]);
$conference = $stmt->fetch();
if (!$conference) {
    echo '<p>Konferensen hittades inte.</p>';
    exit;
}
if (empty($conference['public_visible']) || $conference['public_visible'] != 1) {
    echo '<p>Denna sida är inte aktiv för deltagarregistrering.</p>';
    exit;
}

// --- Hämta sökbara fält för denna konferens ---
$search_fields = isset($conference['public_search_fields']) && $conference['public_search_fields'] ? explode(',', $conference['public_search_fields']) : ['fornamn','efternamn','hsaid','email'];

// Hantera närvaroregistrering för befintlig deltagare
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_present'], $_POST['participant_id'])) {
    $participant_id = (int)$_POST['participant_id'];
    $stmt = $pdo->prepare("UPDATE nv_participants SET present = 1 WHERE id = ? AND conference_id = ?");
    $stmt->execute([$participant_id, $conf_id]);

    // Hämta deltagarens e-post och bord för att skicka mejl
    $stmt = $pdo->prepare("SELECT email, fornamn, efternamn, bord, self_registered FROM nv_participants WHERE id = ? AND conference_id = ?");
    $stmt->execute([$participant_id, $conf_id]);
    $participant = $stmt->fetch();
    if ($participant && !empty($participant['email'])) {
        $to = $participant['email'];
        $subject = "Närvaro registrerad för " . $conference['name'];
        $body = "Hej " . $participant['fornamn'] . " " . $participant['efternamn'] . ",\n\nDin närvaro har registrerats för konferensen \"" . $conference['name'] . "\".";
        // Lägg till bord om konferensen har send_bord_in_mail och deltagaren har ett bord
        if (!empty($conference['send_bord_in_mail'])) {
            if (trim($participant['bord']) !== '') {
                $body .= "\n\nDin bordsplacering: " . $participant['bord'];
            } else {
                $body .= "\n\nFör bordsplacering, \nkontakta konferensvärd på plats.";
            }
        }
        $body .= "\n\nVänliga hälsningar,\nKonferensarrangören";
        $headers = "From: no-reply@odensaker.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        if (function_exists('mb_encode_mimeheader')) {
            $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
        }
        @mail($to, $encoded_subject, $body, $headers);
        logMail($pdo, $to, $subject, $body);
    }

    $message = "Närvaro registrerad!";
    // Ladda om sidan för att visa uppdaterad status
    header("Location: public_attendance.php?conf_id=" . urlencode($conf_id) . "&success=1");
    exit;
}

// Hantera nyregistrering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_self'])) {
    $fornamn = trim($_POST['fornamn']);
    $efternamn = trim($_POST['efternamn']);
    $hsaid = strtoupper(trim($_POST['hsaid']));
    $hsaid = substr(preg_replace('/[^A-Z0-9]/i', '', $hsaid), 0, 4);
    $email = trim($_POST['email']);
    // Lägg till self_registered = 1, bord sätts till NULL
    $stmt = $pdo->prepare("INSERT INTO nv_participants (fornamn, efternamn, hsaid, email, conference_id, present, self_registered, bord) VALUES (?, ?, ?, ?, ?, 1, 1, NULL)");
    $stmt->execute([$fornamn, $efternamn, $hsaid, $email, $conf_id]);

    // Skicka mejl till ny deltagare
    if (!empty($email)) {
        $subject = "Närvaro registrerad för " . $conference['name'];
        $body = "Hej " . $fornamn . " " . $efternamn . ",\n\nDu har lagts till och din närvaro är registrerad för konferensen \"" . $conference['name'] . "\".";
        // Om konferensen har send_bord_in_mail, informera om bordsplacering vid självregistrering
        if (!empty($conference['send_bord_in_mail'])) {
            $body .= "\n\nFör bordsplacering, \nkontakta konferensvärd på plats.";
        }
        $body .= "\n\nVänliga hälsningar,\nKonferensarrangören";
        $headers = "From: no-reply@odensaker.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        if (function_exists('mb_encode_mimeheader')) {
            $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
        }
        @mail($email, $encoded_subject, $body, $headers);
        // Logga mejlet
        logMail($pdo, $email, $subject, $body);
    }

    // Visa rätt meddelande på sidan efter registrering
    if (!empty($conference['send_bord_in_mail'])) {
        // Kontrollera om deltagaren har fått ett bord (alltid NULL vid självregistrering)
        $stmt = $pdo->prepare("SELECT bord FROM nv_participants WHERE email = ? AND conference_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $conf_id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['bord'])) {
            $bordMsg = "Din bordsplacering: " . htmlspecialchars($row['bord']) . "";
        } else {
            $bordMsg = "\n\nFör bordsplacering, \nkontakta konferensvärd på plats.";
        }
    } else {
        $bordMsg = "";
    }
    // Spara meddelande i session för redirect
    $_SESSION['public_attendance_message'] = "Du har lagts till och din närvaro är noterad." . ($bordMsg ? " " . $bordMsg : "");
    header("Location: public_attendance.php?conf_id=" . urlencode($conf_id) . "&success=2");
    exit;
}

// Hantera sökning
$search_active = false;
$search_wheres = [];
$search_params = [$conf_id];
foreach ($search_fields as $field) {
    if (!empty($_GET['search_' . $field])) {
        $search_active = true;
        $search_wheres[] = "$field LIKE ?";
        $search_params[] = '%' . $_GET['search_' . $field] . '%';
    }
}
$results = [];
if ($search_active) {
    $sql = "SELECT fornamn, efternamn, hsaid, email, present, id FROM nv_participants WHERE conference_id = ? AND (" . implode(" OR ", $search_wheres) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $results = $stmt->fetchAll();
}

// Visa ev. bekräftelsemeddelande
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        // Närvaro för befintlig deltagare
        // Hämta bordsplacering om inställt i admin
        $stmt = $pdo->prepare("SELECT send_bord_in_mail FROM nv_conferences WHERE id = ?");
        $stmt->execute([$conf_id]);
        $send_bord_in_mail = $stmt->fetchColumn();

        $bordMsg = "";
        if ($send_bord_in_mail) {
            // Hämta senaste deltagare med present = 1 för denna konferens och denna e-post
            // För att få rätt e-post, hämta deltagarens e-post från participant_id i GET eller POST om möjligt
            $participant_email = null;
            if (isset($_POST['participant_id'])) {
                $stmt = $pdo->prepare("SELECT email FROM nv_participants WHERE id = ?");
                $stmt->execute([$_POST['participant_id']]);
                $participant_email = $stmt->fetchColumn();
            } elseif (isset($_GET['participant_id'])) {
                $stmt = $pdo->prepare("SELECT email FROM nv_participants WHERE id = ?");
                $stmt->execute([$_GET['participant_id']]);
                $participant_email = $stmt->fetchColumn();
            }
            // Om vi inte har e-post, ta senaste närvarande deltagare för konferensen
            if (!$participant_email) {
                $stmt = $pdo->prepare("SELECT email FROM nv_participants WHERE conference_id = ? AND present = 1 ORDER BY id DESC LIMIT 1");
                $stmt->execute([$conf_id]);
                $participant_email = $stmt->fetchColumn();
            }
            if ($participant_email) {
                $stmt = $pdo->prepare("SELECT bord FROM nv_participants WHERE conference_id = ? AND email = ? AND present = 1 ORDER BY id DESC LIMIT 1");
                $stmt->execute([$conf_id, $participant_email]);
                $row = $stmt->fetch();
                if ($row && !empty($row['bord'])) {
                    $bordMsg = " \n\nDin bordsplacering: " . htmlspecialchars($row['bord']) . "";
                } else {
                    $bordMsg = " \n\nFör bordsplacering, \nkontakta konferensvärd på plats.";
                }
            }
        }
        $message = "Närvaro registrerad!" . $bordMsg;
    }
    if ($_GET['success'] == 2) {
        // Hämta meddelande från session om det finns
        if (!empty($_SESSION['public_attendance_message'])) {
            $message = $_SESSION['public_attendance_message'];
            unset($_SESSION['public_attendance_message']);
        } else {
            $message = "Du har lagts till och din närvaro är noterad.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Närvaroregistrering – <?= htmlspecialchars($conference['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="page-header">
        <h2 style="margin:0;">Publik närvaroregistrering</h2>
    </div>
    <div class="page-section">
        <h2 class="mb-3" style="font-size:1.3em;">Närvaroregistrering<br><small><?= htmlspecialchars($conference['name']) ?></small></h2>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= nl2br(htmlspecialchars($message)) ?></div>
        <?php else: ?>
            <form class="search-form mb-3" method="GET">
                <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                <?php
                // Visa sökfält enligt admininställning
                foreach ($search_fields as $field) {
                    $label = [
                        'fornamn' => 'Förnamn',
                        'efternamn' => 'Efternamn',
                        'hsaid' => 'HSA-ID',
                        'email' => 'E-post'
                    ][$field] ?? $field;
                    echo '<input type="text" name="search_' . $field . '" placeholder="Sök på ' . $label . '" class="form-control mb-2" value="' . htmlspecialchars($_GET['search_' . $field] ?? '') . '">';
                }
                ?>
                <button type="submit" class="btn btn-primary w-100">Sök</button>
            </form>
        <?php endif; ?>
        <?php if (!$message && $search_active): ?>
            <?php if ($results): ?>
                <ul class="result-list">
                <?php foreach ($results as $row): ?>
                    <li>
                        <strong><?= htmlspecialchars($row['fornamn']) ?> <?= htmlspecialchars($row['efternamn']) ?></strong><br>
                        HSA-ID: <strong><?= htmlspecialchars($row['hsaid']) ?></strong><br>
                        E-post: <?= htmlspecialchars($row['email']) ?><br>
                        <?= $row['present'] ? '<span class="text-success">Närvaro registrerad</span><br>' : '<span class="text-warning">Ej registrerad</span><br>' ?>
                        <?php if (!$row['present']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mark_present" value="1">
                                <input type="hidden" name="participant_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                                <button type="submit" class="btn btn-success btn-sm mt-2">Registrera närvaro</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
                <form method="GET">
                    <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                    <button type="submit" class="btn btn-secondary w-100 mt-2">Sök igen</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Ingen deltagare hittades. Lägg till dig själv nedan:</div>
                <form method="POST">
                    <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                    <label>Förnamn:</label>
                    <input type="text" name="fornamn" class="form-control" required>
                    <label>Efternamn:</label>
                    <input type="text" name="efternamn" class="form-control" required>
                    <label>HSA-ID:</label>
                    <input type="text" name="hsaid" class="form-control" required maxlength="4">
                    <label>E-post:</label>
                    <input type="email" name="email" class="form-control" required>
                    <button type="submit" name="register_self" class="btn btn-success w-100 mt-2">Lägg till och registrera närvaro</button>
                </form>
                <form method="GET">
                    <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                    <button type="submit" class="btn btn-secondary w-100 mt-2">Sök igen</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>