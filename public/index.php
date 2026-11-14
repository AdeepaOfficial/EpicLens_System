<?php
/**
 * EpicLens System - Web Application Entry Point
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';

$appTitle = "EpicLens System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appTitle) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="container">
            <h1><?= htmlspecialchars($appTitle) ?></h1>
            <p class="tagline">Web Application Platform</p>
        </div>
    </header>

    <main class="container">
        <section class="card">
            <h2>Welcome to EpicLens System</h2>
            <p>System initialized successfully. Web development team active.</p>
            <div id="status-badge" class="badge">Status: Online</div>
        </section>
    </main>

    <footer class="app-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> EpicLens System. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
