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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('salla_user_id')->unique()->nullable();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile', 15)->unique()->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('domain')->nullable();
            $table->string(column: 'password')->nullable();
            $table->string('plan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
