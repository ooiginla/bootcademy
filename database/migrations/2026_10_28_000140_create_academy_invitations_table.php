<?php

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', array_column(AcademyRole::cases(), 'value'));
            $table->enum('status', array_column(AcademyInvitationStatus::cases(), 'value'))
                ->default(AcademyInvitationStatus::Pending->value);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['academy_id', 'email', 'status']);
            $table->index(['email', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_invitations');
    }
};
