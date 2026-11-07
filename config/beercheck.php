<?php

return [

    /*
    | Geo-discovery defaults. Melbourne CBD is used when the browser denies
    | or cannot provide a location.
    */
    'geo' => [
        'default_lat' => (float) env('BEERCHECK_DEFAULT_LAT', -37.8136),
        'default_lng' => (float) env('BEERCHECK_DEFAULT_LNG', 144.9631),
        'default_radius_km' => 5,
        'min_radius_km' => 1,
        'max_radius_km' => 50,
        'max_results' => 100,
    ],

    'prices' => [
        // Price posts older than this are ignored when calculating "current" prices.
        'freshness_days' => (int) env('BEERCHECK_PRICE_FRESHNESS_DAYS', 180),
        'min' => 1.00,
        'max' => 200.00,
        // Filter slider bounds (AUD).
        'filter_min' => 5,
        'filter_max' => 20,
        'top_n' => 3,
    ],

    // Server-side points table. Never trust a client-supplied value.
    'points' => [
        'price_posted' => 5,
        'photo_uploaded' => 5,
        'review_written' => 10,
        'beer_approved' => 15,
        // Repeat posts for the same venue/beer/size by the same user inside this
        // window still save, but earn no points (anti-farming).
        'repeat_cooldown_hours' => 24,
        'review_min_chars' => 20,
    ],

    'images' => [
        'driver' => env('IMAGE_DRIVER', 'gd'), // gd | imagick
        'max_width' => 1600,
        'max_height' => 2400,
        'jpeg_quality' => 70, // ~0.7
        'max_upload_kb' => 12288,
        'directory' => 'photos',
    ],

    /*
    | Staging demo mode: shows a "demo data" banner, keeps pages out of search
    | engines, and lets `php artisan app:demo-content` seed fictional venues.
    */
    'demo' => [
        'enabled' => (bool) env('BEERCHECK_DEMO', false),
        'email_domain' => 'demo.beercheck.test',
        'external_prefix' => 'demo-',
    ],

    'registration' => [
        'minimum_age' => 18,
    ],

    /*
    | Liquor licence sync pipeline.
    */
    'sync' => [
        // If a feed returns fewer than this fraction of the currently active
        // venues for that source, skip deactivation (protects against truncated feeds).
        'min_feed_ratio' => 0.5,
        'http_timeout' => 120,

        'sources' => [
            'nsw_liquor' => [
                'enabled' => env('SYNC_NSW_ENABLED', true),
                // Liquor & Gaming NSW "Licensed premises data" monthly export (XLSX or CSV).
                // The file name changes monthly; set the current URL in .env.
                'url' => env('SYNC_NSW_URL'),
                'format' => env('SYNC_NSW_FORMAT', 'xlsx'),
                'include_licence_types' => [
                    'hotel',
                    'small bar',
                    'general bar',
                ],
                'exclude_licence_types' => [
                    'packaged', 'producer', 'wholesaler', 'club', 'restaurant', 'on-premises', 'limited',
                ],
                'active_statuses' => ['current'],
            ],
            'vic_liquor' => [
                'enabled' => env('SYNC_VIC_ENABLED', true),
                // Discover Data Victoria "Victorian liquor licences by location" CSV resource URL.
                'url' => env('SYNC_VIC_URL'),
                'format' => env('SYNC_VIC_FORMAT', 'csv'),
                'include_licence_types' => [
                    'general licence',
                    'late night (general)',
                    'on-premises licence',
                    'late night (on-premises)',
                ],
                'exclude_licence_types' => [
                    'packaged', 'producer', 'wholesale', 'club', 'restaurant', 'cafe', 'pre-retail', 'limited', 'byo', 'remote seller',
                ],
                'active_statuses' => [], // VIC feed only lists current licences
            ],
        ],
    ],

    'geocoding' => [
        'enabled' => env('GEOCODING_ENABLED', true),
        // OpenStreetMap Nominatim: max 1 request/second, identifying User-Agent required.
        'endpoint' => env('GEOCODING_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'Beercheck venue sync'),
        'throttle_ms' => 1100,
        'max_per_run' => (int) env('GEOCODING_MAX_PER_RUN', 500),
        // Cache misses ("not found") are retried after this many days.
        'negative_ttl_days' => 30,
    ],
];
