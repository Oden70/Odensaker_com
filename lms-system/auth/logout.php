<?php
session_start();
session_unset();
session_destroy();
header("Location: logout_confirm.php");
exit;