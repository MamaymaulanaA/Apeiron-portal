<?php $pageTitle = 'Kelola Users'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Daftar Users</h3>
        <div class="btn-group">
            <a href="<?= base_url('/admin/users') ?>" 
               class="btn btn-sm <?= !isset($_GET['role']) ? 'btn-primary' : '' ?>">
                Semua
            </a>
            <a href="<?= base_url('/admin/users?role=user') ?>" 
               class="btn btn-sm <?= ($_GET['role'] ?? '') === 'user' ? 'btn-primary' : '' ?>">
                Users
            </a>
            <a href="<?= base_url('/admin/users?role=admin') ?>" 
               class="btn btn-sm <?= ($_GET['role'] ?? '') === 'admin' ? 'btn-primary' : '' ?>">
                Admins
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted text-center">Tidak ada users</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?= $u->id ?></td>
                            <td><?= e($u->name) ?></td>
                            <td><?= e($u->email) ?></td>
                            <td>
                                <span class="badge badge-<?= $u->isAdmin() ? 'primary' : 'secondary' ?>">
                                    <?= ucfirst($u->role) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u->is_active ? 'success' : 'danger' ?>">
                                    <?= $u->is_active ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td><?= format_date($u->created_at, 'd M Y') ?></td>
                            <td>
                                <?php if ($u->id !== auth()->id): ?>
                                    <form action="<?= base_url('/admin/users/toggle-status') ?>" 
                                          method="POST" 
                                          style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u->id ?>">
                                        <button type="submit" 
                                                class="btn btn-sm <?= $u->is_active ? 'btn-warning' : 'btn-success' ?>"
                                                onclick="return confirm('Yakin?')">
                                            <?= $u->is_active ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($pagination['last_page'] > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <?php 
                        $params = $_GET;
                        $params['page'] = $i;
                        ?>
                        <a href="?<?= http_build_query($params) ?>" 
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
