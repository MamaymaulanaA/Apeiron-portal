<?php
/**
 * Upload Proof Controller
 */

require_once PORTAL_ROOT . '/src/Validation/ProofUploadValidator.php';
require_once PORTAL_ROOT . '/src/Services/TransactionService.php';

class UploadProofController
{
    /**
     * Show upload form
     */
    public function show(): void
    {
        $user = auth();
        
        // Get pending transactions that need proof
        $transactions = Transaction::findByUser($user->id);
        $pendingTransactions = array_filter($transactions, function($t) {
            return $t->isPending();
        });
        
        // Get specific transaction if ID is in flash or query
        $transactionId = flash('transaction_id') ?? ($_GET['id'] ?? null);
        $selectedTransaction = null;
        
        if ($transactionId) {
            $selectedTransaction = Transaction::find((int) $transactionId);
            if ($selectedTransaction && $selectedTransaction->user_id !== $user->id) {
                $selectedTransaction = null;
            }
        }
        
        include PORTAL_ROOT . '/src/Transaction/Views/upload-proof.php';
    }
    
    /**
     * Handle proof upload
     */
    public function upload(): void
    {
        CsrfProtection::validateOrFail();
        
        $user = auth();
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        
        if (!$transactionId) {
            flash('error', 'Transaksi tidak valid');
            redirect(base_url('/transaction/upload'));
        }
        
        // Check file upload
        if (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'File bukti pembayaran wajib diupload');
            redirect(base_url('/transaction/upload?id=' . $transactionId));
        }
        
        // Validate file
        $errors = ProofUploadValidator::validate($_FILES['proof']);
        
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect(base_url('/transaction/upload?id=' . $transactionId));
        }
        
        // Upload proof
        $result = TransactionService::uploadProof($transactionId, $user->id, $_FILES['proof']);
        
        if (!$result['success']) {
            flash('error', $result['error']);
            redirect(base_url('/transaction/upload?id=' . $transactionId));
        }
        
        flash('success', 'Bukti pembayaran berhasil diupload. Mohon tunggu konfirmasi dari admin.');
        redirect(base_url('/transaction/history'));
    }
}
