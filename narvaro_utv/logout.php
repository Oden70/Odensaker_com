<?php
session_start();
session_unset();     // Tar bort alla sessionvariabler
session_destroy();   // Förstör sessionen

header("Location: login.php");
exit;
