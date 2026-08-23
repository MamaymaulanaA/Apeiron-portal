<?php $pageTitle = 'Admin Dashboard'; ?>
<?php ob_start(); ?>

<div class="dashboard-stats">
    <div class="stat-card stat-warning">
        <div class="stat-icon">⏳</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['pending_transactions'] ?></span>
            <span class="stat-label">Pending</span>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['approved_transactions'] ?></span>
            <span class="stat-label">Approved</span>
        </div>
    </div>

    <div class="stat-card stat-danger">
        <div class="stat-icon">❌</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['rejected_transactions'] ?></span>
            <span class="stat-label">Rejected</span>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">🔄</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['pending_license_transactions'] ?></span>
            <span class="stat-label">Pending License</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['total_users'] ?></span>
            <span class="stat-label">Users</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">⬇️</div>
        <div class="stat-content">
            <span class="stat-value"><?= $stats['total_downloads'] ?></span>
            <span class="stat-label">Downloads</span>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- Pending Transactions -->
    <div class="card">
        <div class="card-header">
            <h3>Transaksi Pending</h3>
            <a href="<?= base_url('/admin/transactions/pending') ?>" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentPending)): ?>
                <p class="text-muted text-center">Tidak ada transaksi pending</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPending as $t): ?>
                            <tr>
                                <td>#<?= $t->id ?></td>
                                <td><?= e($t->user->name ?? 'N/A') ?></td>
                                <td><?= format_currency($t->amount) ?></td>
                                <td>
                                    <a href="<?= base_url('/admin/transactions/detail?id=' . $t->id) ?>"
                                       class="btn btn-sm btn-primary">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3>Aktivitas Terakhir</h3>
            <a href="<?= base_url('/admin/logs') ?>" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentLogs)): ?>
                <p class="text-muted text-center">Belum ada aktivitas</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach (array_slice($recentLogs, 0, 5) as $log): ?>
                        <li class="activity-item">
                            <span class="activity-icon">📝</span>
                            <div class="activity-content">
                                <strong><?= e($log['admin_name'] ?? 'System') ?></strong>
                                <?= e($log['action']) ?>
                                <?php if ($log['target_id']): ?>
                                    #<?= $log['target_id'] ?>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?= format_date($log['created_at']) ?>
                                </small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
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
            <a href="<?= base_url('/admin/transactions/pending') ?>" class="btn btn-warning">
                ⏳ Review Transaksi (<?= $stats['pending_transactions'] ?>)
            </a>
            <a href="<?= base_url('/admin/files') ?>" class="btn btn-primary">
                📁 Upload Plugin Baru
            </a>
            <a href="<?= base_url('/admin/users') ?>" class="btn btn-secondary">
                👥 Kelola Users
            </a>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
