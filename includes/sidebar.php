<?php
/**
 * EpicLens System - Application Sidebar Navigation
 */
require_once __DIR__ . '/auth.php';
$isAdmin = is_admin();
$currentPage = $currentPage ?? 'dashboard';
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <circle cx="12" cy="12" r="4"></circle>
                <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"></line>
                <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"></line>
                <line x1="14.83" y1="9.17" x2="19.07" y2="4.93"></line>
                <line x1="4.93" y1="19.07" x2="9.17" y2="14.83"></line>
            </svg>
        </div>
        <div class="brand-text">
            <span class="brand-title">EpicLens</span>
            <span class="brand-sub">Event Equipment</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Core Operations</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="receive.php" class="nav-link <?= $currentPage === 'receive' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                    <span>Receive Equipment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="return.php" class="nav-link <?= $currentPage === 'return' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    <span>Return & Lending</span>
                </a>
            </li>
        </ul>

        <?php if ($isAdmin): ?>
            <div class="nav-section-title">Administration</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="equipment.php" class="nav-link <?= $currentPage === 'equipment' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <span>Equipment Master</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="equipment_add.php" class="nav-link <?= $currentPage === 'equipment_add' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        <span>Add Equipment</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff.php" class="nav-link <?= $currentPage === 'staff' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>Staff Accounts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transactions.php" class="nav-link <?= $currentPage === 'transactions' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span>Audit Log / History</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="version-tag">EpicLens v2.0 • Secure</div>
    </div>
</aside>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
