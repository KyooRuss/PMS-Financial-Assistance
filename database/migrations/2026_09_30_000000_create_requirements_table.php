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
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->string('project_type');
            $table->string('stage');
            $table->unsignedSmallInteger('number');
            $table->text('description');
            $table->text('details')->nullable(); // sub-items, e.g. item 13 a–c
            $table->boolean('is_optional')->default(false); // "if applicable" / "if any"
            $table->string('funding_purpose')->nullable(); // emergency only: capex / om
            $table->string('responsible_unit')->nullable(); // LWUA unit documents only
            $table->timestamps();

            $table->unique(['project_type', 'stage', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
