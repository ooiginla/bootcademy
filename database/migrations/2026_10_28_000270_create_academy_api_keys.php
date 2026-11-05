<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 16);
            $table->char('secret_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['academy_id', 'revoked_at']);
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->string('source', 24)->default('admin')->after('reference');
            $table->string('external_reference', 120)->nullable()->after('source');
            $table->char('submission_hash', 64)->nullable()->after('external_reference');
            $table->timestamp('guardian_consent_at')->nullable()->after('guardian_signature_path');
            $table->string('privacy_policy_version', 60)->nullable()->after('guardian_consent_at');
            $table->unique(['academy_id', 'external_reference']);
            $table->index(['academy_id', 'source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['academy_id', 'source', 'created_at']);
            $table->dropUnique(['academy_id', 'external_reference']);
            $table->dropColumn([
                'source', 'external_reference', 'submission_hash', 'guardian_consent_at', 'privacy_policy_version',
            ]);
        });

        Schema::dropIfExists('academy_api_keys');
    }
};
