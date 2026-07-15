<nav class="navbar">
    <div class="container">
        <div>
            <a href="index.php" class="navbar-brand">🏢 مدیریت اموال</a>
        </div>
        <div class="navbar-menu">
            <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>داشبورد</a>
            <a href="assets.php" <?php echo basename($_SERVER['PHP_SELF']) == 'assets.php' ? 'class="active"' : ''; ?>>اموال</a>
            <a href="centers.php" <?php echo basename($_SERVER['PHP_SELF']) == 'centers.php' ? 'class="active"' : ''; ?>>مراکز</a>
            <a href="categories.php" <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'class="active"' : ''; ?>>دسته‌بندی‌ها</a>
            <a href="transfers.php" <?php echo basename($_SERVER['PHP_SELF']) == 'transfers.php' ? 'class="active"' : ''; ?>>جابجایی‌ها</a>
            <span class="user-info">👤 <?php echo $_SESSION['fullname']; ?></span>
            <a href="logout.php" class="btn btn-danger btn-sm">خروج</a>
        </div>
    </div>
</nav>
