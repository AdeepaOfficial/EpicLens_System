<?php
/**
 * EpicLens System - Equipment Master Records Management (Admin Only)
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

// Handle Reset All Statuses for a brand new event session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_all_event_status') {
    Csrf::requireValid();

    try {
        $pdo->exec("UPDATE equipment SET status = 'Not Received Yet'");
        setFlash('success', 'All equipment statuses have been reset to "Not Received Yet" for a new event session.');
        header('Location: equipment.php');
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Error resetting equipment status: ' . (APP_DEBUG ? $e->getMessage() : 'Database error.'));
    }
}

// Filters
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = '
    SELECT 
        e.*,
        t.id AS active_tx_id,
        t.received_by_name,
        t.received_at,
        t.equipment_user_name,
        t.user_returned_at
    FROM equipment e
    LEFT JOIN transactions t ON (e.id = t.equipment_id AND t.status = "Received")
    WHERE 1=1
';
$params = [];

if (!empty($categoryFilter)) {
    $sql .= ' AND e.category = ?';
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    $sql .= ' AND e.status = ?';
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= ' AND (e.item_code LIKE ? OR e.model LIKE ? OR e.owner_name LIKE ? OR e.owner_phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY e.category ASC, e.item_code ASC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipment = $stmt->fetchAll();
} catch (PDOException $e) {
    $equipment = [];
}

$categories = ['Camera', 'Tripod', 'Lens', 'Gimbal', 'Microphone', 'Flash', 'Light', 'Other'];

$pageTitle = 'Equipment Master';
$currentPage = 'equipment';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Equipment Master Catalog (<?= count($equipment) ?>)</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Manage student-owned equipment inventory, category prefixes, and event availability</span>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="equipment_add.php" class="btn btn-primary btn-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Add Equipment</span>
            </a>
            <button type="button" class="btn btn-danger btn-sm" data-modal-target="modal-reset-event">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                <span>Reset for New Event</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card-body" style="padding-bottom: 0;">
        <form method="GET" action="equipment.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem;">
            <input type="text" name="search" class="form-control" style="max-width: 260px;" placeholder="Search item code, model, owner..." value="<?= e($search) ?>" data-table-search="equipmentTable">

            <select name="category" class="form-control" style="max-width: 180px;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-control" style="max-width: 180px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Not Received Yet" <?= $statusFilter === 'Not Received Yet' ? 'selected' : '' ?>>Not Received Yet</option>
                <option value="Received" <?= $statusFilter === 'Received' ? 'selected' : '' ?>>Received</option>
                <option value="Returned" <?= $statusFilter === 'Returned' ? 'selected' : '' ?>>Returned</option>
            </select>

            <?php if (!empty($search) || !empty($categoryFilter) || !empty($statusFilter)): ?>
                <a href="equipment.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table" id="equipmentTable">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Model / Gear</th>
                    <th>Category</th>
                    <th>Owner (Student)</th>
                    <th>Serial #</th>
                    <th>Current Event Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($equipment)): ?>
                    <?php foreach ($equipment as $item): ?>
                        <tr>
                            <td><span class="item-code-badge"><?= e($item['item_code']) ?></span></td>
                            <td>
                                <strong><?= e($item['model']) ?></strong>
                                <?php if (!empty($item['notes'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($item['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= renderCategoryBadge($item['category']) ?></td>
                            <td>
                                <strong><?= e($item['owner_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($item['owner_phone']) ?>"><?= e($item['owner_phone']) ?></a></div>
                            </td>
                            <td><span style="font-family: monospace; font-size: 0.8rem;"><?= !empty($item['serial_number']) ? e($item['serial_number']) : '—' ?></span></td>
                            <td><?= renderStatusBadge($item['status']) ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="equipment_edit.php?id=<?= (int)$item['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <?php if ($item['status'] !== 'Received'): ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-modal-target="modal-del-<?= (int)$item['id'] ?>">Delete</button>

                                    <!-- Delete Modal -->
                                    <div id="modal-del-<?= (int)$item['id'] ?>" class="modal-backdrop">
                                        <div class="modal-card">
                                            <div class="modal-header">
                                                <h3 class="modal-title">Confirm Equipment Deletion</h3>
                                                <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
                                            </div>
                                            <form method="POST" action="equipment_delete.php">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <div class="modal-body" style="text-align: left;">
                                                    <p>Are you sure you want to delete <strong>[<?= e($item['item_code']) ?>] <?= e($item['model']) ?></strong> belonging to <strong><?= e($item['owner_name']) ?></strong>?</p>
                                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">The item code <strong><?= e($item['item_code']) ?></strong> will be retired and never reused.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete Item</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="table-empty">No equipment matches your filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Reset All Event Statuses -->
<div id="modal-reset-event" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger);">Reset All Equipment for New Event</h3>
            <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" action="equipment.php">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reset_all_event_status">
            <div class="modal-body">
                <p>This action resets all equipment statuses back to <strong>"Not Received Yet"</strong> to start a brand new event session.</p>
                <div class="alert alert-warning" style="margin-top: 1rem;">
                    <span>Past transaction audit logs remain safely recorded in the history table.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Event Reset</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
