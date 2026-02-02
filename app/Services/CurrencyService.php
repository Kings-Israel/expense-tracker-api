<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class CurrencyService
{
    /**
     * Get conversion rate from one currency to another using Google Finance
     * Falls back to ExchangeRate-API if Google is unavailable
     */
    public function getConversionRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "currency_rate_{$fromCurrency}_{$toCurrency}_" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($fromCurrency, $toCurrency) {
            // Try Google Finance first
            try {
                $rate = $this->fetchFromGoogle($fromCurrency, $toCurrency);
                if ($rate) {
                    return $rate;
                }
            } catch (Exception $e) {
                \Log::warning("Google Finance API failed: " . $e->getMessage());
            }

            // Fallback to ExchangeRate-API (free tier)
            try {
                return $this->fetchFromExchangeRateAPI($fromCurrency, $toCurrency);
            } catch (Exception $e) {
                \Log::error("All currency APIs failed: " . $e->getMessage());
                throw new Exception("Unable to fetch currency conversion rate");
            }
        });
    }

    private function fetchFromGoogle(string $from, string $to): ?float
    {
        // Google Finance doesn't have a public API, but we can scrape the exchange rate
        // Note: This is a simplified approach. In production, consider using official APIs
        $url = "https://www.google.com/finance/quote/{$from}-{$to}";

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->get($url);

        if ($response->successful()) {
            $html = $response->body();
            // Extract the exchange rate from the HTML
            if (preg_match('/data-last-price="([0-9.]+)"/', $html, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    private function fetchFromExchangeRateAPI(string $from, string $to): float
    {
        // Using free tier of ExchangeRate-API
        $response = Http::get("https://api.exchangerate-api.com/v4/latest/{$from}");

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['rates'][$to])) {
                return (float) $data['rates'][$to];
            }
        }

        throw new Exception("Failed to fetch conversion rate from ExchangeRate-API");
    }

    /**
     * Get list of all available currencies
     */
    public function getAvailableCurrencies(): array
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'KES' => 'Kenyan Shilling',
            'UGX' => 'Ugandan Shilling',
            'TZS' => 'Tanzanian Shilling',
            'ZAR' => 'South African Rand',
            'NGN' => 'Nigerian Naira',
            'GHS' => 'Ghanaian Cedi',
            'JPY' => 'Japanese Yen',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'AUD' => 'Australian Dollar',
            'CAD' => 'Canadian Dollar',
            'CHF' => 'Swiss Franc',
            'AED' => 'UAE Dirham',
            'SAR' => 'Saudi Riyal',
            'MXN' => 'Mexican Peso',
            'BRL' => 'Brazilian Real',
            'RUB' => 'Russian Ruble',
        ];
    }
}
