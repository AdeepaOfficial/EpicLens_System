<?php
/**
 * EpicLens System - Permanent Transaction History & Audit Trail (Admin Only)
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'All');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$sql = '
    SELECT 
        t.id, t.equipment_id, t.received_by_name, t.received_at, t.received_condition,
        t.equipment_user_name, t.equipment_user_phone, t.event_user_assigned_at,
        t.user_returned_by_name, t.user_returned_at, t.user_returned_condition,
        t.returned_by_name, t.returned_at, t.returned_condition,
        t.status, t.notes,
        e.item_code, e.model AS equipment_model, e.category AS equipment_category,
        e.owner_name, e.owner_phone
    FROM transactions t
    JOIN equipment e ON t.equipment_id = e.id
    WHERE 1=1
';
$params = [];

if ($statusFilter === 'Received') {
    $sql .= ' AND t.status = "Received"';
} elseif ($statusFilter === 'Returned') {
    $sql .= ' AND t.status = "Returned"';
}

if (!empty($search)) {
    $sql .= ' AND (
        t.id = ? OR 
        e.item_code LIKE ? OR 
        e.model LIKE ? OR 
        e.owner_name LIKE ? OR 
        t.equipment_user_name LIKE ? OR 
        t.received_by_name LIKE ? OR 
        t.returned_by_name LIKE ?
    )';
    $like = '%' . $search . '%';
    $params[] = (int)$search;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($fromDate)) {
    $sql .= ' AND DATE(t.received_at) >= ?';
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $sql .= ' AND DATE(t.received_at) <= ?';
    $params[] = $toDate;
}

$sql .= ' ORDER BY t.id DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

$pageTitle = 'Transaction Audit History';
$currentPage = 'transactions';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Permanent Transaction History (<?= count($transactions) ?>)</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Audited log of all equipment lending transactions</span>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card-body" style="padding-bottom: 0;">
        <form method="GET" action="transactions.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem;">
            <input type="text" name="search" class="form-control" style="max-width: 250px;" placeholder="Search code, model, owner, crew..." value="<?= e($search) ?>" data-table-search="transactionsTable">

            <select name="status" class="form-control" style="max-width: 150px;" onchange="this.form.submit()">
                <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                <option value="Received" <?= $statusFilter === 'Received' ? 'selected' : '' ?>>Received</option>
                <option value="Returned" <?= $statusFilter === 'Returned' ? 'selected' : '' ?>>Returned</option>
            </select>

            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">From:</span>
                <input type="date" name="from_date" class="form-control" style="max-width: 140px;" value="<?= e($fromDate) ?>">
            </div>

            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">To:</span>
                <input type="date" name="to_date" class="form-control" style="max-width: 140px;" value="<?= e($toDate) ?>">
            </div>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || $statusFilter !== 'All' || !empty($fromDate) || !empty($toDate)): ?>
                <a href="transactions.php" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table" id="transactionsTable">
            <thead>
                <tr>
                    <th>Tx #</th>
                    <th>Equipment Item</th>
                    <th>Student Owner</th>
                    <th>Received Stage</th>
                    <th>Event Crew User</th>
                    <th>Returned Stage</th>
                    <th>Status</th>
                    <th style="text-align: right;">Voucher</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><code>#<?= (int)$tx['id'] ?></code></td>
                            <td>
                                <span class="item-code-badge"><?= e($tx['item_code']) ?></span>
                                <strong style="margin-left: 0.25rem;"><?= e($tx['equipment_model']) ?></strong>
                            </td>
                            <td>
                                <strong><?= e($tx['owner_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($tx['owner_phone']) ?>"><?= e($tx['owner_phone']) ?></a></div>
                            </td>
                            <td>
                                <div><?= formatDateTime($tx['received_at']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">By: <?= e($tx['received_by_name']) ?> &bull; Condition: <?= e($tx['received_condition']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($tx['equipment_user_name'])): ?>
                                    <strong><?= e($tx['equipment_user_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($tx['equipment_user_phone']) ?>"><?= e($tx['equipment_user_phone']) ?></a></div>
                                    <?php if (!empty($tx['user_returned_at'])): ?>
                                        <div style="font-size: 0.7rem; color: var(--success);">✓ Handed in (<?= formatDateTime($tx['user_returned_at'], 'H:i') ?>)</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tx['status'] === 'Returned'): ?>
                                    <div><?= formatDateTime($tx['returned_at']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">By: <?= e($tx['returned_by_name']) ?> &bull; Condition: <?= e($tx['returned_condition'] ?? 'Good') ?></div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">— Pending Final Return</span>
                                <?php endif; ?>
                            </td>
                            <td><?= renderStatusBadge($tx['status']) ?></td>
                            <td style="text-align: right;">
                                <a href="transaction_view.php?id=<?= (int)$tx['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                    <span>Voucher</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="table-empty">No transaction history matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
