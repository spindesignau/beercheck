<?php

namespace Database\Seeders;

use App\Enums\BeerStatus;
use App\Models\BeerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Common Australian tap beers (approved). Idempotent. */
class BeerSeeder extends Seeder
{
    public function run(): void
    {
        $types = BeerType::pluck('id', 'name');
        $beers = [
            ['Carlton', 'Draught', 'Lager'], ['Carlton', 'Dry', 'Lager'], ['Victoria', 'Bitter', 'Lager'],
            ['Great Northern', 'Original', 'Lager'], ['Great Northern', 'Super Crisp', 'Mid-strength'],
            ['Tooheys', 'New', 'Lager'], ['Tooheys', 'Old', 'Ale'], ['XXXX', 'Gold', 'Mid-strength'],
            ['Coopers', 'Pale Ale', 'Pale Ale'], ['Coopers', 'Sparkling Ale', 'Ale'], ['Coopers', 'Best Extra Stout', 'Stout'],
            ['Furphy', 'Refreshing Ale', 'Ale'], ['Stone & Wood', 'Pacific Ale', 'Pale Ale'],
            ['Balter', 'XPA', 'Pale Ale'], ['Mountain Goat', 'Steam Ale', 'Ale'], ['Little Creatures', 'Pale Ale', 'Pale Ale'],
            ['James Squire', 'One Fifty Lashes', 'Pale Ale'], ['Hahn', 'Super Dry', 'Lager'], ['Pure Blonde', 'Lager', 'Lager'],
            ['Asahi', 'Super Dry', 'Lager'], ['Peroni', 'Nastro Azzurro', 'Lager'], ['Corona', 'Extra', 'Lager'],
            ['Guinness', 'Draught', 'Stout'], ['Hoegaarden', 'White', 'Wheat'], ['Stomping Ground', 'Gipps St Pale', 'Pale Ale'],
            ['Young Henrys', 'Newtowner', 'Pale Ale'], ['Bulmers', 'Original Apple', 'Cider'], ['Somersby', 'Apple Cider', 'Cider'],
        ];

        $now = now();
        DB::table('beers')->upsert(array_map(fn ($b) => [
            'brand' => $b[0], 'name' => $b[1], 'type_id' => $types[$b[2]] ?? $types['Other'],
            'status' => BeerStatus::Approved->value, 'created_at' => $now, 'updated_at' => $now,
        ], $beers), ['brand', 'name'], ['type_id', 'updated_at']);
    }
}
