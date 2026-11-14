<?php
/**
 * EpicLens System - Secure Logout Handler
 */
require_once __DIR__ . '/../includes/auth.php';

logout_user();
setFlash('info', 'You have been successfully signed out.');
header('Location: login.php');
exit;
