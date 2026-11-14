<?php
/**
 * EpicLens System - Receive Equipment Page (Stage 1 of Event Operations)
 *
 * Check-in gear brought by student owners.
 * Filters exclusively for equipment with status "Not Received Yet".
 */
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = getDB();
$currentUser = current_user();
$errors = [];
$preselectedId = (int)($_GET['equipment_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $receivedCondition = trim($_POST['received_condition'] ?? 'Good');
    $notes = trim($_POST['notes'] ?? '');

    $receivedByUserId = (int)$currentUser['id'];
    $receivedByName = $currentUser['full_name'];
    $receivedAt = date('Y-m-d H:i:s');

    if ($equipmentId <= 0) {
        $errors[] = 'Please select an equipment item to receive.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock and inspect equipment record
            $checkStmt = $pdo->prepare('SELECT id, item_code, model, category, owner_name, status FROM equipment WHERE id = ? FOR UPDATE');
            $checkStmt->execute([$equipmentId]);
            $item = $checkStmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                $errors[] = 'Equipment record not found.';
            } elseif ($item['status'] === 'Received') {
                $pdo->rollBack();
                $errors[] = 'This equipment has already been received.';
            } elseif ($item['status'] === 'Returned') {
                $pdo->rollBack();
                $errors[] = 'This equipment is already marked as Returned for this event.';
            } else {
                // 1. Create transaction record (Event user unassigned at initial check-in)
                $txStmt = $pdo->prepare('
                    INSERT INTO transactions 
                    (equipment_id, received_by_user_id, received_by_name, received_at, equipment_user_name, equipment_user_phone, event_user_assigned_at, received_condition, notes, status, created_at)
                    VALUES (?, ?, ?, ?, NULL, NULL, NULL, ?, ?, "Received", NOW())
                ');
                $txStmt->execute([
                    $equipmentId,
                    $receivedByUserId,
                    $receivedByName,
                    $receivedAt,
                    $receivedCondition,
                    $notes ?: null
                ]);

                // 2. Transition status: Not Received Yet -> Received
                $updStmt = $pdo->prepare('UPDATE equipment SET status = "Received" WHERE id = ?');
                $updStmt->execute([$equipmentId]);

                $pdo->commit();

                setFlash('success', sprintf(
                    'Equipment [%s] "%s" received from owner %s. Event User is currently unassigned.',
                    $item['item_code'],
                    $item['model'],
                    $item['owner_name']
                ));
                header('Location: return.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Database error: ' . (APP_DEBUG ? $e->getMessage() : 'Transaction failed.');
        }
    }
}

// Fetch all equipment currently "Not Received Yet"
try {
    $equipmentList = $pdo->query('
        SELECT id, item_code, category, model, serial_number, owner_name, owner_phone, notes, status
        FROM equipment
        WHERE status = "Not Received Yet"
        ORDER BY category ASC, item_code ASC
    ')->fetchAll();
} catch (PDOException $e) {
    $equipmentList = [];
}

$pageTitle = 'Receive Equipment';
$currentPage = 'receive';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Receive Equipment from Student Owner</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Step 1: Check in equipment arriving at the campus event desk</span>
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($equipmentList)): ?>
            <div class="alert alert-info">
                <span>All registered equipment items have already been received or returned! There are currently no items pending check-in.</span>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Return to Dashboard</a>
        <?php else: ?>
            <form method="POST" action="receive.php">
                <?= Csrf::field() ?>

                <div class="form-group">
                    <label class="form-label" for="receive_equipment_select">Select Equipment Item <span class="required">*</span></label>
                    <select id="receive_equipment_select" name="equipment_id" class="form-control" required autofocus>
                        <option value="">-- Choose an item to check in (<?= count($equipmentList) ?> pending) --</option>
                        <?php foreach ($equipmentList as $eq): ?>
                            <option value="<?= (int)$eq['id'] ?>"
                                <?= $preselectedId === (int)$eq['id'] ? 'selected' : '' ?>
                                data-code="<?= e($eq['item_code']) ?>"
                                data-model="<?= e($eq['model']) ?>"
                                data-category="<?= e($eq['category']) ?>"
                                data-owner="<?= e($eq['owner_name']) ?>"
                                data-phone="<?= e($eq['owner_phone']) ?>"
                                data-notes="<?= e($eq['notes'] ?? 'None') ?>">
                                [<?= e($eq['item_code']) ?>] <?= e($eq['model']) ?> — Owner: <?= e($eq['owner_name']) ?> (<?= e($eq['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Interactive Item Preview Card -->
                <div id="receive_preview_card" class="card" style="display: none; background: var(--bg-alt); margin-bottom: 1.25rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <div>
                                <span id="prev_code" class="item-code-badge"></span>
                                <strong id="prev_model" style="margin-left: 0.5rem; font-size: 1rem;"></strong>
                            </div>
                            <span id="prev_category" class="badge badge-category"></span>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                            <div>Student Owner: <strong id="prev_owner" style="color: var(--text-main);"></strong> &bull; Phone: <span id="prev_phone"></span></div>
                            <div>Notes: <span id="prev_notes"></span></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="received_condition">Check-in Condition</label>
                    <select id="received_condition" name="received_condition" class="form-control">
                        <option value="Good" selected>Good — No visible scratches or damage</option>
                        <option value="Fair">Fair — Normal wear, functioning fine</option>
                        <option value="Minor Damage">Minor Damage (scratches / small marks noted)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Check-in Notes / Accessories Included</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="e.g., 2 batteries, 64GB SD card, carrying strap, lens hood included"></textarea>
                    <div class="form-hint">Record any accessories or specific physical marks at the time of receipt.</div>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span>Confirm & Receive Equipment</span>
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
