<?php $pageTitle = 'Dashboard'; ?>
<?php ob_start(); ?>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon">💳</div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_transactions'] ?></span>
            <span class="stat-label">Total Transaksi</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['pending_transactions'] ?></span>
            <span class="stat-label">Pending</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🔑</div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_licenses'] ?></span>
            <span class="stat-label">Lisensi</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">⬇️</div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_downloads'] ?></span>
            <span class="stat-label">Downloads</span>
        </div>
    </div>
</div>

<!-- API Key Status Alert -->
<?php if (isset($apiKeyStatus) && !$apiKeyStatus['has_template']): ?>
<div class="api-key-alert">
    <div class="api-key-alert-content">
        <div class="api-key-alert-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                <path d="M12 15v2m-8 0h16"/>
            </svg>
        </div>
        <div class="api-key-alert-text">
            <h4>Menunggu API Key Template</h4>
            <p>Anda belum mengatur API Key Template. Silakan atur di <a href="<?= base_url('/settings/apikey') ?>">Pengaturan API Key</a> untuk melanjutkan.</p>
        </div>
        <div class="api-key-alert-action">
            <a href="<?= base_url('/settings/apikey') ?>" class="btn btn-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 4v16m8-8H4"/>
                </svg>
                Atur Sekarang
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="content-grid">
    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h3>Transaksi Terakhir</h3>
            <a href="<?= base_url('/transaction/history') ?>" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentTransactions)): ?>
                <p class="text-muted">Belum ada transaksi</p>
                <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">Buat Transaksi Baru</a>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $t): ?>
                            <tr>
                                <td>#<?= $t->id ?></td>
                                <td><?= format_currency($t->amount) ?></td>
                                <td>
                                    <span class="badge badge-<?= $t->getStatusBadgeClass() ?>">
                                        <?= $t->getStatusLabel() ?>
                                    </span>
                                </td>
                                <td><?= format_date($t->created_at) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Licenses -->
    <div class="card">
        <div class="card-header">
            <h3>Lisensi Anda</h3>
            <a href="<?= base_url('/licenses') ?>" class="btn btn-sm">Lihat Detail</a>
        </div>
        <div class="card-body">
            <?php if (empty($licenses)): ?>
                <p class="text-muted">Anda belum memiliki lisensi</p>
            <?php else: ?>
                <?php foreach ($licenses as $license): ?>
                    <div class="license-item">
                        <div class="license-key">
                            <code><?= e($license->getMaskedKey()) ?></code>
                        </div>
                        <div class="license-meta">
                            <small>Product: <?= e($license->product_id) ?></small>
                            <small>Assigned: <?= format_date($license->assigned_at) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3>Aksi Cepat</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">
                💳 Buat Transaksi Baru
            </a>
            <?php if (!empty($licenses)): ?>
                <a href="<?= base_url('/download') ?>" class="btn btn-success">
                    ⬇️ Download Plugin
                </a>
            <?php endif; ?>
            <a href="<?= base_url('/profile') ?>" class="btn btn-secondary">
                👤 Edit Profil
            </a>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
