<?php $pageTitle = 'Download Plugin'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Download Apeiron Kit</h3>
    </div>
    <div class="card-body">
        <?php if (empty($versions)): ?>
            <div class="empty-state">
                <p class="text-muted">Belum ada versi plugin tersedia</p>
            </div>
        <?php elseif (empty($licenses)): ?>
            <div class="empty-state">
                <p class="text-warning">⚠️ Anda belum memiliki lisensi aktif</p>
                <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">
                    Beli Lisensi
                </a>
            </div>
        <?php else: ?>
            <p class="text-muted mb-4">
                Pilih versi yang ingin Anda download. Link download berlaku selama <?= SIGNED_URL_EXPIRY / 60 ?> menit.
            </p>
            
            <?php foreach ($licenses as $license): ?>
                <div class="license-download-section">
                    <h4>
                        License: <code><?= e($license->getMaskedKey()) ?></code>
                    </h4>
                    
                    <div class="version-list">
                        <?php foreach ($versions as $version): ?>
                            <div class="version-item">
                                <div class="version-info">
                                    <strong>v<?= e($version['version']) ?></strong>
                                    <small class="text-muted">
                                        <?= format_file_size($version['file_size']) ?>
                                    </small>
                                </div>
                                <a href="<?= $downloadLinks[$license->id][$version['version']] ?>" 
                                   class="btn btn-primary btn-sm">
                                    ⬇️ Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="alert alert-info mt-4">
                <strong>Petunjuk Instalasi:</strong><br>
                1. Download file ZIP plugin<br>
                2. Di WordPress admin, buka Plugins > Add New > Upload Plugin<br>
                3. Pilih file ZIP yang sudah didownload<br>
                4. Klik Install Now, kemudian Activate<br>
                5. Masukkan license key Anda di menu Settings > Apeiron Kit
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
