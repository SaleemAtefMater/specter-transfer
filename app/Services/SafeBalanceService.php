<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\SafeBalance;
use App\Models\SafeType;
use App\Models\Transfer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SafeBalanceService
{
    /**
     * Update safe balance for a specific safe type
     */
    public static function updateSafeBalance($safeTypeId, $amount, $operation = 'add')
    {
        try {
            Log::info("Updating safe balance", [
                'safe_type_id' => $safeTypeId,
                'amount' => $amount,
                'operation' => $operation
            ]);

            // Use firstOrCreate to avoid duplicate key errors
            $safeBalance = SafeBalance::firstOrCreate(
                ['safe_type_id' => $safeTypeId],
                [
                    'current_balance' => 0,
                    'initial_balance' => 0
                ]
            );

            $oldBalance = $safeBalance->current_balance;

            if ($operation === 'add') {
                $safeBalance->current_balance += $amount;
            } else {
                $safeBalance->current_balance -= $amount;
            }

            $safeBalance->save();

            Log::info("Safe balance updated", [
                'safe_type_id' => $safeTypeId,
                'old_balance' => $oldBalance,
                'new_balance' => $safeBalance->current_balance,
                'change' => $amount,
                'operation' => $operation
            ]);

            return $safeBalance;
        } catch (\Exception $e) {
            Log::error("Failed to update safe balance", [
                'safe_type_id' => $safeTypeId,
                'amount' => $amount,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    /**
     * Create balance transaction record
     */
    public static function createBalanceTransaction($safeTypeId, $type, $amount, $description, $referenceType = null, $referenceId = null)
    {
        try {
            $transaction = \App\Models\BalanceTransaction::create([
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

            Log::info("Balance transaction created", [
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'safe_type_id' => $safeTypeId,
                'type' => $type,
                'amount' => $amount
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error("Failed to create balance transaction", [
                'safe_type_id' => $safeTypeId,
                'type' => $type,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate if safe has sufficient balance for a transaction
     */
    public static function hasSufficientBalance($safeTypeId, $amount)
    {
        try {
            $safeBalance = SafeBalance::where('safe_type_id', $safeTypeId)->first();

            if (!$safeBalance) {
                Log::warning("Safe balance not found", ['safe_type_id' => $safeTypeId]);
                return false;
            }

            $hasBalance = $safeBalance->current_balance >= $amount;

            Log::info("Balance validation", [
                'safe_type_id' => $safeTypeId,
                'current_balance' => $safeBalance->current_balance,
                'required_amount' => $amount,
                'sufficient' => $hasBalance
            ]);

            return $hasBalance;
        } catch (\Exception $e) {
            Log::error("Balance validation failed", [
                'safe_type_id' => $safeTypeId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get safe balance for a specific safe type
     */
    public static function getSafeBalance(int $safeTypeId): float
    {
        $safeBalance = SafeBalance::where('safe_type_id', $safeTypeId)->first();
        return $safeBalance ? $safeBalance->current_balance : 0;
    }

    /**
     * Get total balance across all safe types
     */
    public static function getTotalSafeBalance()
    {
        return SafeBalance::sum('current_balance');
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
     * Validate delivery request
     */
    public static function validateDeliveryRequest(Transfer $transfer, int $deliverySafeTypeId, float $deliveryAmount): array
    {
        // Check if transfer can be delivered
        if (!$transfer->canBeDelivered()) {
            return [
                'is_valid' => false,
                'message' => "Transfer cannot be delivered. Current status: {$transfer->status}"
            ];
        }

        // Check if delivery amount is valid
        if ($deliveryAmount <= 0) {
            return [
                'is_valid' => false,
                'message' => "Delivery amount must be greater than zero"
            ];
        }

        // Check if delivery safe exists
        $deliverySafe = SafeType::find($deliverySafeTypeId);
        if (!$deliverySafe) {
            return [
                'is_valid' => false,
                'message' => "Invalid delivery safe selected"
            ];
        }

        // Check if delivery safe has sufficient balance
        if (!self::hasSufficientBalance($deliverySafeTypeId, $deliveryAmount)) {
            $currentBalance = self::getSafeBalance($deliverySafeTypeId);

            return [
                'is_valid' => false,
                'message' => "Insufficient balance in {$deliverySafe->name}. Current: $" . number_format($currentBalance, 2) . ", Required: $" . number_format($deliveryAmount, 2),
                'current_balance' => $currentBalance,
                'required_amount' => $deliveryAmount,
                'shortage' => $deliveryAmount - $currentBalance
            ];
        }

        return [
            'is_valid' => true,
            'message' => "Delivery request is valid",
            'delivery_safe_name' => $deliverySafe->name,
            'delivery_amount' => $deliveryAmount,
            'projected_profit' => ($transfer->sent_amount - $transfer->transfer_cost) - $deliveryAmount
        ];
    }

    /**
     * Get delivery options for a transfer
     */
    public static function getDeliveryOptions(Transfer $transfer): array
    {
        $availableSafes = SafeType::with('safeBalance')
            ->where('is_active', true)
            ->get()
            ->map(function ($safeType) use ($transfer) {
                $balance = $safeType->safeBalance?->current_balance ?? 0;
                $suggestedAmount = $transfer->receiver_net_amount ?? 0;
                return [
                    'id' => $safeType->id,
                    'name' => $safeType->name,
                    'type' => $safeType->type,
                    'current_balance' => $balance,
                    'balance_formatted' => '$' . number_format($balance, 2),
                    'can_cover_full_amount' => $balance >= $suggestedAmount
                ];
            });

        return [
            'transfer' => $transfer,
            'suggested_amount' => $transfer->receiver_net_amount ?? 0,
            'available_safes' => $availableSafes,
            'transfer_safe_balance' => self::getSafeBalance($transfer->transfer_type_id)
        ];
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

        return [
            'safe_type' => $safeType,
            'current_balance' => $balance?->current_balance ?? 0,
            'initial_balance' => $balance?->initial_balance ?? 0,
        ];
    }

    /**
     * Get transfer statistics for dashboard
     */
    public static function getTransferStatistics()
    {
        $totalTransfers = Transfer::count();
        $pendingTransfers = Transfer::whereIn('status', ['pending_verification', 'checked'])->count();
        $deliveredTransfers = Transfer::where('status', 'delivered')->count();
        $canceledTransfers = Transfer::where('status', 'canceled')->count();

        $totalSentAmount = Transfer::sum('sent_amount');
        $totalTransferCosts = Transfer::sum('transfer_cost');
        $totalDeliveryAmount = Transfer::where('status', 'delivered')->sum('delivery_amount');

        // Calculate profit safely
        $totalProfit = Transfer::where('status', 'delivered')->get()->sum(function($transfer) {
            $received = $transfer->sent_amount - $transfer->transfer_cost;
            $paid = $transfer->delivery_amount ?? 0;
            return $received - $paid;
        });

        return [
            'total_transfers' => $totalTransfers,
            'pending_transfers' => $pendingTransfers,
            'delivered_transfers' => $deliveredTransfers,
            'canceled_transfers' => $canceledTransfers,
            'total_sent_amount' => $totalSentAmount,
            'total_transfer_costs' => $totalTransferCosts,
            'total_delivery_amount' => $totalDeliveryAmount,
            'total_profit' => $totalProfit,
        ];
    }

    /**
     * Get comprehensive debt statistics
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
     * Get financial overview for dashboard
     */
    public static function getFinancialOverview()
    {
        $totalSafeBalance = self::getTotalSafeBalance();
        $debtStats = self::getDebtStatistics();
        $transferStats = self::getTransferStatistics();

        // Net position (safe balance minus unpaid debts)
        $netPosition = $totalSafeBalance - $debtStats['unpaid_debts'];

        return [
            'total_safe_balance' => $totalSafeBalance,
            'total_debts' => $debtStats['total_debts'],
            'paid_debts' => $debtStats['paid_debts'],
            'unpaid_debts' => $debtStats['unpaid_debts'],
            'unpaid_customers' => $debtStats['unpaid_customers'],
            'net_position' => $netPosition,
            'transfer_stats' => $transferStats,
        ];
    }
}
