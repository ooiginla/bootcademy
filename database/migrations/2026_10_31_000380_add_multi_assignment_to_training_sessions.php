<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->boolean('all_branches')->default(false)->after('programme_id');
            $table->string('delivery_mode', 16)->default('physical')->after('location');
            $table->string('meeting_link', 2048)->nullable()->after('delivery_mode');
        });

        Schema::create('training_session_branch', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('training_session_id');
            $table->foreignId('branch_id');
            $table->timestamps();
            $table->primary(['training_session_id', 'branch_id']);
            $table->index(['academy_id', 'branch_id']);
            $table->foreign(['academy_id', 'training_session_id'], 'tsb_session_fk')->references(['academy_id', 'id'])->on('training_sessions')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'tsb_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('training_session_coach', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('training_session_id');
            $table->foreignId('academy_membership_id');
            $table->timestamps();
            $table->primary(['training_session_id', 'academy_membership_id'], 'tsc_primary');
            $table->index(['academy_id', 'academy_membership_id'], 'tsc_academy_coach_index');
            $table->foreign(['academy_id', 'training_session_id'], 'tsc_session_fk')->references(['academy_id', 'id'])->on('training_sessions')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_membership_id'], 'tsc_membership_fk')->references(['academy_id', 'id'])->on('academy_memberships')->restrictOnDelete();
        });

        DB::table('training_sessions')->orderBy('id')->each(function (object $session): void {
            DB::table('training_session_branch')->insert([
                'academy_id' => $session->academy_id, 'training_session_id' => $session->id,
                'branch_id' => $session->branch_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($session->coach_membership_id !== null) {
                DB::table('training_session_coach')->insert([
                    'academy_id' => $session->academy_id, 'training_session_id' => $session->id,
                    'academy_membership_id' => $session->coach_membership_id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_coach');
        Schema::dropIfExists('training_session_branch');
        Schema::table('training_sessions', fn (Blueprint $table) => $table->dropColumn(['all_branches', 'delivery_mode', 'meeting_link']));
    }
};
