<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pocos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('office_id')->nullable()->unique();
            $table->string('source');
            $table->foreignId('map_id')->nullable()->constrained('maps')->cascadeOnDelete();
            $table->foreignId('solarsystem_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('planet_id')->nullable();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->foreign('corporation_id')->references('id')->on('corporations')->nullOnDelete();
            $table->string('owner_alias')->nullable();
            $table->unsignedTinyInteger('reinforce_exit_start')->nullable();
            $table->unsignedTinyInteger('reinforce_exit_end')->nullable();
            $table->dateTime('reinforced_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['map_id', 'solarsystem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pocos');
    }
};
