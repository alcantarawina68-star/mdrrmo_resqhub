<?php

use App\Enums\IncidentClassification;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDRRMO revisions:
 *
 * - Report statuses `new` and `resolved` are retired. No records used either
 *   value, so the column is narrowed. `closed` remains the terminal status and
 *   keeps populating `resolved_at`.
 * - `typhoon_flood` is split into the separate `typhoon` and `flood` types, and
 *   `other` is renamed to `others`. The legacy `typhoon_flood` value stays in
 *   the column so the existing records remain readable, but it can no longer be
 *   selected or submitted.
 * - The `priority` column keeps its name and is repointed at the new
 *   classification values, migrating historical rows as
 *   urgent -> black, high -> red, medium -> yellow, low -> green.
 */
return new class extends Migration
{
    /**
     * Historical priority values mapped onto the new classifications.
     *
     * @var array<string, string>
     */
    private const CLASSIFICATION_MAPPING = [
        'urgent' => IncidentClassification::Black->value,
        'high' => IncidentClassification::Red->value,
        'medium' => IncidentClassification::Yellow->value,
        'low' => IncidentClassification::Green->value,
    ];

    /**
     * The incident type column as it was before this revision.
     *
     * @var array<int, string>
     */
    private const PRE_REVISION_TYPES = [
        'typhoon_flood', 'earthquake', 'landslide', 'vehicular_accident',
        'fire', 'drowning', 'hazmat', 'ems', 'patient_transport', 'other',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Widen `priority` to hold both the old and the new values first, so the
        // mapping below is not rejected by MySQL's ENUM validation.
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('priority', [
                ...array_keys(self::CLASSIFICATION_MAPPING),
                ...IncidentClassification::values(),
            ])->default('medium')->change();
        });

        foreach (self::CLASSIFICATION_MAPPING as $from => $to) {
            DB::table('incidents')->where('priority', $from)->update(['priority' => $to]);
        }

        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('priority', IncidentClassification::values())
                ->default(IncidentClassification::Yellow->value)
                ->change();

            $table->enum('status', IncidentStatus::values())
                ->default(IncidentStatus::UnderVerification->value)
                ->change();

            $table->enum('incident_type', IncidentType::allValues())->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // `others` was renamed from `other`, and the split `typhoon` and `flood`
        // types collapse back into the combined `typhoon_flood` value that the
        // pre-revision column could express.
        DB::table('incidents')->where('incident_type', 'others')->update(['incident_type' => 'other']);

        DB::table('incidents')
            ->whereIn('incident_type', [IncidentType::Typhoon->value, IncidentType::Flood->value])
            ->update(['incident_type' => IncidentType::TyphoonFlood->value]);

        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('incident_type', self::PRE_REVISION_TYPES)->change();
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', [
                'new',
                'under_verification',
                'verified',
                'ongoing',
                'resolved',
                'closed',
                'rejected',
            ])->default('under_verification')->change();
        });

        // Widen to hold both sets of values first, mirroring the ordering in
        // `up()`, so the mapping below is not rejected by MySQL's ENUM
        // validation.
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('priority', [
                'low', 'medium', 'high', 'urgent',
                ...IncidentClassification::values(),
            ])->default('medium')->change();
        });

        // New classifications have no pre-revision equivalent, so the affected
        // rows fall back to the former default rather than being guessed at.
        DB::table('incidents')
            ->whereIn('priority', IncidentClassification::values())
            ->update(['priority' => 'medium']);

        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->change();
        });
    }
};
