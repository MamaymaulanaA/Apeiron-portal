<?php $pageTitle = 'Register'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1><?= APP_NAME ?></h1>
            <h2>Daftar Akun</h2>
            
            <?php if (has_flash('error')): ?>
                <div class="alert alert-danger"><?= flash('error') ?></div>
            <?php endif; ?>
            
            <form action="<?= base_url('/register') ?>" method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= e(flash('old_name') ?? '') ?>"
                           required 
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= e(flash('old_email') ?? '') ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required
                           minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    <small class="help-text">
                        Minimal <?= PASSWORD_MIN_LENGTH ?> karakter, mengandung huruf besar dan angka
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Konfirmasi Password</label>
                    <input type="password" 
                           id="password_confirm" 
                           name="password_confirm" 
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Daftar</button>
            </form>
            
            <p class="auth-links">
                Sudah punya akun? <a href="<?= base_url('/login') ?>">Login</a>
            </p>
        </div>
    </div>
</body>
</html>
