<?php
/**
 * EpicLens System - Printable Transaction Audit Voucher
 */
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid transaction ID.');
}

try {
    $stmt = $pdo->prepare('
        SELECT 
            t.*,
            e.item_code, e.model AS equipment_model, e.category, e.serial_number,
            e.owner_name, e.owner_phone
        FROM transactions t
        JOIN equipment e ON t.equipment_id = e.id
        WHERE t.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $tx = $stmt->fetch();

    if (!$tx) {
        die('Transaction record not found.');
    }
} catch (PDOException $e) {
    die('Database error loading voucher.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Voucher #<?= (int)$tx['id'] ?> | EpicLens System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f1f5f9; padding: 2rem 1rem; }
        .voucher-card {
            background: #fff;
            max-width: 720px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 2.5rem;
            box-shadow: var(--shadow-md);
        }
        .voucher-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .voucher-title h1 { font-size: 1.5rem; margin: 0; color: var(--text-main); }
        .voucher-title p { margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .info-block { background: var(--bg-alt); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); }
        .info-block h4 { margin: 0 0 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .info-block p { margin: 0.25rem 0; font-size: 0.875rem; color: var(--text-main); }
        .signature-area { margin-top: 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; text-align: center; }
        .sig-line { border-top: 1px dashed #94a3b8; padding-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted); }
        @media print {
            body { background: #fff; padding: 0; }
            .voucher-card { box-shadow: none; border: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="voucher-card">
        <div class="voucher-header">
            <div class="voucher-title">
                <h1>EpicLens Lending Voucher</h1>
                <p>Campus Event Equipment Handover & Return Audit Record</p>
            </div>
            <div style="text-align: right;">
                <div style="font-family: monospace; font-size: 1.25rem; font-weight: 800; color: var(--primary);">TX-<?= sprintf('%05d', $tx['id']) ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= formatDateTime($tx['created_at']) ?></div>
            </div>
        </div>

        <div class="grid-2">
            <div class="info-block">
                <h4>Equipment Specifications</h4>
                <p><strong>Item Code:</strong> <span class="item-code-badge"><?= e($tx['item_code']) ?></span></p>
                <p><strong>Model:</strong> <?= e($tx['equipment_model']) ?></p>
                <p><strong>Category:</strong> <?= e($tx['category']) ?></p>
                <p><strong>Serial Number:</strong> <?= !empty($tx['serial_number']) ? e($tx['serial_number']) : '—' ?></p>
            </div>

            <div class="info-block">
                <h4>Student Owner</h4>
                <p><strong>Name:</strong> <?= e($tx['owner_name']) ?></p>
                <p><strong>Contact:</strong> <?= e($tx['owner_phone']) ?></p>
                <p><strong>Current Status:</strong> <?= renderStatusBadge($tx['status']) ?></p>
            </div>
        </div>

        <div class="grid-2">
            <div class="info-block">
                <h4>Stage 1 & 2: Check-in & Crew Usage</h4>
                <p><strong>Received At:</strong> <?= formatDateTime($tx['received_at']) ?></p>
                <p><strong>Received By (Staff):</strong> <?= e($tx['received_by_name']) ?></p>
                <p><strong>Condition on Arrival:</strong> <?= e($tx['received_condition']) ?></p>
                <p><strong>Assigned Crew Member:</strong> <?= !empty($tx['equipment_user_name']) ? e($tx['equipment_user_name']) . ' (' . e($tx['equipment_user_phone']) . ')' : 'None Assigned' ?></p>
            </div>

            <div class="info-block">
                <h4>Stage 3 & 4: Returns & Discharge</h4>
                <p><strong>Crew Return At:</strong> <?= !empty($tx['user_returned_at']) ? formatDateTime($tx['user_returned_at']) : '—' ?></p>
                <p><strong>Final Return to Owner:</strong> <?= !empty($tx['returned_at']) ? formatDateTime($tx['returned_at']) : 'Pending' ?></p>
                <p><strong>Discharged By (Staff):</strong> <?= !empty($tx['returned_by_name']) ? e($tx['returned_by_name']) : '—' ?></p>
                <p><strong>Final Condition:</strong> <?= !empty($tx['returned_condition']) ? e($tx['returned_condition']) : '—' ?></p>
            </div>
        </div>

        <?php if (!empty($tx['notes'])): ?>
            <div class="info-block" style="margin-bottom: 2rem;">
                <h4>Notes & Audit Log</h4>
                <p><?= nl2br(e($tx['notes'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="signature-area">
            <div>
                <div class="sig-line">Staff Signature (Event Operations)</div>
            </div>
            <div>
                <div class="sig-line">Student Owner / Receiver Signature</div>
            </div>
        </div>

        <div class="no-print" style="margin-top: 2.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print Voucher</span>
            </button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</body>
</html>
