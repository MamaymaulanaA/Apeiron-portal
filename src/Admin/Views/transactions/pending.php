<?php $pageTitle = $title ?? 'Transaksi Pending'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3><?= e($title) ?></h3>
        <div class="btn-group">
            <a href="<?= base_url('/admin/transactions/pending') ?>" 
               class="btn btn-sm <?= $status === 'pending' ? 'btn-warning' : '' ?>">
                Pending
            </a>
            <a href="<?= base_url('/admin/transactions/approved') ?>" 
               class="btn btn-sm <?= $status === 'approved' ? 'btn-success' : '' ?>">
                Approved
            </a>
            <a href="<?= base_url('/admin/transactions/rejected') ?>" 
               class="btn btn-sm <?= $status === 'rejected' ? 'btn-danger' : '' ?>">
                Rejected
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <p class="text-muted text-center">Tidak ada transaksi <?= e($status) ?></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Bukti</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td>#<?= $t->id ?></td>
                            <td>
                                <?= e($t->user->name ?? 'N/A') ?><br>
                                <small class="text-muted"><?= e($t->user->email ?? '') ?></small>
                            </td>
                            <td><?= format_currency($t->amount) ?></td>
                            <td>
                                <?php if ($t->proof_image_path): ?>
                                    <span class="text-success">✓</span>
                                <?php else: ?>
                                    <span class="text-warning">⏳</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_date($t->created_at) ?></td>
                            <td>
                                <a href="<?= base_url('/admin/transactions/detail?id=' . $t->id) ?>" 
                                   class="btn btn-sm btn-primary">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($pagination['last_page'] > 1): ?>
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
