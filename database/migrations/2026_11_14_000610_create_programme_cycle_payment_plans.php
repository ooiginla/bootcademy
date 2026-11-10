<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_cycle_payment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_id');
            $table->foreignId('academy_cycle_term_id')->nullable();
            $table->foreignId('cohort_id')->nullable();
            $table->unsignedBigInteger('term_scope_id')->default(0);
            $table->unsignedBigInteger('cohort_scope_id')->default(0);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_legacy')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(
                ['academy_id', 'programme_cycle_id', 'term_scope_id', 'cohort_scope_id', 'name'],
                'programme_payment_plan_name_unique',
            );
            $table->index(
                ['academy_id', 'programme_cycle_id', 'term_scope_id', 'cohort_scope_id', 'is_active'],
                'programme_payment_plan_scope_index',
            );
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'programme_payment_plan_offering_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_term_id'], 'programme_payment_plan_term_fk')
                ->references(['academy_id', 'id'])->on('academy_cycle_terms')->restrictOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'programme_payment_plan_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
        });

        Schema::create('programme_cycle_payment_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_payment_plan_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_payment_plan_id'], 'payment_plan_item_parent_fk')
                ->references(['academy_id', 'id'])->on('programme_cycle_payment_plans')->cascadeOnDelete();
            $table->index(['programme_cycle_payment_plan_id', 'sort_order'], 'payment_plan_item_order_index');
        });

        Schema::create('programme_cycle_payment_plan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_payment_plan_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['programme_cycle_payment_plan_id', 'sequence'], 'payment_plan_installment_sequence_unique');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_payment_plan_id'], 'payment_plan_installment_parent_fk')
                ->references(['academy_id', 'id'])->on('programme_cycle_payment_plans')->cascadeOnDelete();
        });

        $now = now();
        DB::table('programme_cycles')->orderBy('id')->get()->each(function (object $offering) use ($now): void {
            $groups = DB::table('programme_cycle_fee_items')
                ->where('programme_cycle_id', $offering->id)
                ->orderBy('sort_order')
                ->get()
                ->groupBy(fn (object $item): string => ($item->academy_cycle_term_id ?? 0).':'.($item->cohort_id ?? 0));

            foreach ($groups as $items) {
                $first = $items->first();
                $total = (int) $items->sum('amount_minor');
                if ($first === null || $total <= 0) {
                    continue;
                }

                $planId = DB::table('programme_cycle_payment_plans')->insertGetId([
                    'academy_id' => $offering->academy_id,
                    'programme_cycle_id' => $offering->id,
                    'academy_cycle_term_id' => $first->academy_cycle_term_id,
                    'cohort_id' => $first->cohort_id,
                    'term_scope_id' => (int) ($first->academy_cycle_term_id ?? 0),
                    'cohort_scope_id' => (int) ($first->cohort_id ?? 0),
                    'name' => 'Standard payment',
                    'description' => 'Existing fee schedule preserved during payment-plan setup.',
                    'currency' => $offering->currency,
                    'total_minor' => $total,
                    'is_default' => true,
                    'is_active' => true,
                    'is_legacy' => true,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($items->values() as $index => $item) {
                    DB::table('programme_cycle_payment_plan_items')->insert([
                        'academy_id' => $offering->academy_id,
                        'programme_cycle_payment_plan_id' => $planId,
                        'name' => $item->name,
                        'amount_minor' => $item->amount_minor,
                        'sort_order' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $legacyInstallments = $groups->count() === 1
                    ? DB::table('programme_cycle_installments')->where('programme_cycle_id', $offering->id)->orderBy('sequence')->get()
                    : collect();
                if ($legacyInstallments->isNotEmpty() && (int) $legacyInstallments->sum('amount_minor') === $total) {
                    foreach ($legacyInstallments as $installment) {
                        DB::table('programme_cycle_payment_plan_installments')->insert([
                            'academy_id' => $offering->academy_id,
                            'programme_cycle_payment_plan_id' => $planId,
                            'name' => $installment->name,
                            'amount_minor' => $installment->amount_minor,
                            'due_on' => $installment->due_on,
                            'due_offset_days' => $installment->due_offset_days,
                            'sequence' => $installment->sequence,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } else {
                    DB::table('programme_cycle_payment_plan_installments')->insert([
                        'academy_id' => $offering->academy_id,
                        'programme_cycle_payment_plan_id' => $planId,
                        'name' => 'Full payment',
                        'amount_minor' => $total,
                        'due_on' => null,
                        'due_offset_days' => 0,
                        'sequence' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_cycle_payment_plan_installments');
        Schema::dropIfExists('programme_cycle_payment_plan_items');
        Schema::dropIfExists('programme_cycle_payment_plans');
    }
};
