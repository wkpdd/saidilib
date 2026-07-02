<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    public static function format($amount): string
    {
        $currency = Setting::get('currency', 'DA');
        $n = number_format((float) $amount, 0, ',', ' ');

        return app()->getLocale() === 'ar'
            ? "{$n} {$currency}"
            : "{$n} {$currency}";
    }
}
