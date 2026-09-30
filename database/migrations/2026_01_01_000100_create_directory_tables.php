<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục quản trị: khoa/phòng, nhân sự và ánh xạ mã nhân sự sang tài khoản.
 * Mọi ánh xạ đều có ngày hiệu lực để tra cứu đúng theo kỳ quyết toán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('name')->nullable();
            $table->string('kind', 32)->default('clinical'); // clinical|paraclinical|pharmacy|admin|outpatient
            $table->boolean('is_outpatient_pool')->default(false);
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'effective_from']);
            $table->index('is_active');
        });

        Schema::create('staff_directory', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code', 64);          // MA_BAC_SI / MA_BSI_THUC_HIEN
            $table->string('full_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('can_order')->default(true);      // được chỉ định/kê đơn
            $table->boolean('can_perform')->default(true);    // được thực hiện dịch vụ
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['staff_code', 'effective_from']);
            $table->index('user_id');
        });

        // Mã dịch vụ -> đơn vị thực hiện, dùng khi dòng chi phí không có mã người thực hiện.
        Schema::create('service_owners', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 64);
            $table->string('service_name')->nullable();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['service_code', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_owners');
        Schema::dropIfExists('staff_directory');
        Schema::dropIfExists('departments');
    }
};
