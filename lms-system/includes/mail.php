<?php
function sendSystemEmail($to_email, $subject, $body) {
    $from = 'no-reply@odensaker.com';
    $headers  = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to_email, $subject, $body, $headers);
}

function sendPasswordResetEmail($email, $resetLink) {
    $subject = "Återställ ditt lösenord";
    $body = "Klicka på länken för att återställa ditt lösenord:\n$resetLink\n\nLänken är giltig i 1 timme.";
    return sendSystemEmail($email, $subject, $body);
}

function sendTwoFactorCode($email, $code) {
    $subject = "Din säkerhetskod";
    $body = "Din sexsiffriga kod är: $code";
    return sendSystemEmail($email, $subject, $body);
}

function sendWelcomeEmail($email, $name) {
    $subject = "Välkommen till Odensåker LSM";
    $body = "Hej $name,\n\nVälkommen till vår utbildningsplattform. Du kan logga in på https://dinsida.se\n\nVänliga hälsningar,\nOdensåker-teamet";
    return sendSystemEmail($email, $subject, $body);
}

if (!function_exists('sendPasswordResetEmail')) {
    function sendPasswordResetEmail($email, $resetLink) {
        $subject = "Återställ ditt lösenord";
        $body = "Klicka på följande länk för att återställa ditt lösenord:\n$resetLink\n\nLänken är giltig i 1 timme.";
        return sendSystemEmail($email, $subject, $body);
    }
}

function sendCourseAssignedEmail($email, $courseName, $startDate) {
    $subject = "Du har blivit tilldelad en ny kurs";
    $body = "Du har tilldelats kursen \"$courseName\" som startar den $startDate.\nLogga in på portalen för att komma igång.";
    return sendSystemEmail($email, $subject, $body);
}
