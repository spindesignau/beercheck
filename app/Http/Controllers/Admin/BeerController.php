<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BeerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBeerRequest;
use App\Models\Beer;
use App\Models\BeerType;
use App\Services\BeerModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BeerController extends Controller
{
    public function __construct(private readonly BeerModerationService $moderation) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(BeerStatus::class)]])['status'] ?? 'pending';

        return view('admin.beers', [
            'status' => $status,
            'beers' => Beer::query()->where('status', $status)
                ->with(['type', 'submitter:id,username'])
                ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')))
                ->latest()->paginate(30)->withQueryString(),
            'types' => BeerType::ordered(),
        ]);
    }

    /** Admin-created beers are approved immediately (no points). */
    public function store(StoreBeerRequest $request): RedirectResponse
    {
        $beer = new Beer($request->validated());
        $beer->status = BeerStatus::Approved;
        $beer->reviewed_by = $request->user()->id;
        $beer->reviewed_at = now();
        $beer->save();

        return back()->with('status', "Added {$beer->displayName()}.");
    }

    public function approve(Request $request, Beer $beer): RedirectResponse
    {
        $this->moderation->approve($beer, $request->user());

        return back()->with('status', "Approved {$beer->displayName()}.");
    }

    public function reject(Request $request, Beer $beer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->moderation->reject($beer, $request->user(), $data['reason'] ?? null);

        return back()->with('status', "Rejected {$beer->displayName()}.");
    }
}
