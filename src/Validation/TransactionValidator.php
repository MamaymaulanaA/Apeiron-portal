<?php
/**
 * Transaction Validator
 */

class TransactionValidator
{
    /**
     * Validate new transaction
     */
    public static function validateCreate(array $data): array
    {
        $errors = [];
        
        // Amount validation
        if (!isset($data['amount']) || $data['amount'] === '') {
            $errors[] = 'Jumlah pembayaran wajib diisi';
        } elseif (!is_numeric($data['amount'])) {
            $errors[] = 'Jumlah pembayaran harus berupa angka';
        } elseif ((float) $data['amount'] <= 0) {
            $errors[] = 'Jumlah pembayaran harus lebih dari 0';
        } elseif ((float) $data['amount'] > 999999999) {
            $errors[] = 'Jumlah pembayaran tidak valid';
        }
        
        // Product validation (optional, uses default if not provided)
        if (!empty($data['product_id'])) {
            if (strlen($data['product_id']) > 100) {
                $errors[] = 'Product ID tidak valid';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate approval
     */
    public static function validateApproval(array $data): array
    {
        $errors = [];
        
        if (!isset($data['transaction_id']) || !is_numeric($data['transaction_id'])) {
            $errors[] = 'Transaction ID tidak valid';
        }
        
        return $errors;
    }
    
    /**
     * Validate rejection
     */
    public static function validateRejection(array $data): array
    {
        $errors = [];
        
        if (!isset($data['transaction_id']) || !is_numeric($data['transaction_id'])) {
            $errors[] = 'Transaction ID tidak valid';
        }
        
        if (empty($data['reason'])) {
            $errors[] = 'Alasan penolakan wajib diisi';
        } elseif (strlen($data['reason']) > 1000) {
            $errors[] = 'Alasan penolakan maksimal 1000 karakter';
        }
        
        return $errors;
    }
}
