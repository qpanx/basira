<?php

namespace App\Enums;

enum AlertType: string
{
    case ObstacleFront = 'obstacle_front';
    case ObstacleUpper = 'obstacle_upper';
    case ObstacleLower = 'obstacle_lower';
    case WaterHazard = 'water_hazard';
    case Sos = 'sos';
    case OffRoute = 'off_route';
    case LowBattery = 'low_battery';
    case ArrivedSafely = 'arrived_safely';

    /**
     * @return list<self>
     */
    public static function deviceReportable(): array
    {
        return [
            self::ObstacleFront,
            self::ObstacleUpper,
            self::ObstacleLower,
            self::WaterHazard,
            self::Sos,
            self::LowBattery,
        ];
    }

    public function isObstacle(): bool
    {
        return in_array($this, [
            self::ObstacleFront,
            self::ObstacleUpper,
            self::ObstacleLower,
        ], true);
    }
}
