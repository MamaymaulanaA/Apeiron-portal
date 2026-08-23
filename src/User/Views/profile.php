<?php $pageTitle = 'Profil Saya'; ?>
<?php ob_start(); ?>

<div class="card">
    <div class="card-header">
        <h3>Edit Profil</h3>
    </div>
    <div class="card-body">
        <form action="<?= base_url('/profile') ?>" method="POST">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="<?= e($user->name) ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?= e($user->email) ?>"
                       required>
            </div>
            
            <hr>
            <h4>Ganti Password</h4>
            <p class="text-muted">Kosongkan jika tidak ingin mengubah password</p>
            
            <div class="form-group">
                <label for="current_password">Password Lama</label>
                <input type="password" 
                       id="current_password" 
                       name="current_password">
            </div>
            
            <div class="form-group">
                <label for="new_password">Password Baru</label>
                <input type="password" 
                       id="new_password" 
                       name="new_password"
                       minlength="<?= PASSWORD_MIN_LENGTH ?>">
            </div>
            
            <div class="form-group">
                <label for="new_password_confirm">Konfirmasi Password Baru</label>
                <input type="password" 
                       id="new_password_confirm" 
                       name="new_password_confirm">
            </div>
            
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Informasi Akun</h3>
    </div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <td>Member Sejak</td>
                <td><?= format_date($user->created_at) ?></td>
            </tr>
            <tr>
                <td>Login Terakhir</td>
                <td><?= $user->last_login_at ? format_date($user->last_login_at) : 'N/A' ?></td>
            </tr>
            <tr>
                <td>Role</td>
                <td>
                    <span class="badge badge-<?= $user->isAdmin() ? 'primary' : 'secondary' ?>">
                        <?= ucfirst($user->role) ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include PORTAL_ROOT . '/src/Views/layouts/main.php'; ?>
