<?php
/**
 * Admin Transaction Controller
 */

require_once PORTAL_ROOT . '/src/Validation/TransactionValidator.php';
require_once PORTAL_ROOT . '/src/Services/TransactionService.php';

class TransactionController
{
    /**
     * Show pending transactions
     */
    public function pending(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $result = Transaction::findByStatus(Transaction::STATUS_WAITING, $page);

        $transactions = $result['data'];
        $pagination = $result;
        $status = 'pending';
        $title = 'Transaksi Pending';

        include PORTAL_ROOT . '/src/Admin/Views/transactions/pending.php';
    }

    /**
     * Show approved transactions
     */
    public function approved(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $result = Transaction::findByStatus(Transaction::STATUS_APPROVED, $page);

        $transactions = $result['data'];
        $pagination = $result;
        $status = 'approved';
        $title = 'Transaksi Disetujui';

        include PORTAL_ROOT . '/src/Admin/Views/transactions/approved.php';
    }

    /**
     * Show rejected transactions
     */
    public function rejected(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $result = Transaction::findByStatus(Transaction::STATUS_REJECTED, $page);

        $transactions = $result['data'];
        $pagination = $result;
        $status = 'rejected';
        $title = 'Transaksi Ditolak';

        include PORTAL_ROOT . '/src/Admin/Views/transactions/rejected.php';
    }

    /**
     * Show transaction detail
     */
    public function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            flash('error', 'ID transaksi tidak valid');
            redirect(base_url('/admin/transactions'));
        }

        $transaction = Transaction::findWithRelations($id);

        if (!$transaction) {
            flash('error', 'Transaksi tidak ditemukan');
            redirect(base_url('/admin/transactions'));
        }

        // Get license if approved
        $license = null;
        if ($transaction->isApproved()) {
            $license = UserLicense::findByTransaction($id);
        }

        include PORTAL_ROOT . '/src/Admin/Views/transactions/detail.php';
    }

    /**
     * Approve transaction
     */
    public function approve(): void
    {
        CsrfProtection::validateOrFail();

        $admin = auth();
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Validate
        $errors = TransactionValidator::validateApproval(['transaction_id' => $transactionId]);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect(base_url('/admin/transactions/detail?id=' . $transactionId));
        }

        // Approve
        $result = TransactionService::approve($transactionId, $admin->id, $notes);

        if (!$result['success']) {
            flash('error', $result['error']);
        } else {
            flash('success', $result['message']);
        }

        redirect(base_url('/admin/transactions/detail?id=' . $transactionId));
    }

    /**
     * Reject transaction
     */
    public function reject(): void
    {
        CsrfProtection::validateOrFail();

        $admin = auth();
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        // Validate
        $errors = TransactionValidator::validateRejection([
            'transaction_id' => $transactionId,
            'reason' => $reason
        ]);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect(base_url('/admin/transactions/detail?id=' . $transactionId));
        }

        // Reject
        $result = TransactionService::reject($transactionId, $admin->id, $reason);

        if (!$result['success']) {
            flash('error', $result['error']);
        } else {
            flash('success', $result['message']);
        }

        redirect(base_url('/admin/transactions/detail?id=' . $transactionId));
    }
}
