<?php
require_once __DIR__ . '/../../includes/auth.php';

logoutUser();
redirect('/modules/auth/login.php');
?>
