<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyUsersTableAddRoleAndVerification extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['general', 'admin'])->default('general')->after('password');
            $table->string('verification_code', 6)->nullable()->after('role');
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'verification_code', 'verification_code_expires_at']);
        });
    }
}

