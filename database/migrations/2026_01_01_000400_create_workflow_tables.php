<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vụ việc giải trình, phân công, nội dung giải trình và minh chứng.
 *
 * Mỗi dòng nguồn bị từ chối/cảnh báo trong mỗi tệp là một vụ việc riêng,
 * kể cả khi cùng ID_CP xuất hiện ở tệp khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_row_id')->constrained()->cascadeOnDelete();
            $table->foreignId('error_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error_code', 32);
            $table->string('conclusion', 64)->nullable();   // Từ chối | Cảnh báo | Chấp nhận
            $table->boolean('requires_explanation')->default(true);
            $table->decimal('amount_reference', 18, 2)->nullable();
            $table->string('status', 32)->default('unassigned');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_source', 64)->nullable(); // resolver đã dùng
            $table->unsignedTinyInteger('priority')->default(3);
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->unique('source_row_id');
            $table->index(['import_batch_id', 'error_code', 'status']);
            $table->index(['import_batch_id', 'assignee_id', 'status']);
            $table->index(['import_batch_id', 'department_id', 'status']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolver', 64)->nullable();
            $table->string('reason', 512)->nullable();
            $table->timestamps();

            $table->index(['issue_case_id', 'created_at']);
        });

        Schema::create('explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('body');
            $table->string('resolution', 48)->nullable();   // de_nghi_chap_nhan|chap_nhan_xuat_toan|can_chinh_du_lieu|chuyen_don_vi
            $table->string('status', 32)->default('draft'); // draft|submitted|approved|returned
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('return_reason', 1024)->nullable();
            $table->timestamps();

            $table->unique(['issue_case_id', 'version']);
            $table->index(['issue_case_id', 'status']);
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('explanation_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime', 128)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('column_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope_type', 32)->default('global'); // global|error_code|error_group|department|batch
            $table->string('scope_value', 64)->nullable();
            $table->string('role', 32)->nullable();
            $table->json('columns');                        // danh sách internal_code hoặc khóa nghiệp vụ, theo thứ tự
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_value', 'role']);
        });

        Schema::create('export_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('stored_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->json('verification');                   // kết quả kiểm soát chất lượng
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_file_id', 'version']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('export_artifacts');
        Schema::dropIfExists('column_presets');
        Schema::dropIfExists('evidences');
        Schema::dropIfExists('explanations');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('issue_cases');
    }
};
