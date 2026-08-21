<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->change();
            }
            if (! Schema::hasColumn('users', 'fname')) {
                $table->string('fname')->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'lname')) {
                $table->string('lname')->nullable()->after('fname');
            }
            if (! Schema::hasColumn('users', 'mname')) {
                $table->string('mname')->nullable()->after('lname');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender')->nullable()->after('address');
            }
            if (! Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'profile_picture')) {
                $table->string('profile_picture')->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('profile_picture');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
            if (! Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('users', 'last_logout')) {
                $table->timestamp('last_logout')->nullable()->after('last_login');
            }
        });

        DB::table('users')
            ->whereNull('fname')
            ->whereNotNull('name')
            ->update([
                'fname' => DB::raw('name'),
                'lname' => '',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'fname',
                'lname',
                'mname',
                'phone',
                'address',
                'gender',
                'date_of_birth',
                'profile_picture',
                'role',
                'is_active',
                'last_login',
                'last_logout',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
