<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherHourly extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'tbl_weather_hourly';

    protected $fillable = [
        'barangay_name',
        'forecast_datetime',
        'temperature',
        'precipitation_probability',
        'wind_speed',
        'weather_code',
    ];

    protected $casts = [
        'forecast_datetime' => 'datetime',
        'temperature' => 'decimal:2',
        'precipitation_probability' => 'integer',
        'wind_speed' => 'decimal:2',
        'weather_code' => 'integer',
    ];
}
