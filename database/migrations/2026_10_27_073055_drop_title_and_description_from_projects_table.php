<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "title" and "description" were removed from the create_projects_table migration after some
 * databases had already run it. Those databases still have a required "title" column, so creating
 * a project fails. This drops both columns where they still exist; fresh databases never had them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(['title', 'description'], fn (string $column) => Schema::hasColumn('projects', $column)));

        if ($columns !== []) {
            Schema::table('projects', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        // Nothing to restore: the app no longer uses these columns.
    }
};
