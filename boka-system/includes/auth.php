<?php
// Enkel kontroll för inloggning och 2FA (pseudo)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Kontrollera status och roll vid varje sidladdning
// ...lägg till kontroll för 2FA enligt företagets inställning...
