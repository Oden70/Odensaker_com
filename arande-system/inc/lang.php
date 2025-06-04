<?php
// Laddar språkfil baserat på session eller default
function lang($key) {
    static $dict = null;
    if ($dict === null) {
        $lang = $_SESSION['lang'] ?? 'sv';
        $dict = require __DIR__ . "/../lang/$lang.php";
    }
    return $dict[$key] ?? $key;
}
