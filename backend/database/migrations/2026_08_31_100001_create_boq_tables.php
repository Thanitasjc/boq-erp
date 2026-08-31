<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('version_number', 10);
            $table->string('title')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'version_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boq_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wbs_id')->nullable()->constrained('wbs_nodes')->nullOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wbs_code', 30)->nullable();
            $table->string('cost_code', 30)->nullable();
            $table->string('item_code', 50)->nullable();
            $table->text('description');
            $table->text('specification')->nullable();
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('material_rate', 18, 4)->default(0);
            $table->decimal('labor_rate', 18, 4)->default(0);
            $table->decimal('equipment_rate', 18, 4)->default(0);
            $table->decimal('unit_rate', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['boq_version_id', 'sort_order']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('approvable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('action', 20);
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 30);
            $table->string('file_name');
            $table->string('file_path');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->string('error_report_path')->nullable();
            $table->json('metadata')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('boq_items');
        Schema::dropIfExists('boq_versions');
    }
};
