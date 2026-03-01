<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\CurrencyService;
use App\Services\MessageParserService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected $currencyService;
    protected $messageParser;

    public function __construct(CurrencyService $currencyService, MessageParserService $messageParser)
    {
        $this->currencyService = $currencyService;
        $this->messageParser = $messageParser;
    }

    /**
     * Parse and store expense from SMS message
     */
    public function parseAndStore(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = $request->user();
        $message = $request->message;

        // Check if it's an expense message
        if (!$this->messageParser->isExpenseMessage($message)) {
            return response()->json([
                'message' => 'Message does not appear to be an expense transaction',
            ], 400);
        }

        // Parse the message
        $parsedData = $this->messageParser->parseMessage($message);

        if (!$parsedData) {
            return response()->json([
                'message' => 'Unable to parse transaction details from message',
            ], 400);
        }

        // Get conversion rate
        $conversionRate = $this->currencyService->getConversionRate(
            $parsedData['currency'],
            $user->default_currency
        );

        // Calculate converted amount
        $convertedAmount = $parsedData['amount'] * $conversionRate;

        // Create expense record
        $expense = Expense::create([
            'user_id' => $user->id,
            'original_amount' => $parsedData['amount'],
            'original_currency' => $parsedData['currency'],
            'converted_amount' => $convertedAmount,
            'converted_currency' => $user->default_currency,
            'conversion_rate' => $conversionRate,
            'message' => $message,
            'source' => $parsedData['source'],
            'reference' => $parsedData['reference'],
            'transaction_date' => $parsedData['transaction_date'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully',
            'expense' => $expense,
        ], 201);
    }

    /**
     * Get expense summary with date filters
     */
    public function summary(Request $request)
    {
        $request->validate([
            'period' => 'required|in:current_week,current_month,last_month,last_3_months,last_6_months,last_9_months,last_year,last_2_years,last_5_years',
        ]);

        $user = $request->user();
        $period = $request->period;

        // Calculate date range based on period
        $dateRange = $this->getDateRangeForPeriod($period);

        // Get expenses for the period
        $expenses = Expense::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Calculate totals
        $totalAmount = $expenses->sum('converted_amount');
        $transactionCount = $expenses->count();

        // Group by date
        $dailyExpenses = $expenses->groupBy(function ($expense) {
            return $expense->transaction_date->format('Y-m-d');
        })->map(function ($dayExpenses) {
            return [
                'date' => $dayExpenses->first()->transaction_date->format('Y-m-d'),
                'total' => $dayExpenses->sum('converted_amount'),
                'count' => $dayExpenses->count(),
            ];
        })->values();

        // Group by source
        $bySource = $expenses->groupBy('source')->map(function ($sourceExpenses, $source) {
            return [
                'source' => $source ?? 'unknown',
                'total' => $sourceExpenses->sum('converted_amount'),
                'count' => $sourceExpenses->count(),
            ];
        })->values();

        return response()->json([
            'period' => $period,
            'date_range' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d'),
            ],
            'currency' => $user->default_currency,
            'summary' => [
                'total_amount' => round($totalAmount, 2),
                'transaction_count' => $transactionCount,
                'average_per_transaction' => $transactionCount > 0 ? round($totalAmount / $transactionCount, 2) : 0,
            ],
            'daily_expenses' => $dailyExpenses,
            'by_source' => $bySource,
            'expenses' => $expenses,
        ]);
    }

    /**
     * Get all expenses with pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $expenses = Expense::where('user_id', $user->id)
            ->orderBy('transaction_date', 'desc')
            ->paginate(50);

        return response()->json($expenses);
    }

    /**
     * Get single expense
     */
    public function show(Request $request, $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($expense);
    }

    /**
     * Delete expense
     */
    public function destroy(Request $request, $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Get date range for a given period
     */
    private function getDateRangeForPeriod(string $period): array
    {
        $end = now();
        $start = match ($period) {
            'current_week' => now()->startOfWeek(),
            'current_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            'last_3_months' => now()->subMonths(3)->startOfDay(),
            'last_6_months' => now()->subMonths(6)->startOfDay(),
            'last_9_months' => now()->subMonths(9)->startOfDay(),
            'last_year' => now()->subYear()->startOfDay(),
            'last_2_years' => now()->subYears(2)->startOfDay(),
            'last_5_years' => now()->subYears(5)->startOfDay(),
            default => now()->startOfMonth(),
        };

        // For "last_month", adjust end date to end of that month
        if ($period === 'last_month') {
            $end = now()->subMonth()->endOfMonth();
        }

        return ['start' => $start, 'end' => $end];
    }
}
