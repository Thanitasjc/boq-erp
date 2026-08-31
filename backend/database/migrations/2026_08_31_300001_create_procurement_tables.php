<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('required_date')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 30)->nullable();
            $table->string('description');
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('boq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->string('title');
            $table->date('order_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->enum('status', [
                'draft', 'submitted', 'approved', 'issued',
                'partially_received', 'completed', 'rejected', 'cancelled',
            ])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 30)->nullable();
            $table->string('description');
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->date('receipt_date');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('uom_code', 20)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
