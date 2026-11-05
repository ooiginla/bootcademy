<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_memberships', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id']);
        });
        Schema::table('academy_invitations', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id']);
        });

        Schema::create('academy_membership_branch', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('academy_membership_id');
            $table->foreignId('branch_id');
            $table->timestamps();
            $table->primary(['academy_id', 'academy_membership_id', 'branch_id']);
            $table->foreign(['academy_id', 'academy_membership_id'], 'amb_membership_fk')
                ->references(['academy_id', 'id'])->on('academy_memberships')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'amb_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('academy_invitation_branch', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('academy_invitation_id');
            $table->foreignId('branch_id');
            $table->timestamps();
            $table->primary(['academy_id', 'academy_invitation_id', 'branch_id']);
            $table->foreign(['academy_id', 'academy_invitation_id'], 'aib_invitation_fk')
                ->references(['academy_id', 'id'])->on('academy_invitations')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'aib_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_invitation_branch');
        Schema::dropIfExists('academy_membership_branch');
        Schema::table('academy_invitations', fn (Blueprint $table) => $table->dropUnique(['academy_id', 'id']));
        Schema::table('academy_memberships', fn (Blueprint $table) => $table->dropUnique(['academy_id', 'id']));
    }
};
