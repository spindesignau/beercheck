<?php

namespace Tests\Unit;

use App\Rules\MinimumAge;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MinimumAgeTest extends TestCase
{
    private function fails(string $dob): bool
    {
        $failed = false;
        (new MinimumAge(18))->validate('date_of_birth', $dob, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_boundaries(): void
    {
        $today = Carbon::today('Australia/Sydney');

        $this->assertFalse($this->fails($today->copy()->subYears(18)->toDateString()), '18th birthday today passes');
        $this->assertTrue($this->fails($today->copy()->subYears(18)->addDay()->toDateString()), 'turns 18 tomorrow fails');
        $this->assertTrue($this->fails($today->copy()->addDay()->toDateString()), 'future date fails');
        $this->assertTrue($this->fails('not-a-date'));
        $this->assertTrue($this->fails('1890-01-01'));
    }
}
