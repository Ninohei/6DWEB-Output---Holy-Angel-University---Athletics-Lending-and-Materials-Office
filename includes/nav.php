<?php
// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-nav">
        <?php if ($_SESSION['role'] == 'student'): ?>
            <!-- Student Navigation -->
            <li>
                <a href="../student/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="../student/browse.php" class="<?php echo $current_page == 'browse.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Browse Equipment</span>
                </a>
            </li>
            <li>
                <a href="../student/my-equipment.php" class="<?php echo $current_page == 'my-equipment.php' ? 'active' : ''; ?>">
                    <span class="nav-text">My Equipment</span>
                    <?php
                    $active_count = getActiveLoansCount($pdo, $_SESSION['user_id']);
                    if ($active_count > 0): ?>
                        <span class="badge"><?php echo $active_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="../student/history.php" class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
                    <span class="nav-text">History</span>
                </a>
            </li>
            <li>
                <a href="../student/account.php" class="<?php echo $current_page == 'account.php' ? 'active' : ''; ?>">
                    <span class="nav-text">My Account</span>
                </a>
            </li>

        <?php else: ?>
            <!-- Admin Navigation -->
            <li>
                <a href="../admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="../admin/inventory.php" class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Inventory</span>
                </a>
            </li>
            <li>
                <a href="../admin/requests.php" class="<?php echo $current_page == 'requests.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Requests</span>
                    <?php if ($pending_count > 0): ?>
                        <span class="badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="../admin/checkouts.php" class="<?php echo $current_page == 'checkouts.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Active Loans</span>
                </a>
            </li>
            <li>
                <a href="../admin/returns.php" class="<?php echo $current_page == 'returns.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Returns</span>
                </a>
            </li>
            <li>
                <a href="../admin/students.php" class="<?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Students</span>
                </a>
            </li>
            <li>
                <a href="../admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <span class="nav-text">Reports</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</aside>
