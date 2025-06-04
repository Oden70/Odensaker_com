<?php
session_start();

require 'db.php';
$pageTitle = "Närvaroregistrering";

// --- All logik och eventuella redirects här ovanför ---

$conf_id = $_GET['conf_id'] ?? ($_POST['conf_id'] ?? null);
if (!$conf_id) {
    include 'toppen.php';
    ?>
    <head>
        <title>Närvaroregistrera | Konferenssystem</title>
    </head>
    <div class="container" style="max-width: 1100px; margin: 0 auto;">
        <div class="page-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
            <h2 style="margin:0;">Välj konferens</h2>
        </div>
        <div class="page-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
            <div class="row">
                <div class="col-md-6">
                    <form method="GET" class="mb-3">
                        <div class="mb-2">
                            <select name="conf_id" class="form-select" required>
                                <option value="" disabled selected>Välj konferens här</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM nv_conferences ORDER BY name");
                                while ($conf = $stmt->fetch()) {
                                    echo '<option value="' . htmlspecialchars($conf['id']) . '">' . htmlspecialchars($conf['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Välj</button>
                    </form>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div>
                        <p>Här väljer du vilken konferens du vill närvaroregistrera deltagare på. Du kan även lägga till nya deltagare manuellt om de inte redan finns.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include "botten.php";
    exit;
}

// Hämta konferensnamn och send_bord_in_mail
$stmt = $pdo->prepare("SELECT name, send_bord_in_mail FROM nv_conferences WHERE id = ?");
$stmt->execute([$conf_id]);
$conference = $stmt->fetch();

if (!$conference) {
    echo "Konferensen hittades inte.";
    exit;
}

// --- All POST/GET logik här (ingen HTML/output) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name']) && isset($_POST['email'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    // Sök deltagare
    $stmt = $pdo->prepare("SELECT id FROM nv_participants WHERE email = ? AND conference_id = ?");
    $stmt->execute([$email, $conf_id]);
    $participant = $stmt->fetch();

    if ($participant) {
        // Markera närvaro
        $stmt = $pdo->prepare("UPDATE nv_participants SET present = 1 WHERE id = ?");
        $stmt->execute([$participant['id']]);
        $message = "Närvaro registrerad för $name.";
    } else {
        // Lägg till ny deltagare (OBS! Korrigera till rätt kolumner)
        // Dela upp $name till förnamn och efternamn om möjligt
        $parts = preg_split('/\s+/', $name, 2);
        $fornamn = $parts[0] ?? '';
        $efternamn = $parts[1] ?? '';
        $stmt = $pdo->prepare("INSERT INTO nv_participants (fornamn, efternamn, email, conference_id, present) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$fornamn, $efternamn, $email, $conf_id]);
        $message = "Du har registrerats och din närvaro är noterad.";
    }
}

if (isset($_POST['register_self'])) {
    $fornamn = trim($_POST['fornamn']);
    $efternamn = trim($_POST['efternamn']);
    $hsaid = strtoupper(trim($_POST['hsaid']));
    $hsaid = substr(preg_replace('/[^A-Z0-9]/i', '', $hsaid), 0, 4);
    $email = trim($_POST['email']);
    $conf_id = $_POST['conf_id'];
    // Lägg till self_registered = 1, bord sätts till NULL
    $stmt = $pdo->prepare("INSERT INTO nv_participants (fornamn, efternamn, hsaid, email, conference_id, present, self_registered, bord) VALUES (?, ?, ?, ?, ?, 1, 1, NULL)");
    $stmt->execute([$fornamn, $efternamn, $hsaid, $email, $conf_id]);

    // Skicka bekräftelsemail
    $subject = "Bekräftelse på närvaroregistrering";
    $body = "Hej $fornamn $efternamn!\n\nDu har nu registrerats som närvarande på konferensen '" . htmlspecialchars($conference['name']) . "'.\n\nFör bordsplacering, kontakta konferensvärd på plats.\n\nMed vänlig hälsning\nKonferenssystemet";
    $headers = "From: no-reply@odensaker.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    if (function_exists('mb_encode_mimeheader')) {
        $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
    }
    @mail($email, $encoded_subject, $body, $headers);

    // Spara e-post i session för att kunna visa rätt bordsmeddelande efter redirect
    $_SESSION['last_registered_email'] = $email;

    // Visa rätt meddelande på sidan efter registrering
    if (!empty($conference['send_bord_in_mail'])) {
        $bordMsg = "För bordsplacering, kontakta konferensvärd på plats.";
    } else {
        $bordMsg = "";
    }
    // Spara endast grundmeddelandet, bordsmeddelandet hanteras vid redirect
    $_SESSION['attendance_message'] = "Du har lagts till och din närvaro är noterad.";
    header("Location: attendance.php?conf_id=" . urlencode($conf_id) . "&success=2");
    exit;
}

include 'toppen.php';
?>
<head>
    <title>Närvaroregistrera | Konferenssystem</title>
    <link href="style.css" rel="stylesheet">
</head>
<div class="container" style="max-width: 900px; margin: 0 auto;">
    <div class="page-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Närvaroregistrering</h2>
    </div>
    <div class="page-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <h3 class="mb-3">Närvaroregistrering – <?= htmlspecialchars($conference['name']) ?></h3>

        <?php if (isset($message)) echo "<div class='alert alert-success'>$message</div>"; ?>

        <div class="mb-4">
            <h4>Sök efter deltagare</h4>
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                <div class="col">
                    <input type="text" size="50" name="search" class="form-control" placeholder="HSA-ID, förnamn, efternamn eller e-post" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">Sök</button>
                </div>
            </form>
        </div>

        <?php
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $stmt = $pdo->prepare("SELECT id, fornamn, efternamn, hsaid, email, present FROM nv_participants WHERE conference_id = ? AND (fornamn LIKE ? OR efternamn LIKE ? OR hsaid LIKE ? OR email LIKE ?)");
            $stmt->execute([$conf_id, $search, $search, $search, $search]);
            $results = $stmt->fetchAll();

            if ($results) {
                echo "<div class='mt-3'>";
                echo "<h5>Resultat:</h5>";
                echo "<form method='POST'><ul class='list-group'>";
                foreach ($results as $row) {
                    $checked = $row['present'] ? 'checked disabled' : '';
                    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                    echo htmlspecialchars($row['fornamn']) . " " . htmlspecialchars($row['efternamn']) . " (HSA-ID: <strong>" . htmlspecialchars($row['hsaid']) . "</strong>) ";
                    echo "<span>";
                    echo "<input type='checkbox' name='present_ids[]' value='" . $row['id'] . "' $checked> Närvarande ";
                    echo ($row['present'] ? "<span style='color:green;'>[Redan registrerad]</span>" : "");
                    echo "</span>";
                    echo "</li>";
                }
                echo "</ul>";
                echo "<input type='hidden' name='conf_id' value='" . htmlspecialchars($conf_id) . "'>";
                echo "<button type='submit' name='mark_present' class='btn btn-success mt-3'>Spara närvaro</button>";
                echo "</form>";
                // Lägg till knapp för manuell registrering även om det finns resultat
                echo '<form method="POST" style="margin-top:1em;">';
                echo '<input type="hidden" name="conf_id" value="' . htmlspecialchars($conf_id) . '">';
                echo '<button type="submit" name="show_add_form" class="btn btn-outline-secondary">Hittar du inte dig själv? Klicka här för manuell registrering</button>';
                echo '</form>';
                echo "</div>";
            } else {
                // Visa knapp för att lägga till sig själv om ingen träff
                echo "<div class='alert alert-warning mt-3'>Ingen deltagare hittades.</div>";
                echo '<form method="POST">';
                echo '<input type="hidden" name="conf_id" value="' . htmlspecialchars($conf_id) . '">';
                echo '<button type="submit" name="show_add_form" class="btn btn-outline-secondary">Lägg till dig själv manuellt</button>';
                echo '</form>';
            }
        }

        // Visa formulär för att lägga till sig själv endast om användaren klickat på knappen
        if (isset($_POST['show_add_form'])) {
            ?>
            <div class="mt-4">
                <h5>Lägg till dig själv som deltagare</h5>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="conf_id" value="<?= htmlspecialchars($conf_id) ?>">
                    <div class="col-md-6">
                        <label>Förnamn:</label>
                        <input type="text" name="fornamn" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Efternamn:</label>
                        <input type="text" name="efternamn" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>HSA-ID:</label>
                        <input type="text" name="hsaid" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>E-post:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <!-- Ta bort fältet för bord här -->
                    <div class="col-12">
                        <button type="submit" name="register_self" class="btn btn-primary w-100">Lägg till och registrera närvaro</button>
                    </div>
                </form>
            </div>
            <?php
        }

        // Hantera registrering av ny deltagare via "lägg till dig själv"-formuläret
        if (isset($_POST['register_self'])) {
            $fornamn = trim($_POST['fornamn']);
            $efternamn = trim($_POST['efternamn']);
            $hsaid = strtoupper(trim($_POST['hsaid']));
            $hsaid = substr(preg_replace('/[^A-Z0-9]/i', '', $hsaid), 0, 4);
            $email = trim($_POST['email']);
            $conf_id = $_POST['conf_id'];
            // Lägg till self_registered = 1, bord sätts till NULL
            $stmt = $pdo->prepare("INSERT INTO nv_participants (fornamn, efternamn, hsaid, email, conference_id, present, self_registered, bord) VALUES (?, ?, ?, ?, ?, 1, 1, NULL)");
            $stmt->execute([$fornamn, $efternamn, $hsaid, $email, $conf_id]);

            // Skicka bekräftelsemail
            $subject = "Bekräftelse på närvaroregistrering";
            $body = "Hej $fornamn $efternamn!\n\nDu har nu registrerats som närvarande på konferensen '" . htmlspecialchars($conference['name']) . "'.\n\nFör bordsplacering, kontakta konferensvärd på plats.\n\nMed vänlig hälsning\nKonferenssystemet";
            $headers = "From: no-reply@odensaker.com\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            if (function_exists('mb_encode_mimeheader')) {
                $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
            }
            @mail($email, $encoded_subject, $body, $headers);

            // Spara e-post i session för att kunna visa rätt bordsmeddelande efter redirect
            $_SESSION['last_registered_email'] = $email;

            // Visa rätt meddelande på sidan efter registrering
            if (!empty($conference['send_bord_in_mail'])) {
                $bordMsg = "För bordsplacering, kontakta konferensvärd på plats.";
            } else {
                $bordMsg = "";
            }
            // Spara endast grundmeddelandet, bordsmeddelandet hanteras vid redirect
            $_SESSION['attendance_message'] = "Du har lagts till och din närvaro är noterad.";
            header("Location: attendance.php?conf_id=" . urlencode($conf_id) . "&success=2");
            exit;
        }

        // Hantera närvarouppdatering via checkboxar
        if (isset($_POST['mark_present']) && isset($_POST['present_ids'])) {
            $confirmationMessages = [];
            foreach ($_POST['present_ids'] as $pid) {
                $stmt = $pdo->prepare("UPDATE nv_participants SET present = 1 WHERE id = ?");
                $stmt->execute([$pid]);
                // Hämta deltagarens info för mejl och för meddelande på sidan
                $stmt2 = $pdo->prepare("SELECT fornamn, efternamn, email, bord, self_registered FROM nv_participants WHERE id = ?");
                $stmt2->execute([$pid]);
                $participant = $stmt2->fetch();
                if ($participant && !empty($participant['email'])) {
                    $subject = "Bekräftelse på närvaroregistrering";
                    $body = "Hej " . $participant['fornamn'] . " " . $participant['efternamn'] . "!\n\nDu har nu registrerats som närvarande på konferensen '" . htmlspecialchars($conference['name']) . "'.";
                    // Lägg till bord om konferensen har send_bord_in_mail och deltagaren har ett bord och INTE självregistrerad
                    $bordMsg = "";
                    if (!empty($conference['send_bord_in_mail'])) {
                        if (!$participant['self_registered'] && trim($participant['bord']) !== '') {
                            $body .= "\n\nDu sitter vid bord: " . $participant['bord'];
                            $bordMsg = "Du sitter vid bord: " . htmlspecialchars($participant['bord']);
                        } else {
                            $body .= "\n\nFör bordsplacering, kontakta konferensvärd på plats.";
                            $bordMsg = "För bordsplacering, kontakta konferensvärd på plats.";
                        }
                    }
                    // Flytta hälsningsfrasen sist
                    $body .= "\n\nMed vänlig hälsning\nKonferenssystemet";
                    $headers = "From: no-reply@odensaker.com\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
                    if (function_exists('mb_encode_mimeheader')) {
                        $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
                    }
                    @mail($participant['email'], $encoded_subject, $body, $headers);

                    // Lägg till meddelande för denna deltagare
                    $confirmationMessages[] = htmlspecialchars($participant['fornamn'] . " " . $participant['efternamn']) . ": Närvaro registrerad." . ($bordMsg ? "<br>" . htmlspecialchars($bordMsg) : "");
                }
            }
            // Visa bekräftelsemeddelande på sidan
            if (!empty($confirmationMessages)) {
                echo "<div class='alert alert-success mt-3'>" . implode("<hr>", $confirmationMessages) . "</div>";
            } else {
                echo "<div class='alert alert-success mt-3'>Närvaro har sparats och bekräftelsemail har skickats till deltagarna.</div>";
            }
        }

        // Visa bekräftelsemeddelande efter redirect
        if (isset($_GET['success'])) {
            if ($_GET['success'] == 1) {
                $message = "Närvaro registrerad!";
            }
            if ($_GET['success'] == 2) {
                // Hämta e-post från session (satt vid registrering)
                $lastEmail = $_SESSION['last_registered_email'] ?? null;
                $bordMsg = "";
                if (!empty($conference['send_bord_in_mail']) && $lastEmail) {
                    $participantStmt = $pdo->prepare("SELECT bord FROM nv_participants WHERE email = ? AND conference_id = ? ORDER BY id DESC LIMIT 1");
                    $participantStmt->execute([$lastEmail, $conf_id]);
                    $lastParticipant = $participantStmt->fetch();
                    if ($lastParticipant && !empty($lastParticipant['bord'])) {
                        $bordMsg = "Du sitter vid bord: " . htmlspecialchars($lastParticipant['bord']);
                    } else {
                        $bordMsg = "För bordsplacering, kontakta konferensvärd på plats.";
                    }
                }
                if (!empty($_SESSION['attendance_message'])) {
                    $message = $_SESSION['attendance_message'];
                    unset($_SESSION['attendance_message']);
                } else {
                    $message = "Du har lagts till och din närvaro är noterad.";
                }
                if ($bordMsg) {
                    $message .= "\n" . $bordMsg;
                }
                // Rensa e-post från session efter visning
                unset($_SESSION['last_registered_email']);
            }
        }
        ?>

        <?php if (isset($message) && $message): ?>
            <div class='alert alert-success'><?= nl2br(htmlspecialchars($message)) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php
include 'botten.php';
?>