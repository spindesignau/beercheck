<?php

namespace Database\Seeders;

use App\Enums\BeerStatus;
use App\Models\BeerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Common beers on Australian taps (approved). Idempotent: safe on every deploy.
 * ABV is only filled in where well established; admins can edit the rest.
 */
class BeerSeeder extends Seeder
{
    /** [brand, name, type, abv|null] */
    public const BEERS = [
        // Mainstream lagers
        ['Carlton', 'Draught', 'Lager', 4.6], ['Carlton', 'Dry', 'Lager', 4.5], ['Carlton', 'Zero', 'Other', 0.0],
        ['Victoria', 'Bitter', 'Lager', 4.9], ['Melbourne', 'Bitter', 'Lager', 4.6],
        ['Great Northern', 'Original', 'Lager', 4.2], ['Great Northern', 'Super Crisp', 'Mid-strength', 3.5],
        ['Tooheys', 'New', 'Lager', 4.6], ['Tooheys', 'Old', 'Ale', null], ['Tooheys', 'Extra Dry', 'Lager', null],
        ['XXXX', 'Gold', 'Mid-strength', 3.5], ['XXXX', 'Bitter', 'Lager', null],
        ['Hahn', 'Super Dry', 'Lager', 4.6], ['Pure Blonde', 'Lager', 'Lager', 4.2],
        ['Cascade', 'Premium Lager', 'Lager', null], ['Boag\'s', 'Draught', 'Lager', null],
        ['Coopers', 'Pale Ale', 'Pale Ale', 4.5],
        ['Coopers', 'Sparkling Ale', 'Ale', 5.8], ['Coopers', 'Best Extra Stout', 'Stout', 6.3], ['Coopers', 'Mild Ale', 'Mid-strength', 3.5],
        // Imports
        ['Asahi', 'Super Dry', 'Lager', 5.0], ['Peroni', 'Nastro Azzurro', 'Lager', 5.1], ['Corona', 'Extra', 'Lager', 4.5],
        ['Heineken', 'Lager', 'Lager', 5.0], ['Stella Artois', 'Lager', 'Lager', null], ['Kirin', 'Ichiban', 'Lager', 5.0],
        ['Sapporo', 'Premium', 'Lager', null], ['Guinness', 'Draught', 'Stout', 4.2], ['Kilkenny', 'Irish Red Ale', 'Ale', null],
        ['Hoegaarden', 'White', 'Wheat', 4.9],
        // Craft & independents
        ['Stone & Wood', 'Pacific Ale', 'Pale Ale', 4.4], ['Balter', 'XPA', 'Pale Ale', 5.0],
        ['Mountain Goat', 'Steam Ale', 'Ale', 4.5], ['Mountain Goat', 'Summer Ale', 'Ale', null],
        ['Little Creatures', 'Pale Ale', 'Pale Ale', 5.2], ['Little Creatures', 'Rogers\'', 'Mid-strength', 3.8],
        ['James Squire', 'One Fifty Lashes', 'Pale Ale', 4.2], ['Furphy', 'Refreshing Ale', 'Ale', 4.4],
        ['Young Henrys', 'Newtowner', 'Pale Ale', 4.8], ['Young Henrys', 'Natural Lager', 'Lager', null],
        ['Feral', 'Hop Hog', 'IPA', 5.8], ['Pirate Life', 'Pale Ale', 'Pale Ale', null], ['Bentspoke', 'Crankshaft', 'IPA', 5.8],
        ['4 Pines', 'Pacific Ale', 'Pale Ale', null], ['Fat Yak', 'Pale Ale', 'Pale Ale', 4.7],
        ['Stomping Ground', 'Gipps St Pale Ale', 'Pale Ale', null], ['Lord Nelson', 'Three Sheets', 'Pale Ale', 4.9],
        ['Colonial', 'Small Ale', 'Mid-strength', null], ['Heaps Normal', 'Quiet XPA', 'Other', 0.5],
        ['Matso\'s', 'Mango Beer', 'Other', 4.5], ['Hawke\'s', 'Lager', 'Lager', null],
        // Ciders
        ['Bulmers', 'Original Apple', 'Cider', 4.7], ['Somersby', 'Apple Cider', 'Cider', 4.5], ['Strongbow', 'Original', 'Cider', null],
    ];

    public function run(): void
    {
        $types = BeerType::pluck('id', 'name');
        $now = now();

        DB::table('beers')->upsert(array_map(fn ($b) => [
            'brand' => $b[0], 'name' => $b[1], 'type_id' => $types[$b[2]] ?? $types['Other'], 'abv' => $b[3],
            'status' => BeerStatus::Approved->value, 'created_at' => $now, 'updated_at' => $now,
        ], self::BEERS), ['brand', 'name'], ['updated_at']);
    }
}
