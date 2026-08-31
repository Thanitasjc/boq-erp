<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('position')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('position');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['phone', 'position', 'is_active']);
        });
    }
};
