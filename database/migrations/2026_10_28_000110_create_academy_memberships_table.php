<?php

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', array_column(AcademyRole::cases(), 'value'));
            $table->enum('status', array_column(MembershipStatus::cases(), 'value'))
                ->default(MembershipStatus::Active->value);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['academy_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_memberships');
    }
};
