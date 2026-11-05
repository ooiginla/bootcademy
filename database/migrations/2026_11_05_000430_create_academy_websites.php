<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_websites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subdomain', 63)->unique();
            $table->string('theme_key', 40)->default('launch');
            $table->boolean('is_published')->default(true)->index();
            $table->json('sections');
            $table->timestamps();
        });

        $usedSubdomains = [];
        DB::table('academies')->select(['id', 'slug'])->orderBy('id')->chunkById(100, function ($academies) use (&$usedSubdomains): void {
            $now = now();
            foreach ($academies as $academy) {
                $base = trim(mb_substr($academy->slug, 0, 63), '-');
                $subdomain = $base;
                $suffix = 2;
                while (isset($usedSubdomains[$subdomain])) {
                    $ending = '-'.$suffix;
                    $subdomain = rtrim(mb_substr($base, 0, 63 - mb_strlen($ending)), '-').$ending;
                    $suffix++;
                }
                $usedSubdomains[$subdomain] = true;

                DB::table('academy_websites')->insert([
                    'academy_id' => $academy->id,
                    'subdomain' => $subdomain,
                    'theme_key' => 'launch',
                    'is_published' => true,
                    'sections' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_websites');
    }
};
