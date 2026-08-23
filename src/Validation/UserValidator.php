<?php
/**
 * User Validator
 */

class UserValidator
{
    /**
     * Validate registration data
     */
    public static function validateRegistration(array $data): array
    {
        $errors = [];
        
        // Name validation
        if (empty($data['name'])) {
            $errors[] = 'Nama wajib diisi';
        } elseif (strlen($data['name']) < 2) {
            $errors[] = 'Nama minimal 2 karakter';
        } elseif (strlen($data['name']) > 255) {
            $errors[] = 'Nama maksimal 255 karakter';
        }
        
        // Email validation
        if (empty($data['email'])) {
            $errors[] = 'Email wajib diisi';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid';
        } elseif (strlen($data['email']) > 255) {
            $errors[] = 'Email maksimal 255 karakter';
        }
        
        // Password validation
        $passwordErrors = self::validatePassword($data['password'] ?? '');
        $errors = array_merge($errors, $passwordErrors);
        
        // Password confirmation
        if ($data['password'] !== ($data['password_confirm'] ?? '')) {
            $errors[] = 'Konfirmasi password tidak cocok';
        }
        
        return $errors;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        
        if (empty($password)) {
            $errors[] = 'Password wajib diisi';
            return $errors;
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password minimal ' . PASSWORD_MIN_LENGTH . ' karakter';
        }
        
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf besar';
        }
        
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung angka';
        }
        
        return $errors;
    }
    
    /**
     * Validate profile update
     */
    public static function validateProfileUpdate(array $data, int $userId): array
    {
        $errors = [];
        
        // Name
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors[] = 'Nama wajib diisi';
            } elseif (strlen($data['name']) < 2) {
                $errors[] = 'Nama minimal 2 karakter';
            }
        }
        
        // Email
        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $errors[] = 'Email wajib diisi';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid';
            } elseif (User::emailExists($data['email'], $userId)) {
                $errors[] = 'Email sudah digunakan';
            }
        }
        
        // Password change
        if (!empty($data['new_password'])) {
            $passwordErrors = self::validatePassword($data['new_password']);
            $errors = array_merge($errors, $passwordErrors);
            
            if ($data['new_password'] !== ($data['new_password_confirm'] ?? '')) {
                $errors[] = 'Konfirmasi password baru tidak cocok';
            }
            
            if (empty($data['current_password'])) {
                $errors[] = 'Password lama wajib diisi untuk mengganti password';
            }
        }
        
        return $errors;
    }
}
