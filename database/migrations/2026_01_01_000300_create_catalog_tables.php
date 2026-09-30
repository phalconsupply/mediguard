<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư viện lỗi tách làm hai phần có vòng đời khác nhau:
 *  - error_catalogs / error_catalog_variants: danh mục lỗi, dùng ngay để nhận diện và phân phối.
 *  - check_rules / check_rule_versions: quy tắc tự kiểm tra, chỉ chạy sau khi được duyệt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();           // 9065, 3014 ...
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('group', 64)->nullable();        // thuoc|dvkt|thoi_gian|chi_dinh|khac
            $table->string('responsible_role', 32)->default('unknown'); // orderer|performer|both|unknown
            $table->string('status', 32)->default('pending_review');    // pending_review|classified|manual_only|retired
            $table->string('first_seen_period', 16)->nullable();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->json('default_columns')->nullable();    // gợi ý cột hiển thị
            $table->text('explanation_template')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // Cùng mã lỗi nhưng mô tả/điều kiện đổi giữa các kỳ -> biến thể mới, không gộp tự động.
        Schema::create('error_catalog_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_catalog_id')->constrained()->cascadeOnDelete();
            $table->string('signature', 64);                // hash mô tả + cấu trúc cột
            $table->string('period', 16)->nullable();
            $table->string('sample_note', 1024)->nullable();
            $table->json('column_fingerprint')->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->decimal('amount_reference', 20, 2)->nullable();
            $table->string('status', 32)->default('new');   // new|reviewed|merged
            $table->foreignId('source_file_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['error_catalog_id', 'signature']);
        });

        // Mã lỗi -> vai trò chịu trách nhiệm và thứ tự chọn người, có ngày hiệu lực.
        Schema::create('assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('error_code', 32);
            $table->json('resolver_chain');                 // ví dụ ["ma_bsi_thuc_hien","service_owner","ma_bac_si"]
            $table->foreignId('fallback_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('outpatient_mode', 32)->default('per_person'); // per_person|pool
            $table->unsignedInteger('due_days')->default(7);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['error_code', 'effective_from']);
        });

        Schema::create('check_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('error_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 32)->default('all');    // all|noi_tru|ngoai_tru
            $table->timestamps();
        });

        Schema::create('check_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');                     // điều kiện có cấu trúc
            $table->json('required_fields');                // trường bắt buộc; thiếu -> không đủ dữ liệu
            $table->json('exceptions')->nullable();
            $table->string('severity', 16)->default('warning'); // info|warning|blocker
            $table->string('status', 32)->default('draft'); // draft|analysing|testing|approved|active|paused|retired
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('test_cases')->nullable();
            $table->timestamps();

            $table->unique(['check_rule_id', 'version']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_rule_versions');
        Schema::dropIfExists('check_rules');
        Schema::dropIfExists('assignment_rules');
        Schema::dropIfExists('error_catalog_variants');
        Schema::dropIfExists('error_catalogs');
    }
};
