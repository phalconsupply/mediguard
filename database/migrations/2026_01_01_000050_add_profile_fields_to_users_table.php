<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin: quản trị hệ thống; bhyt_manager: quản lý đợt và phân công;
            // dept_head: duyệt trong khoa; staff: người trực tiếp giải trình; viewer: chỉ xem.
            $table->string('role', 32)->default('staff')->after('email');
            $table->string('staff_code', 64)->nullable()->after('role');
            $table->unsignedBigInteger('department_id')->nullable()->after('staff_code');
            $table->boolean('is_active')->default(true)->after('department_id');

            $table->index('staff_code');
            $table->index(['department_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['staff_code']);
            $table->dropIndex(['department_id', 'role']);
            $table->dropColumn(['role', 'staff_code', 'department_id', 'is_active']);
        });
    }
};
