<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->decimal('total_farm_area_ha', 10, 4)->default(0)->after('livelihood_detail');
        });

        // Legacy-safe backfill: oldest active plot parcel/size (avoids baking in duplicate overflow).
        $farmers = DB::table('farmers')->select('id')->get();
        foreach ($farmers as $farmer) {
            $oldest = DB::table('farm_plots')
                ->where('farmer_id', $farmer->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first(['total_parcel_area_ha', 'size_ha']);

            $quota = 0.0;
            if ($oldest) {
                $parcel = (float) ($oldest->total_parcel_area_ha ?? 0);
                $size = (float) ($oldest->size_ha ?? 0);
                $quota = $parcel > 0 ? $parcel : $size;
            }

            DB::table('farmers')->where('id', $farmer->id)->update([
                'total_farm_area_ha' => $quota,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('total_farm_area_ha');
        });
    }
};
