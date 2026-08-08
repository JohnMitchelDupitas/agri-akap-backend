<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\SmsBroadcast;
use App\Models\WeatherCache;
use App\Services\WeatherAlertService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(private WeatherAlertService $weatherAlerts)
    {
    }

    /**
     * Today's weather + upcoming 3-day forecast for a barangay (hyper-local).
     * Query: ?barangay=San%20Fabian  (defaults to Soyung / municipal proxy)
     */
    public function current(Request $request): JsonResponse
    {
        $barangay = $request->query('barangay');
        if (! is_string($barangay) || trim($barangay) === '') {
            $barangay = 'Soyung (Poblacion)';
        }

        $today = Carbon::now(WeatherService::TIMEZONE)->toDateString();
        $end = Carbon::now(WeatherService::TIMEZONE)->addDays(3)->toDateString();

        $rows = WeatherCache::query()
            ->where('barangay_name', $barangay)
            ->whereBetween('forecast_date', [$today, $end])
            ->orderBy('forecast_date')
            ->get();

        // Fallback: if selected barangay has no cache yet, use any available barangay's today row set.
        if ($rows->isEmpty()) {
            $fallbackName = WeatherCache::query()
                ->whereDate('forecast_date', $today)
                ->value('barangay_name');
            if ($fallbackName) {
                $barangay = $fallbackName;
                $rows = WeatherCache::query()
                    ->where('barangay_name', $barangay)
                    ->whereBetween('forecast_date', [$today, $end])
                    ->orderBy('forecast_date')
                    ->get();
            }
        }

        $coords = Barangay::query()->where('name', $barangay)->first();

        $todayRow = $rows->first(fn (WeatherCache $row) => $row->forecast_date->toDateString() === $today);
        $forecast = $rows
            ->filter(fn (WeatherCache $row) => $row->forecast_date->toDateString() > $today)
            ->values()
            ->take(3)
            ->map(fn (WeatherCache $row) => $this->transform($row))
            ->all();

        return response()->json([
            'data' => [
                'location' => [
                    'municipality' => 'Echague',
                    'province' => 'Isabela',
                    'barangay' => $barangay,
                    'latitude' => $coords ? (float) $coords->latitude : WeatherService::LATITUDE,
                    'longitude' => $coords ? (float) $coords->longitude : WeatherService::LONGITUDE,
                    'timezone' => WeatherService::TIMEZONE,
                ],
                'today' => $todayRow ? $this->transform($todayRow) : null,
                'forecast' => $forecast,
            ],
        ]);
    }

    /**
     * List barangays available for the weather dropdown.
     */
    public function barangays(): JsonResponse
    {
        $names = Barangay::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $names]);
    }

    /**
     * Heatmap payload: one row per barangay for a forecast date (default: today).
     * Query: ?date=YYYY-MM-DD
     */
    public function heatmap(Request $request): JsonResponse
    {
        $date = $request->query('date');
        if (! is_string($date) || trim($date) === '') {
            $date = Carbon::now(WeatherService::TIMEZONE)->toDateString();
        }

        $rows = WeatherCache::query()
            ->whereDate('forecast_date', $date)
            ->orderBy('barangay_name')
            ->get()
            ->map(fn (WeatherCache $row) => $this->transform($row))
            ->values();

        return response()->json([
            'data' => [
                'forecast_date' => $date,
                'barangays' => $rows,
            ],
        ]);
    }

    /**
     * Suggested hyper-local weather SMS copy for the Admin Broadcast Center.
     */
    public function advisories(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->weatherAlerts->suggestedAdvisories(),
        ]);
    }

    /**
     * Manually dispatch hyper-local weather warnings now (Admin trigger).
     */
    public function sendAdvisory(): JsonResponse
    {
        $result = $this->weatherAlerts->evaluateAndSend(
            force: true,
            triggerType: SmsBroadcast::TRIGGER_MANUAL
        );

        if (! $result['sent']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['skipped'] ?? 'No weather advisory to send.',
                'data' => $result,
            ], 422);
        }

        $mockNote = $result['mocked'] ? ' (mocked in local — no Semaphore charge)' : '';

        return response()->json([
            'status' => 'success',
            'message' => "Weather warnings sent for {$result['alerts_sent']} barangay(s) to {$result['recipient_count']} farmer(s).{$mockNote}",
            'data' => $result,
        ]);
    }

    protected function transform(WeatherCache $row): array
    {
        $code = $row->weather_code;

        return [
            'id' => $row->id,
            'barangay_name' => $row->barangay_name,
            'forecast_date' => $row->forecast_date->toDateString(),
            'temperature_min' => $row->temperature_min !== null ? (float) $row->temperature_min : null,
            'temperature_max' => $row->temperature_max !== null ? (float) $row->temperature_max : null,
            'precipitation_probability' => $row->precipitation_probability,
            'soil_moisture' => $row->soil_moisture !== null ? (float) $row->soil_moisture : null,
            'evapotranspiration' => $row->evapotranspiration !== null ? (float) $row->evapotranspiration : null,
            'soil_moisture_28cm' => $row->soil_moisture_28cm !== null ? (float) $row->soil_moisture_28cm : null,
            'wind_speed_10m' => $row->wind_speed_10m !== null ? (float) $row->wind_speed_10m : null,
            'weather_code' => $code,
            'status' => $this->statusFromCode($code),
        ];
    }

    protected function statusFromCode(?int $code): string
    {
        if ($code === null) {
            return 'Unknown';
        }

        return match (true) {
            $code === 0 => 'Clear',
            $code <= 3 => 'Partly Cloudy',
            $code <= 48 => 'Fog',
            $code <= 57 => 'Drizzle',
            $code <= 67 => 'Rain',
            $code <= 77 => 'Snow',
            $code <= 82 => 'Rain Showers',
            $code <= 86 => 'Snow Showers',
            $code >= 95 => 'Thunderstorm',
            default => 'Overcast',
        };
    }
}
