<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planting_logs')) {
            Schema::table('planting_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('planting_logs', 'farm_plot_id')) {
                    $table->foreignUuid('farm_plot_id')->nullable()->after('farmer_id')
                        ->constrained('farm_plots')->nullOnDelete();
                }
                if (! Schema::hasColumn('planting_logs', 'farm_location')) {
                    $table->string('farm_location', 255)->nullable()->after('water_source');
                }
                if (! Schema::hasColumn('planting_logs', 'remarks')) {
                    $table->string('remarks', 500)->nullable()->after('farm_location');
                }
            });
        }

        if (Schema::hasTable('pest_monitoring')) {
            Schema::table('pest_monitoring', function (Blueprint $table) {
                if (! Schema::hasColumn('pest_monitoring', 'farm_plot_id')) {
                    $table->foreignUuid('farm_plot_id')->nullable()->after('farmer_id')
                        ->constrained('farm_plots')->nullOnDelete();
                }
                if (! Schema::hasColumn('pest_monitoring', 'variety')) {
                    $table->string('variety', 128)->nullable()->after('crop');
                }
                if (! Schema::hasColumn('pest_monitoring', 'area_planted')) {
                    $table->decimal('area_planted', 10, 4)->nullable()->after('variety');
                }
                if (! Schema::hasColumn('pest_monitoring', 'days_after_planting')) {
                    $table->unsignedSmallInteger('days_after_planting')->nullable()->after('area_planted');
                }
                if (! Schema::hasColumn('pest_monitoring', 'area_damage_pct')) {
                    $table->decimal('area_damage_pct', 5, 2)->nullable()->after('days_after_planting');
                }
                if (! Schema::hasColumn('pest_monitoring', 'farm_location')) {
                    $table->string('farm_location', 255)->nullable()->after('area_damage_pct');
                }
                if (! Schema::hasColumn('pest_monitoring', 'date_of_inspection')) {
                    $table->date('date_of_inspection')->nullable()->after('farm_location');
                }
            });
        }

        if (Schema::hasTable('damage_assessments')) {
            Schema::table('damage_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('damage_assessments', 'area_planted_ha')) {
                    $table->decimal('area_planted_ha', 10, 4)->nullable()->after('area_destroyed_ha');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planting_logs')) {
            Schema::table('planting_logs', function (Blueprint $table) {
                if (Schema::hasColumn('planting_logs', 'farm_plot_id')) {
                    $table->dropConstrainedForeignId('farm_plot_id');
                }
                foreach (['farm_location', 'remarks'] as $col) {
                    if (Schema::hasColumn('planting_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('pest_monitoring')) {
            Schema::table('pest_monitoring', function (Blueprint $table) {
                if (Schema::hasColumn('pest_monitoring', 'farm_plot_id')) {
                    $table->dropConstrainedForeignId('farm_plot_id');
                }
                foreach ([
                    'variety', 'area_planted', 'days_after_planting',
                    'area_damage_pct', 'farm_location', 'date_of_inspection',
                ] as $col) {
                    if (Schema::hasColumn('pest_monitoring', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('damage_assessments') && Schema::hasColumn('damage_assessments', 'area_planted_ha')) {
            Schema::table('damage_assessments', function (Blueprint $table) {
                $table->dropColumn('area_planted_ha');
            });
        }
    }
};
