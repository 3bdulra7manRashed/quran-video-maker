<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reciters', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->string('name_arabic');
            $Blueprint->string('name_english');
            $Blueprint->string('slug')->unique();
            $Blueprint->boolean('is_default')->default(false);
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reciters');
    }
};
