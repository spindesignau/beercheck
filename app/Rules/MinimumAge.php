<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/** Server-side age gate: the date of birth must be at least N full years ago (Australia/Sydney "today"). */
class MinimumAge implements ValidationRule
{
    public function __construct(private readonly int $years = 18) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $dob = Carbon::createFromFormat('!Y-m-d', (string) $value, 'Australia/Sydney');
        } catch (Throwable) {
            $dob = false;
        }

        if (! $dob || $dob->isFuture()) {
            $fail('Please enter a valid date of birth.');

            return;
        }

        $latestAllowed = Carbon::today('Australia/Sydney')->subYears($this->years);

        if ($dob->gt($latestAllowed)) {
            $fail("You must be {$this->years} or older to use Beercheck.");
        }

        if ($dob->lt(Carbon::today('Australia/Sydney')->subYears(120))) {
            $fail('Please enter a valid date of birth.');
        }
    }
}
