<?php

use App\Models\Setting;

if (! function_exists('ae_money')) {
    /** Format an in-game money amount; small amounts get more decimals so they don't show as 0.00. */
    function ae_money(?float $value, ?int $decimals = null): string
    {
        static $symbol;
        $symbol ??= Setting::allValues()['currency_symbol'] ?? '$';
        $value ??= 0;
        $decimals ??= abs($value) >= 100 ? 2 : (abs($value) >= 1 ? 3 : (abs($value) >= 0.01 ? 4 : 6));

        return ($value < 0 ? '-' : '').$symbol.number_format(abs($value), $decimals);
    }
}

if (! function_exists('ae_earn')) {
    /** Earnings are always shown to 11 decimal places, e.g. $0.00000000117. */
    function ae_earn(?float $value): string
    {
        return ae_money($value, 11);
    }
}

if (! function_exists('ae_currency')) {
    function ae_currency(): string
    {
        return Setting::allValues()['currency_symbol'] ?? '$';
    }
}

if (! function_exists('ae_change')) {
    /** Render a +/- change as a coloured span. */
    function ae_change(?int $change, bool $isNew = false): string
    {
        if ($isNew) {
            return '<span class="chip chip-new">new</span>';
        }
        if ($change === null) {
            return '<span class="muted">-</span>';
        }
        if ($change === 0) {
            return '<span class="muted">0</span>';
        }

        return $change > 0
            ? '<span class="up">+'.number_format($change).'</span>'
            : '<span class="down">'.number_format($change).'</span>';
    }
}

if (! function_exists('ae_days')) {
    function ae_days(?float $days): string
    {
        if ($days === null) {
            return 'never';
        }
        if ($days < 1) {
            return 'under a day';
        }
        if ($days < 60) {
            return number_format($days).' days';
        }
        if ($days < 730) {
            return number_format($days / 30.44, 1).' months';
        }

        return number_format($days / 365, 1).' years';
    }
}

if (! function_exists('ae_num')) {
    /** number_format without trailing zeros: 1.50 -> 1.5, 30.00 -> 30 */
    function ae_num(float|int|null $value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}

if (! function_exists('ae_settings_link')) {
    /** A link to the (admin-only) Settings page - or plain text for everyone else. */
    function ae_settings_link(string $text = 'Settings'): string
    {
        return auth()->user()?->can('admin')
            ? '<a href="'.e(route('settings.edit')).'">'.e($text).'</a>'
            : e($text).' (set by an admin)';
    }
}
