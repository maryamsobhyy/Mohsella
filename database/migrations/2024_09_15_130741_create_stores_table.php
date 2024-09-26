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
        Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('salla_store_id')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('entity')->nullable();
                $table->string('status')->nullable();
                $table->text('description')->nullable();
                $table->string('domain')->nullable();
                $table->string('type')->nullable();
                $table->string('plan')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
