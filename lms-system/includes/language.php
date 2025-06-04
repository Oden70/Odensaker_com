<?php
session_start();
$lang = $_SESSION['lang'] ?? 'sv';
$lang_file = __DIR__ . "/../languages/{$lang}.php";
$lang_strings = file_exists($lang_file) ? include $lang_file : include __DIR__ . '/../languages/sv.php';

function t($key) {
    global $lang_strings;
    return $lang_strings[$key] ?? $key;
}

// Lägg till dessa nycklar i $lang-arrayen om de saknas
if (!isset($lang) || !is_array($lang)) $lang = [];
$lang += [
    'dashboard' => 'Dashboard',
    'courses'   => 'Kurser',
    'users'     => 'Användare',
    'settings'  => 'Inställningar',
    'logout' => 'Logga ut',
    'title' => 'Titel',
    'short_description' => 'Kort beskrivning',
    'upload_image' => 'Ladda upp bild',
    'course_info' => 'Kursinformation',
    'show_in_catalog' => 'Visa i kurskatalog',
    'date_from' => 'Från datum',
    'date_to' => 'Till datum',
    'keywords' => 'Nyckelord',
    'course_admin' => 'Kursadministratör',
    'course_responsible' => 'Kursansvarig',
    'certificate_text' => 'Text till kursintyg',
    'certificate_responsible' => 'Kursansvarig för kursintyg'
];
?>