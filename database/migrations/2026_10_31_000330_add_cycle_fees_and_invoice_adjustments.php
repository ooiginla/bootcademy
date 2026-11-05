<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academy_cycle_fees')) {
            Schema::create('academy_cycle_fees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academy_cycle_id');
                $table->string('name', 160);
                $table->string('category', 80)->default('custom');
                $table->char('currency', 3);
                $table->unsignedBigInteger('amount_minor');
                $table->boolean('is_compulsory')->default(false);
                $table->boolean('applies_to_all_programmes')->default(true);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['academy_id', 'id']);
                $table->unique(['academy_id', 'academy_cycle_id', 'name']);
                $table->foreign(['academy_id', 'academy_cycle_id'], 'cycle_fee_cycle_fk')
                    ->references(['academy_id', 'id'])->on('academy_cycles')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('academy_cycle_fee_programme')) {
            Schema::create('academy_cycle_fee_programme', function (Blueprint $table): void {
                $table->foreignId('academy_id');
                $table->foreignId('academy_cycle_fee_id');
                $table->foreignId('programme_id');
                $table->timestamps();

                $table->primary(['academy_cycle_fee_id', 'programme_id']);
                $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
                $table->foreign(['academy_id', 'academy_cycle_fee_id'], 'cycle_fee_programme_fee_fk')
                    ->references(['academy_id', 'id'])->on('academy_cycle_fees')->cascadeOnDelete();
                $table->foreign(['academy_id', 'programme_id'], 'cycle_fee_programme_programme_fk')
                    ->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            $foreignKeys = collect(['invoice_application_fk', 'invoice_programme_cycle_fk', 'invoice_programme_fk'])
                ->filter(fn (string $name): bool => $this->foreignKeyExists('academy_invoices', $name));
            Schema::table('academy_invoices', function (Blueprint $table) use ($foreignKeys): void {
                $foreignKeys->each(fn (string $name) => $table->dropForeign($name));
            });
        }
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->dropUnique('application_invoice_purpose_unique');
        });
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->foreignId('programme_cycle_id')->nullable()->change();
            $table->foreignId('programme_id')->nullable()->change();
            $table->foreignId('academy_cycle_fee_id')->nullable()->after('programme_cycle_id');
            $table->unsignedBigInteger('written_off_minor')->default(0)->after('paid_minor');
            $table->text('cancellation_reason')->nullable()->after('due_on');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign(['academy_id', 'application_id'], 'invoice_application_fk')
                    ->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
                $table->foreign(['academy_id', 'programme_cycle_id'], 'invoice_programme_cycle_fk')
                    ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
                $table->foreign(['academy_id', 'programme_id'], 'invoice_programme_fk')
                    ->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
                $table->foreign(['academy_id', 'academy_cycle_fee_id'], 'invoice_cycle_fee_fk')
                    ->references(['academy_id', 'id'])->on('academy_cycle_fees')->restrictOnDelete();
            }
            $table->unique(['academy_id', 'player_id', 'academy_cycle_fee_id'], 'player_cycle_fee_invoice_unique');
            $table->unique(['academy_id', 'application_id', 'academy_cycle_fee_id'], 'application_cycle_fee_invoice_unique');
        });

        Schema::table('academy_invoice_installments', function (Blueprint $table): void {
            $table->unsignedBigInteger('written_off_minor')->default(0)->after('paid_minor');
        });

        Schema::create('academy_invoice_write_offs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_invoice_id');
            $table->unsignedBigInteger('amount_minor');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'write_off_invoice_fk')
                ->references(['academy_id', 'id'])->on('academy_invoices')->restrictOnDelete();
        });

        Schema::create('academy_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academy_cycle_id')->nullable();
            $table->foreignId('academy_invoice_write_off_id')->nullable();
            $table->string('category', 80);
            $table->string('description', 200);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->date('incurred_on');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('academy_invoice_write_off_id');
            $table->index(['academy_id', 'incurred_on']);
            $table->foreign(['academy_id', 'academy_cycle_id'], 'expense_cycle_fk')
                ->references(['academy_id', 'id'])->on('academy_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_write_off_id'], 'expense_write_off_fk')
                ->references(['academy_id', 'id'])->on('academy_invoice_write_offs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('academy_cycle_fees')->exists()
            || DB::table('academy_invoice_write_offs')->exists()
            || DB::table('academy_expenses')->exists()) {
            throw new RuntimeException('Archive cycle fees, write-offs, and their expenses before rolling back this financial migration.');
        }

        Schema::dropIfExists('academy_expenses');
        Schema::dropIfExists('academy_invoice_write_offs');
        Schema::table('academy_invoice_installments', fn (Blueprint $table) => $table->dropColumn('written_off_minor'));
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->dropUnique('player_cycle_fee_invoice_unique');
            $table->dropUnique('application_cycle_fee_invoice_unique');
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('invoice_cycle_fee_fk');
                $table->dropForeign('invoice_programme_cycle_fk');
                $table->dropForeign('invoice_programme_fk');
            }
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn(['academy_cycle_fee_id', 'written_off_minor', 'cancellation_reason', 'cancelled_at', 'cancelled_by_user_id']);
        });
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->foreignId('programme_cycle_id')->nullable(false)->change();
            $table->foreignId('programme_id')->nullable(false)->change();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign(['academy_id', 'programme_cycle_id'], 'invoice_programme_cycle_fk')
                    ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
                $table->foreign(['academy_id', 'programme_id'], 'invoice_programme_fk')
                    ->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            }
            $table->unique(['academy_id', 'application_id', 'purpose'], 'application_invoice_purpose_unique');
        });
        Schema::dropIfExists('academy_cycle_fee_programme');
        Schema::dropIfExists('academy_cycle_fees');
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return (bool) DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
