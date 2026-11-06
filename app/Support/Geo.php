<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Small geo helper that hides the difference between MariaDB (native POINT
 * column + SPATIAL index + ST_Distance_Sphere) and other drivers (SQLite in
 * tests) which fall back to a lat/lng bounding box + Haversine formula.
 */
final class Geo
{
    public const EARTH_RADIUS_M = 6371000;

    public static function supportsSpatial(?string $connection = null): bool
    {
        return in_array(DB::connection($connection)->getDriverName(), ['mariadb', 'mysql'], true);
    }

    /** POINT(x, y) expression — MariaDB uses x = longitude, y = latitude. */
    public static function pointExpression(float $lat, float $lng): Expression
    {
        return DB::raw(sprintf('POINT(%.7F, %.7F)', $lng, $lat));
    }

    /**
     * Bounding box (degrees) that fully contains a circle of $radiusKm.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = rad2deg($radiusKm * 1000 / self::EARTH_RADIUS_M);
        $lngDelta = rad2deg($radiusKm * 1000 / self::EARTH_RADIUS_M / max(cos(deg2rad($lat)), 0.000001));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }

    /**
     * SQL fragment (with bindings) returning distance in metres from ($lat, $lng)
     * to the venues table row.
     *
     * @return array{0: string, 1: list<float>}
     */
    public static function distanceSql(float $lat, float $lng, string $table = 'venues'): array
    {
        if (self::supportsSpatial()) {
            return ["ST_Distance_Sphere({$table}.location, POINT(?, ?))", [$lng, $lat]];
        }

        // Haversine. SQLite >= 3.35 with math functions (default in PHP builds) supports these.
        $sql = '(2 * '.self::EARTH_RADIUS_M." * ASIN(SQRT(
            POWER(SIN(RADIANS({$table}.latitude - ?) / 2), 2) +
            COS(RADIANS(?)) * COS(RADIANS({$table}.latitude)) *
            POWER(SIN(RADIANS({$table}.longitude - ?) / 2), 2)
        )))";

        return [$sql, [$lat, $lat, $lng]];
    }

    /** Pure-PHP Haversine, metres. */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1, sqrt($a)));
    }
}
