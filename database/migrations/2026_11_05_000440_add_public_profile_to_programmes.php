<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->string('duration', 100)->nullable()->after('description');
            $table->string('delivery_format', 100)->nullable()->after('duration');
            $table->string('programme_type', 100)->nullable()->after('delivery_format');
            $table->string('public_timezone', 100)->nullable()->after('programme_type');
            $table->string('level', 100)->nullable()->after('public_timezone');
            $table->text('outcomes_html')->nullable()->after('level');
            $table->text('skills_gained_html')->nullable()->after('outcomes_html');
            $table->longText('curriculum_html')->nullable()->after('skills_gained_html');
            $table->text('eligibility_html')->nullable()->after('curriculum_html');
            $table->text('audience_html')->nullable()->after('eligibility_html');
            $table->text('demand_html')->nullable()->after('audience_html');
            $table->longText('application_process_html')->nullable()->after('demand_html');
            $table->json('tools_platforms')->nullable()->after('application_process_html');
            $table->json('faqs')->nullable()->after('tools_platforms');
            $table->json('testimonials')->nullable()->after('faqs');
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->dropColumn([
                'duration', 'delivery_format', 'programme_type', 'public_timezone', 'level',
                'outcomes_html', 'skills_gained_html', 'curriculum_html', 'eligibility_html',
                'audience_html', 'demand_html', 'application_process_html', 'tools_platforms',
                'faqs', 'testimonials',
            ]);
        });
    }
};
