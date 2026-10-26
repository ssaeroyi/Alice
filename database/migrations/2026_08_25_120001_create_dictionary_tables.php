<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_entries', function (Blueprint $table) {
            $table->id();
            $table->string('lemma')->index();
            $table->enum('pos', ['noun', 'verb', 'adj', 'adv']);
            $table->unsignedSmallInteger('sense_num');
            $table->text('definition');
            $table->text('example')->nullable();
            $table->unsignedInteger('freq')->default(0);
            $table->timestamps();

            $table->unique(['lemma', 'pos', 'sense_num']);
        });

        Schema::create('dictionary_forms', function (Blueprint $table) {
            $table->id();
            $table->string('form')->index();
            $table->foreignId('entry_id')->constrained('dictionary_entries')->cascadeOnDelete();
            $table->boolean('irregular')->default(false);
            $table->timestamps();

            $table->unique(['form', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_forms');
        Schema::dropIfExists('dictionary_entries');
    }
};
