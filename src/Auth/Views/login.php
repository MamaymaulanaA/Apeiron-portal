<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <div class="auth-logo-icon">🌿</div>
                    <span class="auth-logo-text"><?= APP_NAME ?></span>
                </div>
                
                <h2>Masuk ke akun Anda</h2>
                
                <?php if (has_flash('error')): ?>
                    <div class="alert alert-danger">
                        <span class="alert-icon">❌</span>
                        <div class="alert-content"><?= flash('error') ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (has_flash('success')): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon">✅</span>
                        <div class="alert-content"><?= flash('success') ?></div>
                    </div>
                <?php endif; ?>
                
                <form action="<?= base_url('/login') ?>" method="POST">
                    <?= csrf_field() ?>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               placeholder="nama@email.com"
                               value="<?= e($_POST['email'] ?? '') ?>" 
                               required 
                               autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="••••••••"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Masuk
                        </button>
                    </div>
                </form>
                
                <div class="auth-links">
                    Belum punya akun? 
                    <a href="<?= base_url('/register') ?>">Daftar sekarang</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
