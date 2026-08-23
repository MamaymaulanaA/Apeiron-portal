<?php $pageTitle = 'Kelola File Plugin'; ?>
<?php ob_start(); ?>

<!-- Upload Form -->
<div class="card">
    <div class="card-header">
        <h3>Upload Plugin Baru</h3>
    </div>
    <div class="card-body">
        <form action="<?= base_url('/admin/files/upload') ?>" 
              method="POST" 
              enctype="multipart/form-data">
            <?= csrf_field() ?>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="version">Versi</label>
                    <input type="text" 
                           id="version" 
                           name="version" 
                           placeholder="1.0.0"
                           pattern="\d+\.\d+\.\d+"
                           required>
                    <small class="help-text">Format: x.x.x</small>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="plugin">File Plugin (.zip)</label>
                    <input type="file" 
                           id="plugin" 
                           name="plugin" 
                           accept=".zip"
                           required>
                </div>
                
                <div class="form-group col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Existing Versions -->
<div class="card">
    <div class="card-header">
        <h3>Versi Tersedia</h3>
    </div>
    <div class="card-body">
        <?php if (empty($versions)): ?>
            <div class="empty-state">
                <p class="text-muted">Belum ada file plugin yang diupload</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Versi</th>
                        <th>Nama File</th>
                        <th>Ukuran</th>
                        <th>Uploaded</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versions as $v): ?>
                        <tr>
                            <td><strong>v<?= e($v['version']) ?></strong></td>
                            <td><?= e($v['file_name']) ?></td>
                            <td><?= format_file_size($v['file_size']) ?></td>
                            <td><?= format_date($v['uploaded_at']) ?></td>
                            <td>
                                <form action="<?= base_url('/admin/files/delete') ?>" 
                                      method="POST" 
                                      style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="version" value="<?= e($v['version']) ?>">
                                    <button type="submit" 
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Yakin ingin menghapus versi ini?')">
                                        🗑️ Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
