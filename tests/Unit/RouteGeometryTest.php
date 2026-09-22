<?php

use App\Services\RouteGeometry;

test('route deviation uses segments rather than distance to route vertices', function () {
    $geometry = new RouteGeometry;
    expect($geometry->distanceFromRoute(0, 0.005, [[0, 0], [0.01, 0]]))->toBeLessThan(0.01);
    expect($geometry->distanceFromRoute(0.001, 0.005, [[0, 0], [0.01, 0]]))->toBeGreaterThan(110)->toBeLessThan(112);
});

test('route distance handles repeated coordinates and date line crossings', function () {
    $geometry = new RouteGeometry;
    expect($geometry->distanceFromRoute(0, 0, [[0, 0], [0, 0]]))->toBe(0.0);
    expect($geometry->distanceFromRoute(0, 180, [[179.999, 0], [-179.999, 0]]))->toBeLessThan(0.01);
});
