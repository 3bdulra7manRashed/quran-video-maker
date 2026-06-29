<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->string('name');
            $Blueprint->unsignedSmallInteger('width');
            $Blueprint->unsignedSmallInteger('height');
            $Blueprint->json('config');
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
