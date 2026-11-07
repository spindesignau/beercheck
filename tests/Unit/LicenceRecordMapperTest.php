<?php

namespace Tests\Unit;

use App\Enums\VenueSource;
use App\Services\Sync\LicenceRecordMapper;
use Tests\TestCase;

class LicenceRecordMapperTest extends TestCase
{
    public function test_nsw_rows_map_and_filter_to_pubs_and_bars(): void
    {
        $mapper = new LicenceRecordMapper(VenueSource::NswLiquor);
        $row = fn (string $type, string $status = 'Current') => [
            'licence_number' => 'LIQH400'.rand(100, 999), 'licence_name' => 'THE LORD NELSON BREWERY HOTEL',
            'licence_type' => $type, 'status' => $status, 'address' => '19 KENT ST', 'suburb' => 'MILLERS POINT',
            'postcode' => '2000', 'latitude' => '-33.8573', 'longitude' => '151.2040',
        ];

        $hotel = $mapper->map($row('Liquor - hotel licence'));
        $this->assertSame('The Lord Nelson Brewery Hotel', $hotel['name']);
        $this->assertSame('NSW', $hotel['state']);
        $this->assertTrue($mapper->accepts($hotel));
        $this->assertTrue($mapper->accepts($mapper->map($row('Liquor - small bar licence'))));

        $this->assertFalse($mapper->accepts($mapper->map($row('Liquor - packaged liquor licence'))));
        $this->assertFalse($mapper->accepts($mapper->map($row('Liquor - club licence'))));
        $this->assertFalse($mapper->accepts($mapper->map($row('Liquor - on-premises licence'))));
        $this->assertFalse($mapper->accepts($mapper->map($row('Liquor - hotel licence', 'Cancelled'))));
    }

    public function test_out_of_australia_coordinates_are_dropped_for_geocoding(): void
    {
        $mapped = (new LicenceRecordMapper(VenueSource::VicLiquor))->map([
            'licence_num' => '1', 'trading_as' => 'X', 'category' => 'General licence', 'latitude' => '0', 'longitude' => '0',
        ]);

        $this->assertNull($mapped['latitude']);
        $this->assertNull($mapped['longitude']);
    }

    public function test_rows_without_licence_number_or_name_are_rejected(): void
    {
        $this->assertNull((new LicenceRecordMapper(VenueSource::VicLiquor))->map(['trading_as' => 'No Number']));
    }
}
