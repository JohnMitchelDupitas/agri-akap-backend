<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;
use Throwable;

class FetchWeatherCommand extends Command
{
    protected $signature = 'weather:fetch';

    protected $description = 'Bulk-fetch Open-Meteo hyper-local forecasts for all Echague barangays';

    public function handle(WeatherService $weatherService): int
    {
        $this->info('Bulk-fetching Open-Meteo for all active barangays (chunks of '.WeatherService::CHUNK_SIZE.')…');

        try {
            $result = $weatherService->fetchAndCache();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$result['synced']} forecast row(s) across {$result['barangays']} barangay(s) in {$result['chunks']} chunk(s).");
        foreach ($result['dates'] as $date) {
            $this->line("  • {$date}");
        }

        return self::SUCCESS;
    }
}
