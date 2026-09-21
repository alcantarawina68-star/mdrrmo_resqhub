<?php

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
        Schema::table('evidence', function (Blueprint $table) {
            $table->string('ai_label')->nullable()->after('file_size');
            $table->boolean('ai_is_generated')->nullable()->after('ai_label');
            $table->double('ai_score', 5, 4)->nullable()->after('ai_is_generated');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_score');
            $table->string('ai_error')->nullable()->after('ai_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropColumn([
                'ai_label',
                'ai_is_generated',
                'ai_score',
                'ai_analyzed_at',
                'ai_error',
            ]);
        });
    }
};
