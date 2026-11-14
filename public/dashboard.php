<?php
/**
 * EpicLens System - Adaptive Dashboard (Admin & Staff)
 *
 * Displays the 4 official status metrics:
 * Total Equipment = Not Received Yet + Received + Returned
 */
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = getDB();
$currentUser = current_user();
$isAdmin = is_admin();
$userId = (int)$currentUser['id'];

$totalEquipment = 0;
$receivedCount = 0;
$returnedCount = 0;
$notReceivedCount = 0;

try {
    $totalEquipment = (int)$pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    $receivedCount = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'Received'")->fetchColumn();
    $returnedCount = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'Returned'")->fetchColumn();
    $notReceivedCount = max(0, $totalEquipment - ($receivedCount + $returnedCount));

    if ($isAdmin) {
        // Admin Overview: Equipment handover status
        $overviewStmt = $pdo->query("
            SELECT 
                e.id, e.item_code, e.model, e.category, e.owner_name, e.owner_phone, e.status,
                t.received_at, t.received_by_name, t.equipment_user_name, t.equipment_user_phone, t.user_returned_at
            FROM equipment e
            LEFT JOIN transactions t ON (e.id = t.equipment_id AND t.status = 'Received')
            ORDER BY 
                CASE 
                    WHEN e.status = 'Received' THEN 1 
                    WHEN e.status = 'Returned' THEN 2 
                    ELSE 0 
                END,
                e.id ASC
            LIMIT 12
        ");
        $equipmentOverview = $overviewStmt->fetchAll();

        // Admin Activity Feed
        $recentStmt = $pdo->query("
            SELECT 
                t.id, t.received_at, t.received_by_name, t.equipment_user_name,
                t.returned_at, t.returned_by_name, t.status,
                e.item_code, e.model AS equipment_model, e.owner_name
            FROM transactions t
            JOIN equipment e ON t.equipment_id = e.id
            ORDER BY COALESCE(t.returned_at, t.received_at) DESC, t.id DESC
            LIMIT 10
        ");
        $recentActivity = $recentStmt->fetchAll();
    } else {
        // Staff: Currently Received Equipment
        $staffStmt = $pdo->query("
            SELECT 
                t.id AS transaction_id, e.id AS equipment_id, e.item_code, e.model AS equipment_model,
                e.category, e.owner_name, e.owner_phone,
                t.received_by_name, t.received_at, t.equipment_user_name, t.equipment_user_phone, t.user_returned_at
            FROM transactions t
            JOIN equipment e ON t.equipment_id = e.id
            WHERE e.status = 'Received' AND t.status = 'Received'
            ORDER BY t.received_at DESC
        ");
        $currentlyReceived = $staffStmt->fetchAll();

        // Staff: My Activity
        $myStmt = $pdo->prepare("
            SELECT 
                t.id, e.item_code, e.model AS equipment_model, e.owner_name,
                t.received_at, t.received_by_user_id, t.returned_at, t.returned_by_user_id, t.status
            FROM transactions t
            JOIN equipment e ON t.equipment_id = e.id
            WHERE t.received_by_user_id = ? OR t.returned_by_user_id = ?
            ORDER BY COALESCE(t.returned_at, t.received_at) DESC, t.id DESC
            LIMIT 8
        ");
        $myStmt->execute([$userId, $userId]);
        $myRecentActivity = $myStmt->fetchAll();
    }
} catch (PDOException $e) {
    setFlash('error', 'Error querying dashboard metrics.');
    $equipmentOverview = [];
    $recentActivity = [];
    $currentlyReceived = [];
    $myRecentActivity = [];
}

$pageTitle = $isAdmin ? 'Admin Dashboard' : 'Staff Dashboard';
$currentPage = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card" style="border-left: 4px solid var(--primary); background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);">
    <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                Welcome, <?= e($currentUser['full_name']) ?>
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                Role: <strong style="color: var(--primary); text-transform: uppercase;"><?= e($currentUser['role']) ?></strong> &bull;
                Event Lending Operations Active
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="receive.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Receive Equipment</span>
            </a>
            <a href="return.php" class="btn btn-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                <span>Return & Lending</span>
            </a>
        </div>
    </div>
</div>

<!-- ==================== 4 STATUS COUNTERS ==================== -->
<div class="stat-grid">
    <div class="stat-card stat-total">
        <div class="stat-label">Total Equipment</div>
        <div class="stat-value"><?= $totalEquipment ?></div>
        <div class="stat-desc">Master equipment registered</div>
    </div>
    <div class="stat-card stat-not-received">
        <div class="stat-label">Not Received Yet</div>
        <div class="stat-value"><?= $notReceivedCount ?></div>
        <div class="stat-desc">Awaiting student arrival</div>
    </div>
    <div class="stat-card stat-received">
        <div class="stat-label">Received</div>
        <div class="stat-value"><?= $receivedCount ?></div>
        <div class="stat-desc">Currently with event team</div>
    </div>
    <div class="stat-card stat-returned">
        <div class="stat-label">Returned</div>
        <div class="stat-value"><?= $returnedCount ?></div>
        <div class="stat-desc">Returned to student owners</div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- Admin: Equipment Handover Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Equipment Handover Overview</h2>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Real-time tracking of owner arrivals and event status</span>
            </div>
            <a href="equipment.php" class="btn btn-secondary btn-sm">Full Equipment Master &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Code & Model</th>
                        <th>Owner</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Received At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipmentOverview)): ?>
                        <?php foreach ($equipmentOverview as $eq): ?>
                            <tr>
                                <td>
                                    <span class="item-code-badge"><?= e($eq['item_code']) ?></span>
                                    <strong style="margin-left: 0.35rem;"><?= e($eq['model']) ?></strong>
                                </td>
                                <td><strong><?= e($eq['owner_name']) ?></strong></td>
                                <td><a href="tel:<?= e($eq['owner_phone']) ?>"><?= e($eq['owner_phone']) ?></a></td>
                                <td><?= renderCategoryBadge($eq['category']) ?></td>
                                <td>
                                    <?= renderStatusBadge($eq['status']) ?>
                                    <?php if ($eq['status'] === 'Received'): ?>
                                        <?php if (empty($eq['equipment_user_name'])): ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">Crew: <em>Unassigned</em></div>
                                        <?php elseif (empty($eq['user_returned_at'])): ?>
                                            <div style="font-size: 0.7rem; color: var(--primary); font-weight: 600; margin-top: 2px;">Used by: <?= e($eq['equipment_user_name']) ?></div>
                                        <?php else: ?>
                                            <div style="font-size: 0.7rem; color: var(--success); font-weight: 600; margin-top: 2px;">✓ Crew returned</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $eq['status'] === 'Received' ? formatDateTime($eq['received_at'], 'H:i') : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="table-empty">No equipment registered in the master list. <a href="equipment_add.php">Add equipment here</a>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin: Recent Activity Feed -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Activity Feed</h2>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Latest receive and return transactions</span>
            </div>
            <a href="transactions.php" class="btn btn-secondary btn-sm">All Transactions &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Item</th>
                        <th>Owner</th>
                        <th>Event User</th>
                        <th>Action</th>
                        <th>Staff Member</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentActivity)): ?>
                        <?php foreach ($recentActivity as $act): ?>
                            <?php $isReturned = ($act['status'] === 'Returned'); ?>
                            <tr>
                                <td><strong><?= formatDateTime($isReturned ? $act['returned_at'] : $act['received_at'], 'H:i') ?></strong></td>
                                <td>
                                    <span class="item-code-badge"><?= e($act['item_code']) ?></span>
                                    <span style="margin-left: 0.25rem;"><?= e($act['equipment_model']) ?></span>
                                </td>
                                <td><?= e($act['owner_name']) ?></td>
                                <td><?= !empty($act['equipment_user_name']) ? e($act['equipment_user_name']) : '—' ?></td>
                                <td>
                                    <span style="font-weight: 600; color: <?= $isReturned ? 'var(--success)' : 'var(--primary)' ?>">
                                        <?= $isReturned ? 'Returned to Owner' : 'Received from Owner' ?>
                                    </span>
                                </td>
                                <td><?= e($isReturned ? $act['returned_by_name'] : $act['received_by_name']) ?></td>
                                <td><?= renderStatusBadge($act['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="table-empty">No activity recorded yet for this event session.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Staff: Currently Received Items -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Currently Received Equipment (<?= count($currentlyReceived) ?>)</h2>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Items currently in the event team custody</span>
            </div>
            <a href="return.php" class="btn btn-success btn-sm">Process Operations &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Owner</th>
                        <th>Event User</th>
                        <th>Received By</th>
                        <th>Received At</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($currentlyReceived)): ?>
                        <?php foreach ($currentlyReceived as $row): ?>
                            <tr>
                                <td>
                                    <span class="item-code-badge"><?= e($row['item_code']) ?></span>
                                    <strong style="margin-left: 0.35rem;"><?= e($row['equipment_model']) ?></strong>
                                </td>
                                <td>
                                    <?= e($row['owner_name']) ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($row['owner_phone']) ?>"><?= e($row['owner_phone']) ?></a></div>
                                </td>
                                <td>
                                    <?php if (!empty($row['equipment_user_name'])): ?>
                                        <strong><?= e($row['equipment_user_name']) ?></strong>
                                        <?= !empty($row['user_returned_at']) ? '<div style="font-size: 0.7rem; color: var(--success);">✓ Returned to crew</div>' : '<div style="font-size: 0.7rem; color: var(--warning);">● In use</div>' ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['received_by_name']) ?></td>
                                <td><?= formatDateTime($row['received_at'], 'H:i') ?></td>
                                <td style="text-align: right;">
                                    <a href="return.php" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="table-empty">No equipment currently in "Received" state.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
