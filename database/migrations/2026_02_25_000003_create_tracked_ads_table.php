<?php

use App\Enums\ListingTrackingStatus;
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
        Schema::create('tracked_ads', function (Blueprint $table) {
            $table->id();
            $table->string('olx_ad_id')->unique();
            $table->text('listing_url');
            $table->unsignedBigInteger('current_price_minor');
            $table->string('currency_code', 3);
            $table->string('status', 20)->default(ListingTrackingStatus::ACTIVE->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_ads');
    }
};
