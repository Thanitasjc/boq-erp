<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variation_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('vo_number', 30)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('vo_type', ['addition', 'omission', 'modification'])->default('addition');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('variation_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 30)->nullable();
            $table->string('description');
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('boq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_order_items');
        Schema::dropIfExists('variation_orders');
    }
};
