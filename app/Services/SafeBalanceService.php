<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\SafeBalance;
use App\Models\SafeType;

class SafeBalanceService
{
    /**
     * Create a new class instance.
     */
    /**
     * Get total balance across all safe types
     */
    public static function getTotalSafeBalance()
    {
        return SafeBalance::sum('current_balance');
    }

    /**
     * Get comprehensive debt statistics
     * Returns: total_debts, paid_debts, unpaid_debts, unpaid_customers
     */
    public static function getDebtStatistics()
    {
        $totalDebts = Debt::sum('total_amount');
        $paidDebts = Debt::sum('paid_amount');
        $unpaidDebts = $totalDebts - $paidDebts;
        $unpaidCustomers = Debt::where('status', '!=', 'paid')
            ->where('status', '!=', 'canceled')
            ->distinct('creditor_name')
            ->count('creditor_name');

        return [
            'total_debts' => $totalDebts,
            'paid_debts' => $paidDebts,
            'unpaid_debts' => $unpaidDebts,
            'unpaid_customers' => $unpaidCustomers
        ];
    }

    /**
     * Update safe balance for a specific safe type
     */
    public static function updateSafeBalance($safeTypeId, $amount, $operation = 'add')
    {
        $safeBalance = SafeBalance::where('safe_type_id', $safeTypeId)->first();

        if (!$safeBalance) {
            $safeBalance = SafeBalance::create([
                'safe_type_id' => $safeTypeId,
                'current_balance' => 0,
                'initial_balance' => 0
            ]);
        }

        if ($operation === 'add') {
            $safeBalance->current_balance += $amount;
        } else {
            $safeBalance->current_balance -= $amount;
        }

        $safeBalance->save();
        return $safeBalance;
    }

    /**
     * Get balance summary for a specific safe type
     */
    public static function getSafeTypeSummary($safeTypeId)
    {
        $safeType = SafeType::with('safeBalance')->find($safeTypeId);

        if (!$safeType) {
            return null;
        }

        $balance = $safeType->safeBalance;
        $totalDebts = $safeType->debts()->sum('total_amount');
        $paidDebts = $safeType->debts()->sum('paid_amount');

        return [
            'safe_type' => $safeType,
            'current_balance' => $balance?->current_balance ?? 0,
            'initial_balance' => $balance?->initial_balance ?? 0,
            'total_debts_associated' => $totalDebts,
            'paid_debts_associated' => $paidDebts,
            'unpaid_debts_associated' => $totalDebts - $paidDebts
        ];
    }

    /**
     * Get financial overview for dashboard
     */
    public static function getFinancialOverview()
    {
        $totalSafeBalance = self::getTotalSafeBalance();
        $debtStats = self::getDebtStatistics();

        // Net position (safe balance minus unpaid debts)
        $netPosition = $totalSafeBalance - $debtStats['unpaid_debts'];

        return [
            'total_safe_balance' => $totalSafeBalance,
            'total_debts' => $debtStats['total_debts'],
            'paid_debts' => $debtStats['paid_debts'],
            'unpaid_debts' => $debtStats['unpaid_debts'],
            'unpaid_customers' => $debtStats['unpaid_customers'],
            'net_position' => $netPosition
        ];
    }

    /**
     * Create balance transaction record
     */
    public static function createBalanceTransaction($safeTypeId, $type, $amount, $description, $referenceType = null, $referenceId = null)
    {
        return \App\Models\BalanceTransaction::create([
            'transaction_number' => 'TXN' . date('Y') . str_pad(
                    \App\Models\BalanceTransaction::whereYear('created_at', date('Y'))->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            'safe_type_id' => $safeTypeId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ]);
    }

    /**
     * Validate if safe has sufficient balance for a transaction
     */
    public static function hasSufficientBalance($safeTypeId, $amount)
    {
        $safeBalance = SafeBalance::where('safe_type_id', $safeTypeId)->first();

        if (!$safeBalance) {
            return false;
        }

        return $safeBalance->current_balance >= $amount;
    }

    /**
     * Get safe types with their current balances
     */
    public static function getSafeTypesWithBalances()
    {
        return SafeType::with('safeBalance')
            ->where('is_active', true)
            ->get()
            ->map(function ($safeType) {
                return [
                    'id' => $safeType->id,
                    'name' => $safeType->name,
                    'type' => $safeType->type,
                    'account_number' => $safeType->account_number,
                    'current_balance' => $safeType->safeBalance?->current_balance ?? 0,
                    'balance_formatted' => '$' . number_format($safeType->safeBalance?->current_balance ?? 0, 2)
                ];
            });
    }

    /**
     * Get monthly balance summary
     */
    public static function getMonthlyBalanceSummary($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('n');

        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $transactions = \App\Models\BalanceTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->with('safeType')
            ->get();

        $summary = [];

        foreach ($transactions as $transaction) {
            $safeTypeName = $transaction->safeType->name;

            if (!isset($summary[$safeTypeName])) {
                $summary[$safeTypeName] = [
                    'deposits' => 0,
                    'withdrawals' => 0,
                    'debt_payments' => 0,
                    'net_change' => 0
                ];
            }

            switch ($transaction->type) {
                case 'deposit':
                case 'transfer_in':
                    $summary[$safeTypeName]['deposits'] += $transaction->amount;
                    $summary[$safeTypeName]['net_change'] += $transaction->amount;
                    break;
                case 'withdrawal':
                case 'transfer_out':
                    $summary[$safeTypeName]['withdrawals'] += $transaction->amount;
                    $summary[$safeTypeName]['net_change'] -= $transaction->amount;
                    break;
                case 'debt_payment':
                    $summary[$safeTypeName]['debt_payments'] += $transaction->amount;
                    $summary[$safeTypeName]['net_change'] -= $transaction->amount;
                    break;
            }
        }

        return $summary;
    }
}
