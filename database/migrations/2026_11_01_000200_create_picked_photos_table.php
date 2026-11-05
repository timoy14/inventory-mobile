<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picked_photos', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picked_photos');
    }
};
