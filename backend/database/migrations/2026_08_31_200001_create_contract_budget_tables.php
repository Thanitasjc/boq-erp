<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('contract_number', 50)->nullable();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->decimal('contract_value', 18, 2)->default(0);
            $table->date('signed_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('retention_percent', 5, 2)->default(5);
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'terminated'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('boq_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('version_number', 10)->default('1.0');
            $table->string('title')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->decimal('boq_total', 18, 2)->default(0);
            $table->decimal('contingency_percent', 5, 2)->default(0);
            $table->decimal('contingency_amount', 18, 2)->default(0);
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->decimal('markup_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->boolean('is_baseline')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 30)->nullable();
            $table->string('cost_code_name')->nullable();
            $table->decimal('boq_amount', 18, 2)->default(0);
            $table->decimal('budget_amount', 18, 2)->default(0);
            $table->decimal('committed_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['budget_id', 'cost_code_id']);
        });

        Schema::create('cost_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wbs_id')->nullable()->constrained('wbs_nodes')->nullOnDelete();
            $table->foreignId('boq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('entry_type', ['budget', 'committed', 'actual', 'billing', 'cash_in', 'cash_out', 'revision']);
            $table->decimal('amount', 18, 2);
            $table->decimal('running_balance', 18, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'entry_type']);
            $table->index(['project_id', 'cost_code_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_ledger_entries');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('contracts');
    }
};
