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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_district_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who filed the request
            $table->string('type');
            $table->string('funding_purpose')->nullable(); // emergency only: capex / om
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('current_role')->nullable()->index(); // whose turn it is; kept in sync by the model
            $table->boolean('is_viable')->nullable(); // LWRD rapid assessment: financially viable or not
            // Final evaluation: LWRD asks the LWD for the post-evaluation documents, the LWD sends them.
            $table->timestamp('compliance_requested_at')->nullable();
            $table->timestamp('compliance_submitted_at')->nullable();
            $table->timestamp('cfp_concurred_at')->nullable(); // LWD agreed to the cash flow projections (regular)
            $table->timestamp('disapproval_letter_sent_at')->nullable(); // LWRD informed the LWD; a disapproved request then closes
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['water_district_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
