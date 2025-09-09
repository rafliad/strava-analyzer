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
        Schema::table('users', function (Blueprint $table) {
            $table->string('strava_id')->unique()->nullable()->after('id');
            $table->text('strava_token')->nullable();
            $table->text('strava_refresh_token')->nullable();
            $table->timestamp('strava_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'strava_id',
                'strava_token',
                'strava_refresh_token',
                'strava_expires_at',
            ]);
        });
    }
};
