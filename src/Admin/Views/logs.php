<?php $pageTitle = 'Audit Logs'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Audit Logs</h3>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="text-muted text-center">Belum ada log aktivitas</p>
        <?php else: ?>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small><?= format_date($log->created_at) ?></small>
                            </td>
                            <td><?= e($log->admin_name ?? 'System') ?></td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?= e($log->action) ?>
                                </span>
                            </td>
                            <td>
                                <?= e($log->target_type) ?>
                                <?php if ($log->target_id): ?>
                                    #<?= $log->target_id ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= e($log->description ?? '-') ?></small>
                            </td>
                            <td>
                                <small><?= e($log->ip_address) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($pagination && $pagination['last_page'] > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <a href="?page=<?= $i ?>" 
                           class="btn btn-sm <?= $i === $pagination['current_page'] ? 'btn-primary' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
