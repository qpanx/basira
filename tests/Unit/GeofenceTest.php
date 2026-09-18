<?php

use App\Services\HaversineDistance;

test('haversine distance is accurate enough for safe-zone boundaries', function () {
    $distance = new HaversineDistance;

    expect($distance->meters(30.0444, 31.2357, 30.0444, 31.2357))->toBe(0.0)
        ->and($distance->meters(30.0444, 31.2357, 30.0453, 31.2357))->toBeGreaterThan(90)
        ->and($distance->meters(30.0444, 31.2357, 30.0453, 31.2357))->toBeLessThan(110);
});
