<?php $pageTitle = 'Riwayat Transaksi'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Riwayat Transaksi</h3>
        <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary btn-sm">
            + Transaksi Baru
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <p>Belum ada riwayat transaksi</p>
                <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">
                    Buat Transaksi Pertama
                </a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Jumlah</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td>#<?= $t->id ?></td>
                            <td><?= e($t->product_id) ?></td>
                            <td><?= format_currency($t->amount) ?></td>
                            <td>
                                <?php if ($t->proof_image_path): ?>
                                    <span class="text-success">✓ Uploaded</span>
                                <?php else: ?>
                                    <span class="text-warning">⏳ Belum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $t->getStatusBadgeClass() ?>">
                                    <?= $t->getStatusLabel() ?>
                                </span>
                            </td>
                            <td><?= format_date($t->created_at) ?></td>
                            <td>
                                <?php if ($t->isPending() && empty($t->proof_image_path)): ?>
                                    <a href="<?= base_url('/transaction/upload?id=' . $t->id) ?>" 
                                       class="btn btn-sm btn-primary">
                                        Upload Bukti
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($t->isApproved()): ?>
                                    <a href="<?= base_url('/download') ?>" 
                                       class="btn btn-sm btn-success">
                                        Download
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if ($t->admin_notes): ?>
                            <tr class="notes-row">
                                <td colspan="7">
                                    <small class="text-muted">
                                        <strong>Catatan Admin:</strong> <?= e($t->admin_notes) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
