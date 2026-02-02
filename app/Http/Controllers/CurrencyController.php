<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get list of available currencies
     */
    public function index()
    {
        $currencies = $this->currencyService->getAvailableCurrencies();

        return response()->json([
            'currencies' => $currencies,
        ]);
    }

    /**
     * Get conversion rate between two currencies
     */
    public function conversionRate(Request $request)
    {
        $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        try {
            $rate = $this->currencyService->getConversionRate(
                strtoupper($request->from),
                strtoupper($request->to)
            );

            return response()->json([
                'from' => strtoupper($request->from),
                'to' => strtoupper($request->to),
                'rate' => $rate,
                'date' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch conversion rate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user's default currency
     */
    public function updateDefaultCurrency(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|size:3',
        ]);

        $user = $request->user();
        $user->default_currency = strtoupper($request->currency);
        $user->save();

        return response()->json([
            'message' => 'Default currency updated successfully',
            'user' => $user,
        ]);
    }
}
