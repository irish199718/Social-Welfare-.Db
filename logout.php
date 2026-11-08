<?php
/**
 * logout.php
 * Destroys the session and returns to the login screen.
 */
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();