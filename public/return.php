<?php
/**
 * EpicLens System - Return & Operational Lending Workflow
 *
 * Coordinates active equipment (Status = 'Received') through the 3 operational stages:
 * 1. ASSIGN USER (Assign crew member operating the equipment)
 * 2. USER RETURNED (Crew member hands equipment back to event crew)
 * 3. RETURN TO OWNER (Event team hands equipment back to permanent student owner)
 */
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = getDB();
$currentUser = current_user();

// -----------------------------------------------------------------------------
// POST ACTION 1: ASSIGN EVENT CREW USER
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_user') {
    Csrf::requireValid();

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $userName = trim($_POST['equipment_user_name'] ?? '');
    $userPhone = trim($_POST['equipment_user_phone'] ?? '');

    if ($transactionId <= 0 || empty($userName) || empty($userPhone)) {
        setFlash('error', 'Crew user name and contact number are required.');
    } else {
        try {
            $stmt = $pdo->prepare('
                UPDATE transactions 
                SET equipment_user_name = ?, equipment_user_phone = ?, event_user_assigned_at = NOW()
                WHERE id = ? AND status = "Received"
            ');
            $stmt->execute([$userName, $userPhone, $transactionId]);

            setFlash('success', sprintf('Crew member "%s" (%s) assigned to equipment.', $userName, $userPhone));
            header('Location: return.php');
            exit;
        } catch (PDOException $e) {
            setFlash('error', 'Database error assigning event user.');
        }
    }
}

