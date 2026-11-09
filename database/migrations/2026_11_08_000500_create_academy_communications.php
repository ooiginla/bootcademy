<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('automatic_payment_reminders')->default(false);
            $table->json('payment_reminder_days')->nullable();
            $table->boolean('automatic_renewal_reminders')->default(false);
            $table->json('renewal_reminder_days')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->string('title', 160);
            $table->text('body');
            $table->string('audience_type', 24)->default('all');
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('programme_id')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status', 'publish_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'announcement_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'announcement_programme_fk')->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('announcement_id')->nullable();
            $table->foreignId('academy_invoice_id')->nullable();
            $table->foreignId('enrollment_id')->nullable();
            $table->string('type', 40);
            $table->string('channel', 20)->default('mail');
            $table->string('recipient_email', 254);
            $table->string('recipient_name', 160)->nullable();
            $table->string('status', 24)->default('queued');
            $table->string('idempotency_key', 190);
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'idempotency_key']);
            $table->index(['academy_id', 'type', 'status', 'queued_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'announcement_id'], 'delivery_announcement_fk')->references(['academy_id', 'id'])->on('announcements')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'delivery_invoice_fk')->references(['academy_id', 'id'])->on('academy_invoices')->restrictOnDelete();
            $table->foreign(['academy_id', 'enrollment_id'], 'delivery_enrollment_fk')->references(['academy_id', 'id'])->on('enrollments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('academy_communication_settings');
    }
};
