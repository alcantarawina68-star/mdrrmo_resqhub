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
        // Narrowing the enum below truncates any surviving 'barangay_official'
        // value, which MySQL refuses outright under strict mode. Existing
        // accounts have to land on a surviving role first, so the migration is
        // safe to run against a populated database rather than only a fresh one.
        DB::table('users')
            ->where('role', 'barangay_official')
            ->update(['role' => 'community_user']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'encoder', 'responder', 'community_user'])
                ->default('community_user')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'encoder', 'barangay_official', 'responder', 'community_user'])
                ->default('community_user')
                ->change();
        });
    }
};
