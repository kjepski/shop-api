<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\Request;

/**
 * For index requests that declare their filter parameters in a FILTERS constant.
 */
trait DetectsFilterInput
{
    /**
     * Whether any filter parameter is present, without parsing its value: the search rate
     * limiters call this before validation, when the input may still be invalid.
     */
    public static function hasFilterInput(Request $request): bool
    {
        foreach (static::FILTERS as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
