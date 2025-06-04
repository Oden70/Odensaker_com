<?php
// Rensa all kod och HTML före header()-anropen!
session_start();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
?>
