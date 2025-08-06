<?php

namespace App\Services;


use App\Models\Transfer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\SafeBalance;
use App\Models\BalanceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
class ReportService
{
    /**
     * Generate monthly financial report
     */
    public static function getMonthlyFinancialReport($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('n');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Transfer Statistics
        $transfers = Transfer::whereBetween('created_at', [$startDate, $endDate])->get();
        $transferStats = [
            'total_transfers' => $transfers->count(),
            'total_amount' => $transfers->sum('sent_amount'),
            'total_fees' => $transfers->sum('customer_price'),
            'total_profit' => $transfers->sum('profit'),
            'delivered_count' => $transfers->where('status', 'delivered')->count(),
            'canceled_count' => $transfers->where('status', 'canceled')->count(),
        ];

        // Debt Statistics
        $debts = Debt::whereBetween('created_at', [$startDate, $endDate])->get();
        $payments = DebtPayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        $debtStats = [
            'new_debts_count' => $debts->count(),
            'new_debts_amount' => $debts->sum('total_amount'),
            'payments_count' => $payments->count(),
            'payments_amount' => $payments->sum('payment_amount'),
        ];

        // Safe Balance Changes
        $transactions = BalanceTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->with('safeType')
            ->get();
        $balanceStats = [
            'total_deposits' => $transactions->whereIn('type', ['deposit', 'transfer_in'])->sum('amount'),
            'total_withdrawals' => $transactions->whereIn('type', ['withdrawal', 'transfer_out', 'debt_payment'])->sum('amount'),
            'net_change' => $transactions->whereIn('type', ['deposit', 'transfer_in'])->sum('amount') -
                $transactions->whereIn('type', ['withdrawal', 'transfer_out', 'debt_payment'])->sum('amount'),
        ];

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $startDate->format('F'),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'transfers' => $transferStats,
            'debts' => $debtStats,
            'balances' => $balanceStats,
            'transactions' => $transactions,
        ];
    }

    /**
     * Generate customer activity report
     */
    public static function getCustomerActivityReport($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        // Transfer customers
        $transferCustomers = Transfer::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('customer_name, customer_phone, COUNT(*) as transfer_count, SUM(sent_amount) as total_sent, SUM(customer_price) as total_fees')
            ->groupBy('customer_name', 'customer_phone')
            ->orderByDesc('transfer_count')
            ->get();

        // Debt customers
        $debtCustomers = Debt::selectRaw('creditor_name, creditor_phone, COUNT(*) as debt_count, SUM(total_amount) as total_debt, SUM(paid_amount) as total_paid, SUM(remaining_amount) as remaining_debt')
            ->groupBy('creditor_name', 'creditor_phone')
            ->orderByDesc('total_debt')
            ->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'transfer_customers' => $transferCustomers,
            'debt_customers' => $debtCustomers,
        ];
    }

    /**
     * Generate profit/loss report
     */
    public static function getProfitLossReport($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->startOfYear();
        $endDate = $endDate ?? Carbon::now()->endOfYear();

        // Transfer profits by type
        $transferProfits = Transfer::whereBetween('created_at', [$startDate, $endDate])
            ->with('transferType')
            ->get()
            ->groupBy('transferType.name')
            ->map(function ($transfers, $type) {
                return [
                    'type' => $type,
                    'count' => $transfers->count(),
                    'total_revenue' => $transfers->sum('customer_price'),
                    'total_cost' => $transfers->sum('transfer_cost'),
                    'profit' => $transfers->sum('profit'),
                    'avg_profit_per_transfer' => $transfers->avg('profit'),
                ];
            });

        // Safe balance changes
        $safeChanges = SafeBalance::with('safeType')
            ->get()
            ->map(function ($balance) {
                return [
                    'safe_name' => $balance->safeType->name,
                    'safe_type' => $balance->safeType->type,
                    'initial_balance' => $balance->initial_balance,
                    'current_balance' => $balance->current_balance,
                    'change' => $balance->current_balance - $balance->initial_balance,
                ];
            });

        // Overall summary
        $totalRevenue = Transfer::whereBetween('created_at', [$startDate, $endDate])->sum('customer_price');
        $totalCosts = Transfer::whereBetween('created_at', [$startDate, $endDate])->sum('transfer_cost');
        $totalProfit = $totalRevenue - $totalCosts;

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_costs' => $totalCosts,
                'total_profit' => $totalProfit,
                'profit_margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0,
            ],
            'transfer_profits' => $transferProfits,
            'safe_changes' => $safeChanges,
        ];
    }

    /**
     * Generate debt analysis report
     */
    public static function getDebtAnalysisReport()
    {
        $totalDebts = Debt::sum('total_amount');
        $paidDebts = Debt::sum('paid_amount');
        $unpaidDebts = $totalDebts - $paidDebts;

        // Debts by status
        $debtsByStatus = Debt::selectRaw('status, COUNT(*) as count, SUM(total_amount) as total_amount, SUM(remaining_amount) as remaining_amount')
            ->groupBy('status')
            ->get();

        // Overdue debts
        $overdueDebts = Debt::where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'canceled'])
            ->with('safeType')
            ->get();

        // Payment history analysis
        $recentPayments = DebtPayment::with(['debt', 'safeType'])
            ->orderByDesc('payment_date')
            ->limit(20)
            ->get();

        // Top debtors
        $topDebtors = Debt::selectRaw('creditor_name, SUM(remaining_amount) as total_remaining, COUNT(*) as debt_count')
            ->where('remaining_amount', '>', 0)
            ->groupBy('creditor_name')
            ->orderByDesc('total_remaining')
            ->limit(10)
            ->get();

        return [
            'summary' => [
                'total_debts' => $totalDebts,
                'paid_debts' => $paidDebts,
                'unpaid_debts' => $unpaidDebts,
                'collection_rate' => $totalDebts > 0 ? ($paidDebts / $totalDebts) * 100 : 0,
            ],
            'debts_by_status' => $debtsByStatus,
            'overdue_debts' => $overdueDebts,
            'recent_payments' => $recentPayments,
            'top_debtors' => $topDebtors,
        ];
    }

    /**
     * Generate daily transactions summary
     */
    public static function getDailyTransactionsSummary($date = null)
    {
        $date = $date ?? Carbon::today();

        $transfers = Transfer::whereDate('created_at', $date)->get();
        $payments = DebtPayment::whereDate('payment_date', $date)->get();
        $transactions = BalanceTransaction::whereDate('created_at', $date)->with('safeType')->get();

        return [
            'date' => $date,
            'transfers' => [
                'count' => $transfers->count(),
                'total_amount' => $transfers->sum('sent_amount'),
                'total_profit' => $transfers->sum('profit'),
            ],
            'payments' => [
                'count' => $payments->count(),
                'total_amount' => $payments->sum('payment_amount'),
            ],
            'transactions' => [
                'count' => $transactions->count(),
                'deposits' => $transactions->whereIn('type', ['deposit', 'transfer_in'])->sum('amount'),
                'withdrawals' => $transactions->whereIn('type', ['withdrawal', 'transfer_out', 'debt_payment'])->sum('amount'),
            ],
        ];
    }

    /**
     * Export report to CSV
     */
    public static function exportToCsv($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            if (!empty($data)) {
                fputcsv($file, array_keys((array) $data[0]));

                // Add data rows
                foreach ($data as $row) {
                    fputcsv($file, (array) $row);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
