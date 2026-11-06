<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            // Client-side constraint for <input type="date" max="...">; server re-validates.
            'maxDob' => now('Australia/Sydney')->subYears(config('beercheck.registration.minimum_age'))->toDateString(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // Role is never taken from the request: everyone starts as a punter.
        $user = User::create($request->safe()->only(['name', 'username', 'email', 'mobile', 'date_of_birth', 'password']));

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', "Welcome to Beercheck, {$user->username}! Post a price to start earning points.");
    }
}
