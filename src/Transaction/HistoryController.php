<?php
/**
 * Transaction History Controller
 */

class HistoryController
{
    /**
     * Show transaction history
     */
    public function index(): void
    {
        $user = auth();
        
        // Get all transactions for user
        $transactions = Transaction::findByUser($user->id);
        
        include PORTAL_ROOT . '/src/Transaction/Views/history.php';
    }
}
