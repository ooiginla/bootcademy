<?php

namespace App\Academies\Actions;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAcademy
{
    /**
     * @param  array{name: string, academy_type: string, timezone: string, currency: string}  $data
     */
    public function handle(User $owner, array $data): Academy
    {
        return DB::transaction(function () use ($owner, $data): Academy {
            $academy = Academy::create([
                'name' => trim($data['name']),
                'slug' => $this->uniqueSlug($data['name']),
                'academy_type' => trim($data['academy_type']),
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'created_by_user_id' => $owner->id,
            ]);

            AcademyMembership::create([
                'academy_id' => $academy->id,
                'user_id' => $owner->id,
                'role' => AcademyRole::OwnerAdmin,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            AcademyWebsite::create([
                'academy_id' => $academy->id,
                'subdomain' => $academy->slug,
                'theme_key' => 'launch',
                'is_published' => true,
                'sections' => [],
            ]);

            return $academy;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(mb_substr(Str::slug($name) ?: 'academy', 0, 63), '-');

        $slug = $base;
        $suffix = 2;

        while (Academy::query()->where('slug', $slug)->exists()) {
            $ending = '-'.$suffix;
            $slug = rtrim(mb_substr($base, 0, 63 - mb_strlen($ending)), '-').$ending;
            $suffix++;
        }

        return $slug;
    }
}
