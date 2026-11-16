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
        // On-premises (restaurant-style) licences only count when the name says bar/pub/etc.
        $this->assertTrue($mapper->accepts($mapper->map($row('Liquor - on-premises licence'))), 'name contains Brewery/Hotel');
        $restaurant = $mapper->map(['licence_number' => 'LIQO9', 'licence_name' => 'Osteria Ilaria', 'licence_type' => 'Liquor - on-premises licence', 'status' => 'Current']);
        $this->assertFalse($mapper->accepts($restaurant));
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

    public function test_nsw_bar_business_types_and_trading_status(): void
    {
        $mapper = new LicenceRecordMapper(VenueSource::NswLiquor);
        $row = fn (string $type, string $business = '', string $trading = 'Trading') => $mapper->map([
            'licence_number' => 'LIQ1', 'licence_name' => 'X', 'licence_type' => $type, 'status' => 'Current',
            'trading_status' => $trading, 'business_type' => $business, 'latitude' => '-33.86', 'longitude' => '151.2',
        ]);

        $this->assertTrue($mapper->accepts($row('Liquor - hotel licence', 'General bar')));
        $this->assertTrue($mapper->accepts($row('Liquor - on-premises licence', 'Bar,Sport facility')));
        $this->assertTrue($mapper->accepts($row('Liquor - on-premises licence', 'Wine bar')));
        $this->assertFalse($mapper->accepts($row('Liquor - on-premises licence', 'Restaurant')));
        $this->assertFalse($mapper->accepts($row('Liquor - on-premises licence', 'Karaoke venue,Restaurant')));
        $this->assertFalse($mapper->accepts($row('Liquor - pop-up licence', 'Bar')));
        $this->assertFalse($mapper->accepts($row('Liquor - club licence', 'Bar')));
        $this->assertFalse($mapper->accepts($row('Liquor - hotel licence', 'Full hotel', 'Temporarily Ceased Trading')));
    }

    public function test_bars_on_restaurant_style_licences_are_kept_by_name(): void
    {
        $nsw = new LicenceRecordMapper(VenueSource::NswLiquor);
        $onPrem = fn (string $name) => $nsw->accepts($nsw->map([
            'licence_number' => 'LIQO1', 'licence_name' => $name, 'licence_type' => 'Liquor - on-premises licence',
            'status' => 'Current', 'trading_status' => 'Trading', 'business_type' => 'Catering service,Restaurant',
        ]));
        $this->assertTrue($onPrem('Bar Conte Sydney'));
        $this->assertTrue($onPrem('Barrel Below'));
        $this->assertTrue($onPrem('Grafton Brewing Co'));
        $this->assertFalse($onPrem('Nando\'s Clarence Street'));
        $this->assertFalse($onPrem('Goodies Bar Service'));
        $this->assertFalse($onPrem('Tinys Sandwich Bar'));

        $vic = new LicenceRecordMapper(VenueSource::VicLiquor);
        $resto = fn (string $name) => $vic->accepts($vic->map([
            'licence_num' => '1', 'trading_as' => $name, 'category' => 'Restaurant and cafe Licence',
        ]));
        $this->assertTrue($resto('THE PIG & WHISTLE TAVERN MAIN RIDGE'));
        $this->assertTrue($resto('LONA PINTXOS BAR'));
        $this->assertFalse($resto('PENINSULA MOTOR INN'));
        $this->assertFalse($resto('OSTERIA ILARIA'));
    }
}
