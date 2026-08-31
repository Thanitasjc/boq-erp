<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->date('report_date');
            $table->string('weather', 30)->nullable();
            $table->unsignedInteger('workforce_count')->default(0);
            $table->text('summary')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'report_date']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('daily_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['material', 'labor', 'equipment'])->default('material');
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 30)->nullable();
            $table->string('description');
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_items');
        Schema::dropIfExists('daily_reports');
    }
};
