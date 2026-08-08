<?php

namespace App\Console\Commands;

use App\Services\WeatherAlertService;
use Illuminate\Console\Command;

class SendWeatherAlerts extends Command
{
    protected $signature = 'weather:alert {--force : Send even if an automated alert already went out today for that barangay}';

    protected $description = 'Evaluate tomorrow\'s hyper-local weather cache and SMS farmers per affected barangay';

    public function handle(WeatherAlertService $alerts): int
    {
        $this->info('Evaluating tomorrow\'s barangay forecasts for weather SMS advisories…');

        $result = $alerts->evaluateAndSend(force: (bool) $this->option('force'));

        if (! empty($result['details'])) {
            foreach ($result['details'] as $detail) {
                $brgy = $detail['barangay'] ?? '?';
                if (! empty($detail['skipped'])) {
                    $this->warn("  [{$brgy}] {$detail['skipped']}");
                } elseif (! empty($detail['success'])) {
                    $mock = ! empty($detail['mocked']) ? ' (mocked)' : '';
                    $this->info("  [{$brgy}] {$detail['alert_type']} → {$detail['recipient_count']} farmer(s){$mock}");
                } else {
                    $this->error("  [{$brgy}] send failed");
                }
            }
        }

        if (! $result['sent']) {
            $this->warn($result['skipped'] ?? 'No advisories dispatched.');

            return self::SUCCESS;
        }

        $this->info("Done: {$result['alerts_sent']} barangay advisory(ies), {$result['recipient_count']} total recipient(s).");

        return self::SUCCESS;
    }
}
