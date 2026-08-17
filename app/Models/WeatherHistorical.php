<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherHistorical extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'tbl_weather_historical';

    protected $fillable = [
        'barangay_name',
        'date',
        'precipitation_sum',
        'temperature_max',
        'et0_fao_evapotranspiration',
    ];

    protected $casts = [
        'date' => 'date',
        'precipitation_sum' => 'decimal:2',
        'temperature_max' => 'decimal:2',
        'et0_fao_evapotranspiration' => 'decimal:3',
    ];
}
