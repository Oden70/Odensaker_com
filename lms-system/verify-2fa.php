<?php
session_start();
require_once 'includes/language.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['code'] == $_SESSION['2fa_code']) {
        unset($_SESSION['2fa_code']);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Fel kod!";
    }
}
?>

<h2><?= $lang['2fa_title'] ?></h2>
<p><?= $lang['2fa_instruction'] ?></p>
<?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post">
    <input name="code" maxlength="6">
    <button type="submit"><?= $lang['submit'] ?></button>
</form>
