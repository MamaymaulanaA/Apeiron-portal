<?php $pageTitle = 'Upload Bukti Pembayaran'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Upload Bukti Pembayaran</h3>
    </div>
    <div class="card-body">
        <?php if (empty($pendingTransactions) && !$selectedTransaction): ?>
            <div class="empty-state">
                <p>Tidak ada transaksi yang membutuhkan bukti pembayaran</p>
                <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">
                    Buat Transaksi Baru
                </a>
            </div>
        <?php else: ?>
            <form action="<?= base_url('/transaction/upload') ?>" 
                  method="POST" 
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="transaction_id">Pilih Transaksi</label>
                    <select id="transaction_id" name="transaction_id" class="form-control" required>
                        <option value="">-- Pilih Transaksi --</option>
                        <?php foreach ($pendingTransactions as $t): ?>
                            <option value="<?= $t->id ?>" 
                                    <?= ($selectedTransaction && $selectedTransaction->id === $t->id) ? 'selected' : '' ?>>
                                #<?= $t->id ?> - <?= format_currency($t->amount) ?> 
                                (<?= format_date($t->created_at, 'd M Y') ?>)
                                <?= empty($t->proof_image_path) ? '' : '✓ Sudah upload' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="proof">File Bukti Transfer</label>
                    <input type="file" 
                           id="proof" 
                           name="proof" 
                           accept=".jpg,.jpeg,.png,.gif,.pdf"
                           required>
                    <small class="help-text">
                        Format: JPG, PNG, GIF, PDF. Maksimal <?= format_file_size(PROOF_MAX_SIZE) ?>
                    </small>
                </div>
                
                <div class="alert alert-warning">
                    <strong>Penting:</strong><br>
                    - Pastikan bukti transfer jelas dan terbaca<br>
                    - Bukti transfer akan diverifikasi oleh admin<br>
                    - Proses verifikasi maksimal 1x24 jam
                </div>
                
                <button type="submit" class="btn btn-primary">Upload Bukti</button>
                <a href="<?= base_url('/transaction/history') ?>" class="btn btn-secondary">Kembali</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
