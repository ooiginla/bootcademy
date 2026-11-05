<?php

namespace App\Academies\Actions;

use App\Academies\AcademyWebsiteDefaults;
use App\Models\Academy;
use App\Models\AcademyWebsite;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateAcademyWebsite
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{subdomain: string, theme_key: string, is_published: bool, sections: array<string, array<string, mixed>>} $data */
    public function handle(Academy $academy, AcademyWebsite $website, User $actor, array $data, Request $request): AcademyWebsite
    {
        return DB::transaction(function () use ($academy, $website, $actor, $data, $request): AcademyWebsite {
            $before = $website->only(['subdomain', 'theme_key', 'is_published']);
            $beforeSections = $website->resolvedSections();
            $sections = AcademyWebsiteDefaults::merge($data['sections']);

            $website->update([
                'subdomain' => $data['subdomain'],
                'theme_key' => $data['theme_key'],
                'is_published' => $data['is_published'],
                'sections' => $sections,
            ]);

            $changedSections = collect($sections)->keys()->filter(
                fn (string $key): bool => ($beforeSections[$key] ?? null) !== $sections[$key],
            )->values()->all();

            $this->audit->record('academy.website.updated', $actor, $academy, $website, [
                'before' => $before,
                'after' => $website->only(['subdomain', 'theme_key', 'is_published']),
                'changed_sections' => $changedSections,
            ], $request);

            return $website->fresh();
        });
    }
}
