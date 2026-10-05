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
        // Checklist items an office flagged when returning a request to the water district.
        Schema::create('returned_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_log_id')->constrained()->cascadeOnDelete(); // the "returned" timeline entry
            $table->text('remarks');
            $table->timestamp('resolved_at')->nullable(); // set when the LWD resubmits
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returned_items');
    }
};
