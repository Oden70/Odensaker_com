<?php require_once '../includes/lang.php'; ?>
<?php $token = $_GET['token'] ?? ''; ?>
<?php if (!$token) die("Ogiltig token."); ?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= t('reset_title') ?></title>
</head>
<body>
  <h2><?= t('reset_title') ?></h2>
  <form action="process_reset.php" method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <label for="password"><?= t('new_password_label') ?></label><br>
    <input type="password" name="password" required><br><br>
    <button type="submit"><?= t('reset_password_button') ?></button>
  </form>
</body>
</html>
