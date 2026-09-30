<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classification and priority level were retired from ResQHub, so the
     * `incidents.priority` column is dropped rather than left orphaned.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }

    /**
     * Restores the column in the shape it had immediately before this
     * migration, so rolling back lands on the previous migration's schema.
     *
     * Per-row values are not recoverable once the column is dropped; every
     * incident comes back on the column default.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('priority', ['red', 'green', 'yellow', 'black'])
                ->default('yellow')
                ->after('status');
        });
    }
};
