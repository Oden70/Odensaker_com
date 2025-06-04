<?php
require_once 'includes/db.php';
require_once 'includes/language.php';
session_start();

// Omdirigera till auth/login.php om användaren inte är inloggad
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

// Fallback om $lang inte är en array
if (!is_array($lang)) {
    $lang = [
        'email' => 'E-post',
        'password' => 'Lösenord',
        'submit' => 'Logga in'
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE email = ?");
    $stmt->execute([$_POST["email"]]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST["password"], $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["lang"] = $_POST["lang"] ?? 'sv';

        if ($user["twofa_enabled"]) {
            $code = rand(100000, 999999);
            $_SESSION["2fa_code"] = $code;
            $_SESSION["2fa_email"] = $user["email"];
            require_once 'includes/mail.php';
            send_2fa_code($user["email"], $code);
            header("Location: verify-2fa.php");
            exit;
        }

        header("Location: dashboard.php");
        exit;
    }
} else {
    header("Location: auth/login.php");
    exit;
}
?>
            <button type="submit"><?= $lang['submit'] ?></button>
        </form>
    </div>
</body>
</html>
<link rel="stylesheet" href="assets/css/main.css">
