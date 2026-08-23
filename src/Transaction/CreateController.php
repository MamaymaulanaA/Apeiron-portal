<?php
/**
 * Transaction Create Controller
 */

require_once PORTAL_ROOT . '/src/Validation/TransactionValidator.php';
require_once PORTAL_ROOT . '/src/Services/TransactionService.php';

class CreateController
{
    /**
     * Show create transaction form
     */
    public function show(): void
    {
        $user = auth();
        include PORTAL_ROOT . '/src/Transaction/Views/create.php';
    }
    
    /**
     * Store new transaction
     */
    public function store(): void
    {
        CsrfProtection::validateOrFail();
        
        $user = auth();
        
        $data = [
            'amount' => $_POST['amount'] ?? '',
            'product_id' => $_POST['product_id'] ?? DEFAULT_PRODUCT_ID
        ];
        
        // Validate
        $errors = TransactionValidator::validateCreate($data);
        
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            flash('old_amount', $data['amount']);
            redirect(base_url('/transaction/create'));
        }
        
        // Create transaction
        $result = TransactionService::create(
            $user->id,
            (float) $data['amount'],
            $data['product_id']
        );
        
        if (!$result['success']) {
            flash('error', $result['error']);
            redirect(base_url('/transaction/create'));
        }
        
        flash('success', 'Transaksi berhasil dibuat. Silakan upload bukti pembayaran.');
        flash('transaction_id', $result['transaction']->id);
        redirect(base_url('/transaction/upload'));
    }
}
