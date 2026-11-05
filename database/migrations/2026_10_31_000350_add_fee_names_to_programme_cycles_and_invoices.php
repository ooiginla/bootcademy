<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_cycles', function (Blueprint $table): void {
            $table->string('fee_name', 160)->default('Programme fee')->after('programme_id');
        });
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->string('fee_name', 160)->nullable()->after('number');
        });

        $programmeFeeNames = DB::table('programme_cycles')->pluck('fee_name', 'id');
        $customFeeNames = DB::table('academy_cycle_fees')->pluck('name', 'id');
        DB::table('academy_invoices')
            ->select(['id', 'purpose', 'programme_cycle_id', 'academy_cycle_fee_id'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $invoice) use ($programmeFeeNames, $customFeeNames): void {
                $name = match ($invoice->purpose) {
                    'application' => 'Application fee',
                    'programme' => $programmeFeeNames->get($invoice->programme_cycle_id, 'Programme fee'),
                    default => $customFeeNames->get($invoice->academy_cycle_fee_id, 'Custom fee'),
                };
                DB::table('academy_invoices')->where('id', $invoice->id)->update(['fee_name' => $name]);
            });
    }

    public function down(): void
    {
        Schema::table('academy_invoices', fn (Blueprint $table) => $table->dropColumn('fee_name'));
        Schema::table('programme_cycles', fn (Blueprint $table) => $table->dropColumn('fee_name'));
    }
};
