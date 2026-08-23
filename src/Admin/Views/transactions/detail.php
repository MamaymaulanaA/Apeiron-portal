<?php $pageTitle = 'Detail Transaksi #' . $transaction->id; ?>
<?php ob_start(); ?>

<a href="<?= base_url('/admin/transactions') ?>" class="btn btn-sm btn-secondary mb-3">
    ← Kembali
</a>

<div class="content-grid">
    <!-- Transaction Info -->
    <div class="card">
        <div class="card-header">
            <h3>Informasi Transaksi</h3>
            <span class="badge badge-<?= $transaction->getStatusBadgeClass() ?>">
                <?= $transaction->getStatusLabel() ?>
            </span>
        </div>
        <div class="card-body">
            <table class="table table-borderless">
                <tr>
                    <td width="150">ID Transaksi</td>
                    <td><strong>#<?= $transaction->id ?></strong></td>
                </tr>
                <tr>
                    <td>User</td>
                    <td>
                        <?= e($transaction->user->name ?? 'N/A') ?><br>
                        <small><?= e($transaction->user->email ?? '') ?></small>
                    </td>
                </tr>
                <tr>
                    <td>Product</td>
                    <td><?= e($transaction->product_id) ?></td>
                </tr>
                <tr>
                    <td>Amount</td>
                    <td><strong><?= format_currency($transaction->amount) ?></strong></td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td><?= format_date($transaction->created_at) ?></td>
                </tr>
                <?php if ($transaction->approved_at): ?>
                    <tr>
                        <td>Approved At</td>
                        <td><?= format_date($transaction->approved_at) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($transaction->admin_notes): ?>
                    <tr>
                        <td>Catatan</td>
                        <td><?= e($transaction->admin_notes) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Proof Image -->
    <div class="card">
        <div class="card-header">
            <h3>Bukti Pembayaran</h3>
        </div>
        <div class="card-body">
            <?php if ($transaction->proof_image_path): ?>
                <?php 
                $proofUrl = STORAGE_PATH . '/' . $transaction->proof_image_path;
                $extension = pathinfo($transaction->proof_image_path, PATHINFO_EXTENSION);
                ?>
                
                <?php if ($extension === 'pdf'): ?>
                    <p>📄 PDF Document</p>
                    <p><strong><?= e($transaction->proof_original_name) ?></strong></p>
                <?php else: ?>
                    <img src="data:image/<?= $extension ?>;base64,<?= base64_encode(file_get_contents($proofUrl)) ?>" 
                         alt="Bukti Transfer" 
                         class="proof-image"
                         style="max-width: 100%; height: auto;">
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p class="text-warning">⚠️ Bukti pembayaran belum diupload</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- License Info (if approved) -->
<?php if ($license): ?>
    <div class="card">
        <div class="card-header">
            <h3>License Generated</h3>
        </div>
        <div class="card-body">
            <table class="table table-borderless">
                <tr>
                    <td width="150">License Key</td>
                    <td><code><?= e($license->license_key) ?></code></td>
                </tr>
                <tr>
                    <td>Assigned At</td>
                    <td><?= format_date($license->assigned_at) ?></td>
                </tr>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Action Forms (if pending) -->
<?php if ($transaction->isPending() && $transaction->proof_image_path): ?>
    <div class="content-grid">
        <!-- Approve Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-success">✓ Approve Transaksi</h3>
            </div>
            <div class="card-body">
                <form action="<?= base_url('/admin/transactions/approve') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="transaction_id" value="<?= $transaction->id ?>">

                    <div class="form-group">
                        <label for="notes">Catatan (opsional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Yakin ingin menyetujui transaksi ini?')">
                        ✓ Approve & Generate License
                    </button>
                </form>
            </div>
        </div>

        <!-- Reject Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-danger">✕ Tolak Transaksi</h3>
            </div>
            <div class="card-body">
                <form action="<?= base_url('/admin/transactions/reject') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="transaction_id" value="<?= $transaction->id ?>">

                    <div class="form-group">
                        <label for="reason">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea id="reason" name="reason" class="form-control" rows="2" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Yakin ingin menolak transaksi ini?')">
                        ✕ Reject
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php elseif ($transaction->isPending() && !$transaction->proof_image_path): ?>
    <div class="alert alert-warning">
        ⚠️ Menunggu user upload bukti pembayaran sebelum dapat diproses
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
