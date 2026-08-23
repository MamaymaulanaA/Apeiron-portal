<?php
/**
 * Base Layout - Light Theme
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">🌿</div>
                <h1><?= APP_NAME ?></h1>
            </div>
            
            <nav class="sidebar-nav">
                <?php if (is_admin()): ?>
                    <!-- Admin Navigation -->
                    <div class="nav-section">
                        <div class="nav-section-title">Menu</div>
                        <a href="<?= base_url('/admin/dashboard') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/dashboard') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            Dashboard
                        </a>
                        <a href="<?= base_url('/admin/transactions') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/transactions') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">💳</span>
                            Transaksi
                            <?php 
                            $pendingCount = Transaction::countByStatus(Transaction::STATUS_WAITING);
                            if ($pendingCount > 0): 
                            ?>
                                <span class="nav-badge"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?= base_url('/admin/users') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/users') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">👥</span>
                            User
                        </a>
                        <a href="<?= base_url('/admin/files') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/files') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">📁</span>
                            File Plugin
                        </a>
                        <a href="<?= base_url('/admin/logs') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/logs') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">📝</span>
                            Log Aktivitas
                        </a>
                    </div>
                    
                    <div class="nav-section">
                        <div class="nav-section-title">General</div>
                        <a href="<?= base_url('/dashboard') ?>" class="nav-link">
                            <span class="nav-icon">🏠</span>
                            Portal User
                        </a>
                    </div>
                <?php else: ?>
                    <!-- User Navigation -->
                    <div class="nav-section">
                        <div class="nav-section-title">Menu</div>
                        <a href="<?= base_url('/dashboard') ?>" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/dashboard' ? 'active' : '' ?>">
                            <span class="nav-icon">🏠</span>
                            Dashboard
                        </a>
                        <a href="<?= base_url('/transaction/create') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/transaction') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">💰</span>
                            Transaksi
                        </a>
                        <a href="<?= base_url('/licenses') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/licenses') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">🔑</span>
                            Lisensi
                        </a>
                        <a href="<?= base_url('/download') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/download') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">⬇️</span>
                            Download
                        </a>
                    </div>
                    
                    <div class="nav-section">
                        <div class="nav-section-title">General</div>
                        <a href="<?= base_url('/profile') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/profile') !== false ? 'active' : '' ?>">
                            <span class="nav-icon">👤</span>
                            Profil
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="nav-section" style="margin-top: auto;">
                    <a href="<?= base_url('/logout') ?>" class="nav-link">
                        <span class="nav-icon">🚪</span>
                        Logout
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <button class="btn btn-icon btn-outline sidebar-toggle" onclick="toggleSidebar()" style="display: none;">
                    ☰
                </button>
                
                <div class="topbar-search">
                    <span class="search-icon">🔍</span>
                    <input type="search" placeholder="Cari...">
                </div>
                
                <div class="topbar-actions">
                    <button class="topbar-icon-btn">
                        📧
                    </button>
                    <button class="topbar-icon-btn">
                        🔔
                        <?php if (is_admin() && $pendingCount ?? 0 > 0): ?>
                            <span class="notification-badge"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="user-dropdown">
                        <div class="user-avatar">
                            <?= strtoupper(substr(auth()->name, 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= e(auth()->name) ?></span>
                            <span class="user-email"><?= e(auth()->email) ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Header -->
            <div class="content-header">
                <div>
                    <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($headerActions)): ?>
                    <div class="header-actions">
                        <?= $headerActions ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Content Body -->
            <div class="content-body">
                <!-- Flash Messages -->
                <?php if (has_flash('success')): ?>
                    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                        <span class="alert-icon">✅</span>
                        <div class="alert-content"><?= flash('success') ?></div>
                    </div>
                <?php endif; ?>

                <?php if (has_flash('error')): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                        <span class="alert-icon">❌</span>
                        <div class="alert-content"><?= flash('error') ?></div>
                    </div>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
    
    <script src="<?= base_url('/assets/js/app.js') ?>"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        
        // Show toggle on mobile
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar-toggle').style.display = 'flex';
        }
    </script>
</body>
</html>
