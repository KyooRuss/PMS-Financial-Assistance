<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Users can log in with a username as well as their email. Existing accounts get a username made from
 * their email (lwrd@pms.test → lwrd), with a number added when two would clash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('last_name');
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::limit(preg_replace('/[^a-z0-9._-]/', '', Str::lower(Str::before($user->email, '@'))), 45, '');
            $base = strlen($base) >= 2 ? $base : 'user'.$user->id;

            $username = $base;
            for ($n = 2; isset($taken[$username]); $n++) {
                $username = $base.$n;
            }
            $taken[$username] = true;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
