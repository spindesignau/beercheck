<nav class="mb-4 flex flex-wrap gap-2 text-sm font-semibold">
    @foreach ([
        'admin.dashboard' => 'Dashboard', 'admin.beers.index' => 'Beers', 'admin.prices.index' => 'Posts',
        'admin.venues.index' => 'Venues', 'admin.claims.index' => 'Claims', 'admin.sync.index' => 'Sync', 'admin.points.index' => 'Points',
    ] as $route => $label)
        <a href="{{ route($route) }}" class="rounded-full px-3 py-1 {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-stone-900 text-white' : 'bg-white shadow-sm' }}">{{ $label }}</a>
    @endforeach
</nav>
