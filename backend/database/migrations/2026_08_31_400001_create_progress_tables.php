<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');
            $table->decimal('planned_percent', 5, 2)->default(0);
            $table->decimal('planned_value', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'period_month']);
        });

        Schema::create('progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_code_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_month');
            $table->decimal('actual_percent', 5, 2)->default(0);
            $table->decimal('earned_value', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_entries');
        Schema::dropIfExists('progress_baselines');
    }
};
