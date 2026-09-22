<?php

namespace App\Services;

class RouteGeometry
{
    /**
     * Local tangent-plane distance to each segment, in meters. Coordinates use GeoJSON longitude/latitude order.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function distanceFromRoute(float $latitude, float $longitude, array $coordinates): float
    {
        $minimum = INF;
        for ($index = 1; $index < count($coordinates); $index++) {
            $start = $coordinates[$index - 1];
            $end = $coordinates[$index];
            $scale = cos(deg2rad($latitude));
            $startX = deg2rad($this->longitudeDelta($start[0] - $longitude)) * 6371000 * $scale;
            $startY = deg2rad($start[1] - $latitude) * 6371000;
            $endX = deg2rad($this->longitudeDelta($end[0] - $longitude)) * 6371000 * $scale;
            $endY = deg2rad($end[1] - $latitude) * 6371000;
            $deltaX = $endX - $startX;
            $deltaY = $endY - $startY;
            $lengthSquared = $deltaX ** 2 + $deltaY ** 2;
            $fraction = $lengthSquared > 0 ? max(0, min(1, -($startX * $deltaX + $startY * $deltaY) / $lengthSquared)) : 0;
            $minimum = min($minimum, hypot($startX + $fraction * $deltaX, $startY + $fraction * $deltaY));
        }

        return $minimum;
    }

    private function longitudeDelta(float $delta): float
    {
        return fmod($delta + 540, 360) - 180;
    }
}
