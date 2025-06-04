<?php
$lang_code = $_SESSION['lang'] ?? 'sv';
$lang_file = __DIR__ . '/../languages/' . $lang_code . '.php';
if (file_exists($lang_file)) {
    require $lang_file;
} else {
    require __DIR__ . '/../languages/sv.php';
}
function t($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

// Lägg till detta om språkfilen inte laddas korrekt:
if (!isset($lang) || !is_array($lang)) {
    $lang_code = $_SESSION['lang'] ?? 'sv';
    $lang_file = dirname(__DIR__) . '/languages/' . $lang_code . '.php';
    if (file_exists($lang_file)) {
        require $lang_file;
    } else {
        require dirname(__DIR__) . '/languages/sv.php';
    }
}
