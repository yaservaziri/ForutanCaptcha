<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fcaptchas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image', 255);
            $table->string('category', 100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcaptchas');
    }
};
