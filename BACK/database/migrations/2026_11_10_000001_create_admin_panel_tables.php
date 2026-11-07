<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super admin panel: application error log, audit trail and the user
 * columns needed for global user management.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Reported exceptions. Repeated errors (same fingerprint, still open)
        // are grouped: occurrences++ and last_seen_at instead of a new row.
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('level', 20)->default('error');
            $table->string('exception', 255);
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('ip', 45)->nullable();
            $table->mediumText('trace')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });

        // Who did what (company admins and super admin).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255)->nullable(); // snapshot, survives user deletion
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->timestamp('disabled_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'disabled_at']);
        });
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('error_logs');
    }
};
