<?php

function generateCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendCodeEmail($to, $code, $from = "no-reply@odensaker.com") {
    global $pdo;
    $subject = "Din inloggningskod";
    $message = "Din kod är: $code";
    $headers = "From: Konferenssystem <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Originating-IP: " . $_SERVER['SERVER_ADDR'] . "\r\n";
    // Skicka med rätt encoding för svenska tecken
    $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    // Använd mb_encode_mimeheader om tillgängligt
    if (function_exists('mb_encode_mimeheader')) {
        $encoded_subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n");
    }
    $mailResult = mail($to, $encoded_subject, $message, $headers);

    // Logga mejlet i nv_maillog
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO nv_maillog (to_email, subject, body, sent_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$to, $subject, $message]);
    }

    return $mailResult;
}
