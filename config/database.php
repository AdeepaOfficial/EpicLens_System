<?php
/**
 * EpicLens System - Core Database & Environment Configuration
 *
 * Implements PDO connection pooling, environment loader, secure error handling,
 * and category-based unique Item Code sequence tracking.
 */

// Load local environment file (.env) if present
(function () {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
})();

// Set application timezone
$appTimezone = getenv('APP_TIMEZONE') ?: 'Asia/Colombo';
date_default_timezone_set($appTimezone);

// Database configuration defaults (Environment variables take precedence)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'epiclens_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));

/**
 * Returns a shared PDO instance configured with strict security and prepared statements.
 *
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set MySQL connection timezone to Sri Lanka / configured offset
            try {
                $pdo->exec("SET time_zone = '+05:30'");
            } catch (Exception $tzEx) {
                // Ignore if server lacks timezone permission; PHP handles display time
            }
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());

            if (APP_DEBUG) {
                $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            } else {
                $detail = 'Could not establish connection to the database. Verify environment settings or run setup.';
            }

            // Secure, user-friendly error response without credential leakage
            http_response_code(503);
            die("
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Database Service Unavailable | EpicLens System</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                        .error-card { max-width: 540px; width: 100%; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
                        .error-icon { width: 48px; height: 48px; color: #ef4444; margin: 0 auto 16px; }
                        h1 { font-size: 20px; font-weight: 700; color: #f8fafc; margin: 0 0 8px; }
                        p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 20px; }
                        .error-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 12px; font-family: monospace; font-size: 12px; color: #fca5a5; word-break: break-word; text-align: left; }
                        .btn { display: inline-block; margin-top: 20px; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; }
                        .btn:hover { background: #1d4ed8; }
                    </style>
                </head>
                <body>
                    <div class='error-card'>
                        <svg class='error-icon' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                            <circle cx='12' cy='12' r='10'></circle>
                            <line x1='12' y1='8' x2='12' y2='12'></line>
                            <line x1='12' y1='16' x2='12.01' y2='16'></line>
                        </svg>
                        <h1>Database Connection Unavailable</h1>
                        <p>{$detail}</p>
                        <div class='error-box'>Target Host: " . htmlspecialchars(DB_HOST . ':' . DB_PORT, ENT_QUOTES, 'UTF-8') . " | Database: " . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . "</div>
                        <a href='setup.php' class='btn'>Run System Setup</a>
                    </div>
                </body>
                </html>
            ");
        }
    }

    return $pdo;
}

/**
 * Returns the standardized 2-letter uppercase prefix for an equipment category.
 *
 * @param string $category
 * @return string
 */
function getCategoryPrefix(string $category): string {
    $prefixes = [
        'Camera'     => 'CM',
        'Tripod'     => 'TP',
        'Lens'       => 'LN',
        'Gimbal'     => 'GM',
        'Microphone' => 'MC',
        'Flash'      => 'FL',
        'Light'      => 'LT',
        'Other'      => 'OT'
    ];
    return $prefixes[$category] ?? 'OT';
}

/**
 * Atomically calculates or reserves the next permanent unique Item Code for a category.
 * Deleted codes are never reused to safeguard audit history.
 *
 * @param PDO $pdo
 * @param string $category
 * @param bool $reserve If true, updates and commits the sequence counter atomically.
 * @return string (e.g., 'CM001', 'TP004')
 */
function getNextItemCode(PDO $pdo, string $category, bool $reserve = false): string {
    $prefix = getCategoryPrefix($category);

    // Retrieve last number recorded in sequences table
    $seqStmt = $pdo->prepare('SELECT last_number FROM item_code_sequences WHERE prefix = ?');
    $seqStmt->execute([$prefix]);
    $seqRow = $seqStmt->fetch();
    $lastNum = $seqRow ? (int)$seqRow['last_number'] : 0;

    // Check maximum numeric suffix currently in equipment table
    $maxStmt = $pdo->prepare('
        SELECT MAX(CAST(SUBSTRING(item_code, LENGTH(?) + 1) AS UNSIGNED)) AS max_num
        FROM equipment
        WHERE item_code LIKE CONCAT(?, "%")
    ');
    $maxStmt->execute([$prefix, $prefix]);
    $maxRow = $maxStmt->fetch();
    $maxExisting = ($maxRow && $maxRow['max_num'] !== null) ? (int)$maxRow['max_num'] : 0;

    $highest = max($lastNum, $maxExisting);
    $nextNum = $highest + 1;

    if ($reserve) {
        $upsert = $pdo->prepare('
            INSERT INTO item_code_sequences (prefix, last_number)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))
        ');
        $upsert->execute([$prefix, $nextNum]);
    }

    return sprintf('%s%03d', $prefix, $nextNum);
}

/**
 * Returns projected next Item Codes across all supported categories.
 *
 * @param PDO $pdo
 * @return array<string, string>
 */
function getAllNextItemCodes(PDO $pdo): array {
    $categories = ['Camera', 'Tripod', 'Lens', 'Gimbal', 'Microphone', 'Flash', 'Light', 'Other'];
    $codes = [];
    foreach ($categories as $cat) {
        $codes[$cat] = getNextItemCode($pdo, $cat, false);
    }
    return $codes;
}
