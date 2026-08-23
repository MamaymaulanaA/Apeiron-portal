<?php $pageTitle = 'Lisensi Saya'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Daftar Lisensi</h3>
    </div>
    <div class="card-body">
        <?php if (empty($licenses)): ?>
            <div class="empty-state">
                <p>Anda belum memiliki lisensi</p>
                <a href="<?= base_url('/transaction/create') ?>" class="btn btn-primary">
                    Beli Lisensi
                </a>
            </div>
        <?php else: ?>
            <div class="license-list">
                <?php foreach ($licenses as $license): ?>
                    <div class="license-card">
                        <div class="license-header">
                            <h4><?= e($license->product_id) ?></h4>
                            <?php if ($license->license_status && ($license->license_status['success'] ?? false)): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Status Unknown</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="license-body">
                            <div class="license-key-display">
                                <label>License Key</label>
                                <div class="key-container">
                                    <code id="key-<?= $license->id ?>"><?= e($license->license_key) ?></code>
                                    <button type="button" 
                                            class="btn btn-sm btn-copy"
                                            onclick="copyToClipboard('key-<?= $license->id ?>')">
                                        📋 Copy
                                    </button>
                                </div>
                            </div>
                            
                            <div class="license-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Assigned</span>
                                    <span class="meta-value"><?= format_date($license->assigned_at) ?></span>
                                </div>
                                
                                <?php if ($license->license_status && isset($license->license_status['expires_at'])): ?>
                                    <div class="meta-item">
                                        <span class="meta-label">Expires</span>
                                        <span class="meta-value"><?= format_date($license->license_status['expires_at']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="license-footer">
                            <a href="<?= base_url('/download') ?>" class="btn btn-primary btn-sm">
                                ⬇️ Download Plugin
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('License key berhasil disalin!');
    });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
