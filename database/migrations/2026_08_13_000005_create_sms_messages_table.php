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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->text('message');
            $table->string('status', 15)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('phone');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
