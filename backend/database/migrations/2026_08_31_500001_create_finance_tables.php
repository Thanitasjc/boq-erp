<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('progress_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('title');
            $table->date('claim_date');
            $table->date('period_month')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->decimal('previous_percent', 5, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('retention_percent', 5, 2)->default(5);
            $table->decimal('retention_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'invoiced', 'paid', 'rejected', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('progress_claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->date('disbursement_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('payee')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_disbursements');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('progress_claims');
    }
};
