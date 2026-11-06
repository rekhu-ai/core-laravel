<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('rekhu.ledger.connection');
    }

    public function up(): void
    {
        Schema::create(config('rekhu.ledger.table', 'rekhu_usage'), function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('tenant_name')->nullable();
            $table->string('user_id')->nullable()->index();
            $table->string('knower', 40)->index();
            $table->string('action', 40);
            $table->string('ref', 40)->nullable();
            $table->boolean('ok')->default(true);
            $table->unsignedSmallInteger('model_calls')->default(0);
            $table->decimal('cost_usd', 14, 6)->default(0);
            $table->decimal('price', 14, 4)->nullable();
            $table->unsignedInteger('ms')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rekhu.ledger.table', 'rekhu_usage'));
    }
};
