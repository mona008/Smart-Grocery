<?php
require_once 'includes/auth.php';
header('Location: ' . (is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
