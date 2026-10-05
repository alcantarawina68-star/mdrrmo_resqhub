<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->foreignId('evidence_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('content');
        });

        // The schema builder has no blob type, and longText alone would cap
        // uploads at the 5 MB validation limit with no headroom. MySQL is the
        // only driver that needs the real binary column; every other driver
        // keeps the portable longText and stores raw bytes as a string.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE evidence_files MODIFY content LONGBLOB NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
