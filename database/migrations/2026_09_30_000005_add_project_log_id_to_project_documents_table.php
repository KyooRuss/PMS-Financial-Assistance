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
        // Files an office uploads while acting on a step, shown under that timeline entry.
        // Internal ones (assessments, LWUA-POW drafts, memos) are for LWUA offices only.
        Schema::table('project_documents', function (Blueprint $table) {
            $table->foreignId('project_log_id')->nullable()->after('requirement_id')->constrained()->nullOnDelete();
            $table->boolean('internal')->default(false)->after('project_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropColumn('internal');
            $table->dropConstrainedForeignId('project_log_id');
        });
    }
};
