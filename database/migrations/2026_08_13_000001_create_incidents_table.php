<?php

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('incident_type', IncidentType::values());
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label', 255)->nullable();
            $table->enum('source', IncidentSource::values());
            $table->string('caller_name', 120)->nullable();
            $table->string('caller_contact', 20)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', IncidentStatus::values())->default(IncidentStatus::UnderVerification->value);
            $table->enum('priority', Priority::values())->default(Priority::Medium->value);
            $table->string('assigned_unit', 120)->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('incident_type');
            $table->index('status');
            $table->index('source');
            $table->index('reported_at');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
