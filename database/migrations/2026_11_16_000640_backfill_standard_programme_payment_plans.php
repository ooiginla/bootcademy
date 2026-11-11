<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('programme_cycles')->orderBy('id')->get()->each(function (object $offering) use ($now): void {
                $cycle = DB::table('academy_cycles')->where('id', $offering->academy_cycle_id)->first();
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

                    $scope = DB::table('programme_cycle_payment_plans')
                        ->where('academy_id', $offering->academy_id)
                        ->where('programme_cycle_id', $offering->id)
                        ->where('term_scope_id', (int) ($first->academy_cycle_term_id ?? 0))
                        ->where('cohort_scope_id', (int) ($first->cohort_id ?? 0));
                    $standard = (clone $scope)
                        ->where('name', 'Standard payment')
                        ->orderByDesc('is_legacy')
                        ->orderBy('id')
                        ->first();

                    if ($standard !== null) {
                        DB::table('programme_cycle_payment_plans')->where('id', $standard->id)->update([
                            'sort_order' => 0,
                            'is_standard' => true,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $planId = DB::table('programme_cycle_payment_plans')->insertGetId([
                            'academy_id' => $offering->academy_id,
                            'programme_cycle_id' => $offering->id,
                            'academy_cycle_term_id' => $first->academy_cycle_term_id,
                            'cohort_id' => $first->cohort_id,
                            'term_scope_id' => (int) ($first->academy_cycle_term_id ?? 0),
                            'cohort_scope_id' => (int) ($first->cohort_id ?? 0),
                            'name' => 'Standard payment',
                            'description' => 'Pay the standard programme fee in one payment.',
                            'sort_order' => 0,
                            'currency' => $offering->currency,
                            'total_minor' => $total,
                            'is_default' => ! (clone $scope)->where('is_default', true)->exists(),
                            'is_active' => (bool) $offering->is_active,
                            'is_legacy' => false,
                            'is_standard' => true,
                            'created_by_user_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        foreach ($items->values() as $index => $item) {
                            DB::table('programme_cycle_payment_plan_items')->insert([
                                'academy_id' => $offering->academy_id,
                                'programme_cycle_payment_plan_id' => $planId,
                                'name' => $item->name,
                                'description' => $item->description,
                                'amount_minor' => $item->amount_minor,
                                'sort_order' => $index,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        DB::table('programme_cycle_payment_plan_installments')->insert([
                            'academy_id' => $offering->academy_id,
                            'programme_cycle_payment_plan_id' => $planId,
                            'name' => 'Full payment',
                            'amount_minor' => $total,
                            'due_on' => $cycle?->type === 'fixed' ? $cycle->starts_on : null,
                            'due_offset_days' => $cycle?->type === 'rolling' ? 0 : null,
                            'sequence' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    (clone $scope)->where('is_standard', false)->orderBy('id')->get()
                        ->each(function (object $plan, int $index) use ($now): void {
                            DB::table('programme_cycle_payment_plans')->where('id', $plan->id)->update([
                                'sort_order' => ($index + 1) * 10,
                                'updated_at' => $now,
                            ]);
                        });
                }
            });
        });
    }

    public function down(): void
    {
        // Preserve created financial configuration; the schema rollback removes only the added metadata.
    }
};
