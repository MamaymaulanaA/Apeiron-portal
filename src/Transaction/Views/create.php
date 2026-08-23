<?php $pageTitle = 'Buat Transaksi'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Buat Transaksi Baru</h3>
    </div>
    <div class="card-body">
        <form action="<?= base_url('/transaction/create') ?>" method="POST">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="product_id">Product</label>
                <select id="product_id" name="product_id" class="form-control">
                    <option value="<?= DEFAULT_PRODUCT_ID ?>">Apeiron Kit - Lifetime License</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="amount">Jumlah Pembayaran (Rp)</label>
                <input type="number" 
                       id="amount" 
                       name="amount" 
                       value="<?= e(flash('old_amount') ?? '') ?>"
                       placeholder="Contoh: 500000"
                       min="1"
                       required>
                <small class="help-text">Masukkan jumlah yang Anda transfer</small>
            </div>
            
            <div class="alert alert-info">
                <strong>Informasi Pembayaran:</strong><br>
                Bank: BCA<br>
                No. Rekening: 1234567890<br>
                Atas Nama: Apeiron Indonesia<br><br>
                <em>Setelah transfer, upload bukti pembayaran pada langkah berikutnya.</em>
            </div>
            
            <button type="submit" class="btn btn-primary">Lanjutkan</button>
            <a href="<?= base_url('/dashboard') ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
