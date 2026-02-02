<?php

namespace App\Services;

use Carbon\Carbon;

class MessageParserService
{
    /**
     * Parse SMS message and extract transaction details
     */
    public function parseMessage(string $message): ?array
    {
        $message = trim($message);

        // Try different parsing patterns
        $parsers = [
            'parseKenyanBankMessage',
            'parseMobileMoney',
            'parseCardPayment',
        ];

        foreach ($parsers as $parser) {
            $result = $this->$parser($message);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Parse Kenyan bank debit messages
     * Example: "Dear Customer, your account 01********0000000 has been debited with KES 1139 on 2026-02-01 15:02:41. Your balance is KES 2847.31. REF: S90832101"
     */
    private function parseKenyanBankMessage(string $message): ?array
    {
        // Look for debit transactions
        if (!preg_match('/debited/i', $message)) {
            return null;
        }

        $result = [
            'source' => 'bank',
            'reference' => null,
            'transaction_date' => null,
            'amount' => null,
            'currency' => null,
        ];

        // Extract amount and currency
        // Patterns: "KES 1139", "USD 1.19", "Ksh 1,000"
        if (preg_match('/(?:KES|USD|EUR|GBP|Ksh)\s*([0-9,]+\.?[0-9]*)/i', $message, $matches)) {
            $amount = str_replace(',', '', $matches[1]);
            $result['amount'] = (float) $amount;

            // Extract currency
            if (preg_match('/(KES|USD|EUR|GBP|Ksh)/i', $message, $currencyMatch)) {
                $currency = strtoupper($currencyMatch[1]);
                $result['currency'] = $currency === 'KSH' ? 'KES' : $currency;
            }
        }

        // Extract date
        // Patterns: "2026-02-01 15:02:41", "02-FEB-2026 02:01:25"
        if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $message, $matches)) {
            $result['transaction_date'] = Carbon::parse($matches[1]);
        } elseif (preg_match('/(\d{2}-[A-Z]{3}-\d{4}\s+\d{2}:\d{2}:\d{2})/', $message, $matches)) {
            $result['transaction_date'] = Carbon::parse($matches[1]);
        }

        // Extract reference
        if (preg_match('/REF:\s*([A-Z0-9]+)/i', $message, $matches)) {
            $result['reference'] = $matches[1];
        }

        return $result['amount'] && $result['currency'] ? $result : null;
    }

    /**
     * Parse mobile money messages (M-PESA, etc.)
     * Example: "UB22N5IXAO confirmed. Ksh 1,000 sent to KPC for account XXXXXXXX on 2/2/26 at 8:37 AM New M-PESA balance is Ksh3,034.30. Transaction cost Ksh10.00."
     */
    private function parseMobileMoney(string $message): ?array
    {
        // Look for mobile money indicators
        if (!preg_match('/(?:confirmed|sent to|paid to|M-PESA)/i', $message)) {
            return null;
        }

        $result = [
            'source' => 'mobile_money',
            'reference' => null,
            'transaction_date' => null,
            'amount' => null,
            'currency' => null,
        ];

        // Extract reference (usually at the start)
        if (preg_match('/^([A-Z0-9]{8,12})\s+confirmed/i', $message, $matches)) {
            $result['reference'] = $matches[1];
        }

        // Extract main transaction amount (not balance or transaction cost)
        // Pattern: "Ksh 1,000 sent to"
        if (preg_match('/(?:KES|Ksh)\s*([0-9,]+\.?[0-9]*)\s+(?:sent to|paid to|for)/i', $message, $matches)) {
            $amount = str_replace(',', '', $matches[1]);
            $result['amount'] = (float) $amount;
            $result['currency'] = 'KES';
        }

        // Extract transaction cost if present and add to amount
        if (preg_match('/Transaction cost\s+(?:KES|Ksh)\s*([0-9,]+\.?[0-9]*)/i', $message, $matches)) {
            $cost = str_replace(',', '', $matches[1]);
            if ($result['amount']) {
                $result['amount'] += (float) $cost;
            }
        }

        // Extract date
        // Patterns: "2/2/26 at 8:37 AM", "02/02/2026 at 08:37"
        if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{2,4})\s+at\s+(\d{1,2}:\d{2}\s*(?:AM|PM)?)/i', $message, $matches)) {
            $dateStr = $matches[1] . ' ' . $matches[2];
            try {
                $result['transaction_date'] = Carbon::parse($dateStr);
            } catch (\Exception $e) {
                $result['transaction_date'] = now();
            }
        }

        return $result['amount'] && $result['currency'] ? $result : null;
    }

    /**
     * Parse card payment messages
     * Example: "Dear Customer, Card PAYMENT transaction dated 02-FEB-2026 02:01:25 of USD 1.19 AWS EMEA>aws.amazon.c LU was successful."
     */
    private function parseCardPayment(string $message): ?array
    {
        // Look for card payment indicators
        if (!preg_match('/(?:Card PAYMENT|VISA|MASTERCARD|card transaction)/i', $message)) {
            return null;
        }

        $result = [
            'source' => 'bank',
            'reference' => null,
            'transaction_date' => null,
            'amount' => null,
            'currency' => null,
        ];

        // Extract amount and currency
        if (preg_match('/of\s+(USD|EUR|GBP|KES)\s*([0-9,]+\.?[0-9]*)/i', $message, $matches)) {
            $result['currency'] = strtoupper($matches[1]);
            $amount = str_replace(',', '', $matches[2]);
            $result['amount'] = (float) $amount;
        }

        // Extract date
        if (preg_match('/dated\s+(\d{2}-[A-Z]{3}-\d{4}\s+\d{2}:\d{2}:\d{2})/i', $message, $matches)) {
            $result['transaction_date'] = Carbon::parse($matches[1]);
        }

        return $result['amount'] && $result['currency'] ? $result : null;
    }

    /**
     * Determine if a message is an expense (debit) or income (credit)
     */
    public function isExpenseMessage(string $message): bool
    {
        $expenseKeywords = ['debited', 'sent to', 'paid to', 'payment', 'purchase', 'withdrawal'];
        $incomeKeywords = ['credited', 'received from', 'deposit', 'salary'];

        $message = strtolower($message);

        foreach ($expenseKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