// -----------------------------------------------------------------------------
// POST ACTION 2: EVENT CREW USER RETURNS TO TEAM
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'user_returned') {
    Csrf::requireValid();

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $condition = trim($_POST['user_returned_condition'] ?? 'Good');
    $notes = trim($_POST['user_return_notes'] ?? '');

    if ($transactionId <= 0) {
        setFlash('error', 'Invalid transaction specified.');
    } else {
        try {
            $stmt = $pdo->prepare('
                UPDATE transactions 
                SET 
                    user_returned_by_user_id = ?,
                    user_returned_by_name = ?,
                    user_returned_at = NOW(),
                    user_returned_condition = ?,
                    notes = CASE WHEN ? != "" THEN CONCAT(COALESCE(notes, ""), " | Return Note: ", ?) ELSE notes END
                WHERE id = ? AND status = "Received"
            ');
            $stmt->execute([
                (int)$currentUser['id'],
                $currentUser['full_name'],
                $condition,
                $notes,
                $notes,
                $transactionId
            ]);

            setFlash('success', 'Equipment marked as returned by crew member. Ready for final return to student owner.');
            header('Location: return.php');
            exit;
        } catch (PDOException $e) {
            setFlash('error', 'Database error recording crew return.');
        }
    }
}

// -----------------------------------------------------------------------------
// POST ACTION 3: FINAL RETURN TO STUDENT OWNER
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return_to_owner') {
    Csrf::requireValid();

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $finalCondition = trim($_POST['returned_condition'] ?? 'Good');
    $finalNotes = trim($_POST['final_notes'] ?? '');

    if ($transactionId <= 0) {
        setFlash('error', 'Invalid transaction specified.');
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch transaction and equipment details
            $stmt = $pdo->prepare('
                SELECT t.id, t.equipment_id, e.item_code, e.model, e.owner_name
                FROM transactions t
                JOIN equipment e ON t.equipment_id = e.id
                WHERE t.id = ? AND t.status = "Received"
                FOR UPDATE
            ');
            $stmt->execute([$transactionId]);
            $tx = $stmt->fetch();

            if (!$tx) {
                $pdo->rollBack();
                setFlash('error', 'Active transaction record not found.');
            } else {
                // Update transaction
                $updTx = $pdo->prepare('
                    UPDATE transactions 
                    SET 
                        returned_by_user_id = ?,
                        returned_by_name = ?,
                        returned_at = NOW(),
                        returned_condition = ?,
                        status = "Returned",
                        notes = CASE WHEN ? != "" THEN CONCAT(COALESCE(notes, ""), " | Final Return: ", ?) ELSE notes END
                    WHERE id = ?
                ');
                $updTx->execute([
                    (int)$currentUser['id'],
                    $currentUser['full_name'],
                    $finalCondition,
                    $finalNotes,
                    $finalNotes,
                    $transactionId
                ]);

                // Update equipment master status to 'Returned'
                $updEq = $pdo->prepare('UPDATE equipment SET status = "Returned" WHERE id = ?');
                $updEq->execute([$tx['equipment_id']]);

                $pdo->commit();

                setFlash('success', sprintf(
                    'Equipment [%s] "%s" successfully handed back to student owner (%s). Lending lifecycle completed.',
                    $tx['item_code'],
                    $tx['model'],
                    $tx['owner_name']
                ));
                header('Location: return.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Database error completing return.');
        }
    }
}

// Fetch all active items (status = Received)
try {
    $activeItems = $pdo->query('
        SELECT 
            t.id AS tx_id,
            t.equipment_id,
            t.received_by_name,
            t.received_at,
            t.received_condition,
            t.equipment_user_name,
            t.equipment_user_phone,
            t.event_user_assigned_at,
            t.user_returned_at,
            t.user_returned_by_name,
            t.user_returned_condition,
            e.item_code,
            e.model,
            e.category,
            e.owner_name,
            e.owner_phone
        FROM transactions t
        JOIN equipment e ON t.equipment_id = e.id
        WHERE e.status = "Received" AND t.status = "Received"
        ORDER BY t.received_at ASC
    ')->fetchAll();
} catch (PDOException $e) {
    $activeItems = [];
}

$pageTitle = 'Return & Lending Operations';
$currentPage = 'return';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Active Equipment in Custody (<?= count($activeItems) ?>)</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Manage event crew assignments, intermediate user returns, and final returns to student owners</span>
        </div>
        <div>
            <input type="text" class="form-control" style="width: 250px;" placeholder="Filter active equipment..." data-table-search="activeLendingTable">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="activeLendingTable">
            <thead>
                <tr>
                    <th>Item Code & Model</th>
                    <th>Student Owner</th>
                    <th>Received At</th>
                    <th>Event Crew User</th>
                    <th>Operational State</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($activeItems)): ?>
                    <?php foreach ($activeItems as $item): ?>
                        <tr>
                            <td>
                                <span class="item-code-badge"><?= e($item['item_code']) ?></span>
                                <strong style="margin-left: 0.35rem;"><?= e($item['model']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($item['category']) ?></div>
                            </td>
                            <td>
                                <strong><?= e($item['owner_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($item['owner_phone']) ?>"><?= e($item['owner_phone']) ?></a></div>
                            </td>
                            <td>
                                <div><?= formatDateTime($item['received_at'], 'H:i') ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">By: <?= e($item['received_by_name']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($item['equipment_user_name'])): ?>
                                    <strong><?= e($item['equipment_user_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><a href="tel:<?= e($item['equipment_user_phone']) ?>"><?= e($item['equipment_user_phone']) ?></a></div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($item['equipment_user_name'])): ?>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">Available for Crew</span>
                                <?php elseif (empty($item['user_returned_at'])): ?>
                                    <span class="badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">In Use by Crew</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">✓ Returned to Crew (Ready for Owner)</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if (empty($item['equipment_user_name'])): ?>
                                    <!-- Stage 2 Button -->
                                    <button type="button" class="btn btn-primary btn-sm" data-modal-target="modal-assign-<?= (int)$item['tx_id'] ?>">
                                        Assign Crew
                                    </button>
                                <?php elseif (empty($item['user_returned_at'])): ?>
                                    <!-- Stage 3 Button -->
                                    <button type="button" class="btn btn-warning btn-sm" style="background: var(--warning); color: #fff;" data-modal-target="modal-user-return-<?= (int)$item['tx_id'] ?>">
                                        Crew Return
                                    </button>
                                <?php else: ?>
                                    <!-- Stage 4 Button -->
                                    <button type="button" class="btn btn-success btn-sm" data-modal-target="modal-owner-return-<?= (int)$item['tx_id'] ?>">
                                        Return to Owner
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Modal 1: Assign Crew User -->
                        <div id="modal-assign-<?= (int)$item['tx_id'] ?>" class="modal-backdrop">
                            <div class="modal-card">
                                <div class="modal-header">
                                    <h3 class="modal-title">Assign Equipment to Event Crew Member</h3>
                                    <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST" action="return.php">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="assign_user">
                                    <input type="hidden" name="transaction_id" value="<?= (int)$item['tx_id'] ?>">

                                    <div class="modal-body">
                                        <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                            Assigning <strong>[<?= e($item['item_code']) ?>] <?= e($item['model']) ?></strong>
                                        </p>
                                        <div class="form-group">
                                            <label class="form-label">Crew Member Full Name <span class="required">*</span></label>
                                            <input type="text" name="equipment_user_name" class="form-control" placeholder="e.g., Amal Silva" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Crew Member Phone Number <span class="required">*</span></label>
                                            <input type="text" name="equipment_user_phone" class="form-control" placeholder="e.g., 0771234567" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Confirm Assignment</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Modal 2: Crew Return -->
                        <div id="modal-user-return-<?= (int)$item['tx_id'] ?>" class="modal-backdrop">
                            <div class="modal-card">
                                <div class="modal-header">
                                    <h3 class="modal-title">Record Crew Hand-In</h3>
                                    <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST" action="return.php">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="user_returned">
                                    <input type="hidden" name="transaction_id" value="<?= (int)$item['tx_id'] ?>">

                                    <div class="modal-body">
                                        <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                            Receiving back <strong>[<?= e($item['item_code']) ?>] <?= e($item['model']) ?></strong> from <strong><?= e($item['equipment_user_name']) ?></strong>.
                                        </p>
                                        <div class="form-group">
                                            <label class="form-label">Condition upon Return</label>
                                            <select name="user_returned_condition" class="form-control">
                                                <option value="Good" selected>Good — Clean and working</option>
                                                <option value="Fair">Fair — Minor dust / normal wear</option>
                                                <option value="Damage">Damage Noted</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea name="user_return_notes" class="form-control" rows="2" placeholder="Any comments or observations"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning" style="background: var(--warning); color: #fff;">Confirm Crew Hand-In</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Modal 3: Return to Owner -->
                        <div id="modal-owner-return-<?= (int)$item['tx_id'] ?>" class="modal-backdrop">
                            <div class="modal-card">
                                <div class="modal-header">
                                    <h3 class="modal-title">Final Return to Student Owner</h3>
                                    <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST" action="return.php">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="return_to_owner">
                                    <input type="hidden" name="transaction_id" value="<?= (int)$item['tx_id'] ?>">

                                    <div class="modal-body">
                                        <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                            Handing <strong>[<?= e($item['item_code']) ?>] <?= e($item['model']) ?></strong> back to student owner <strong><?= e($item['owner_name']) ?></strong> (<?= e($item['owner_phone']) ?>).
                                        </p>
                                        <div class="form-group">
                                            <label class="form-label">Final Return Condition</label>
                                            <select name="returned_condition" class="form-control">
                                                <option value="Good" selected>Good — Fully functional</option>
                                                <option value="Fair">Fair</option>
                                                <option value="Issue Reported">Issue Reported</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Final Notes (Optional)</label>
                                            <textarea name="final_notes" class="form-control" rows="2" placeholder="Any accessories confirmed with owner"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Complete Final Return</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="table-empty">No active equipment currently in "Received" state. <a href="receive.php">Receive equipment here</a>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
