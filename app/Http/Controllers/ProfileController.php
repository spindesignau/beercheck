<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'isOwner' => true,
            'events' => $user->pointsEvents()->latest()->limit(20)->get(),
            'prices' => $user->beerPrices()->with(['beer:id,brand,name', 'venue:id,name,suburb'])->latest()->paginate(10),
            'pendingBeers' => $user->submittedBeers()->latest()->limit(10)->get(),
            'claims' => $user->venueClaims()->with('venue:id,name,suburb')->latest()->get(),
        ]);
    }

    public function public(User $user): View
    {
        return view('profile.show', [
            'user' => $user,
            'isOwner' => false,
            'events' => collect(),
            'prices' => $user->beerPrices()->visible()->with(['beer:id,brand,name', 'venue:id,name,suburb'])->latest()->paginate(10),
            'pendingBeers' => collect(),
            'claims' => collect(),
        ]);
    }
}
