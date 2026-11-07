<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_haversine_melbourne_to_sydney_is_about_713_km(): void
    {
        $m = Geo::haversine(-37.8136, 144.9631, -33.8688, 151.2093);

        $this->assertEqualsWithDelta(713_000, $m, 2_000);
    }

    public function test_bounding_box_contains_the_whole_radius(): void
    {
        $box = Geo::boundingBox(-37.8136, 144.9631, 10);

        // Points exactly 10 km due N/S/E/W must lie on or inside the box.
        $this->assertEqualsWithDelta(10_000, Geo::haversine(-37.8136, 144.9631, $box['maxLat'], 144.9631), 1);
        $this->assertEqualsWithDelta(10_000, Geo::haversine(-37.8136, 144.9631, -37.8136, $box['maxLng']), 1);
        $this->assertLessThan(-37.8136, $box['minLat']);
        $this->assertGreaterThan(144.9631, $box['maxLng']);
    }
}
