<?php

namespace App\Http\Controllers;

use App\Enums\AcademyCycleStatus;
use App\Models\AcademyCustomField;
use App\Models\AcademyWebsite;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Support\AccessibleColor;
use App\Support\Money;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAcademyWebsiteController extends Controller
{
    public function home(Request $request, string $subdomain): Response
    {
        [$website, $common] = $this->common($request, $subdomain);
        $academy = $website->academy;

        return Inertia::render('public/home', $common + [
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Programme $programme): array => $this->programmeData($programme, $common['base_url'])),
            'locations' => $academy->branches()->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'address' => collect([$branch->address_line, $branch->city, $branch->state_region, $branch->country_code])->filter()->implode(', '),
                    'email' => $branch->contact_email,
                    'phone' => $branch->contact_phone,
                ]),
        ]);
    }

    public function programmes(Request $request, string $subdomain): Response
    {
        [$website, $common] = $this->common($request, $subdomain);

        return Inertia::render('public/programmes', $common + [
            'programmes' => $website->academy->programmes()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Programme $programme): array => $this->programmeData($programme, $common['base_url'])),
        ]);
    }

    public function programme(Request $request, string $subdomain, int $programme): Response
    {
        [$website, $common] = $this->common($request, $subdomain);
        $academy = $website->academy;
        $record = $academy->programmes()->where('is_active', true)->findOrFail($programme);
        $offerings = $academy->programmeCycles()->where('programme_id', $record->id)->where('is_active', true)
            ->whereHas('cycle', fn ($query) => $query->where('status', AcademyCycleStatus::Active->value))
            ->with(['cycle', 'branch', 'feeItems', 'installments'])->get();

        return Inertia::render('public/programme', $common + [
            'programme' => $this->programmeData($record, $common['base_url']) + [
                'offerings' => $offerings->map(fn (ProgrammeCycle $offering): array => $this->offeringData($offering)),
            ],
        ]);
    }

    public function application(Request $request, string $subdomain): Response
    {
        [$website, $common] = $this->common($request, $subdomain);
        $academy = $website->academy;
        $offerings = $academy->programmeCycles()->where('is_active', true)
            ->whereHas('cycle', fn ($query) => $query->where('status', AcademyCycleStatus::Active->value))
            ->with(['cycle', 'programme', 'branch', 'feeItems', 'installments'])
            ->orderBy('programme_id')->get();

        return Inertia::render('public/application', $common + [
            'offerings' => $offerings->map(fn (ProgrammeCycle $offering): array => $this->offeringData($offering) + [
                'programme_name' => $offering->programme->name,
            ]),
            'custom_fields' => AcademyCustomField::query()
                ->where('academy_id', $academy->id)->where('entity_type', 'player')->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (AcademyCustomField $field): array => [
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'options' => $field->options ?? [],
                    'required' => $field->is_required,
                    'sensitive' => $field->is_sensitive,
                ]),
            'countries' => config('countries'),
        ]);
    }

    public function programmeImage(Request $request, string $subdomain, int $programme): StreamedResponse
    {
        [$website] = $this->common($request, $subdomain);
        $record = $website->academy->programmes()->where('is_active', true)->findOrFail($programme);
        abort_if($record->hero_image_path === null, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($record->hero_image_path), 404);

        return $disk->response($record->hero_image_path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo(string $subdomain): StreamedResponse
    {
        $website = AcademyWebsite::query()->where('subdomain', $subdomain)->where('is_published', true)
            ->with('academy:id,logo_path')->firstOrFail();
        $path = $website->academy->logo_path;
        abort_if($path === null, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{AcademyWebsite, array<string, mixed>} */
    private function common(Request $request, string $subdomain): array
    {
        $website = AcademyWebsite::query()->where('subdomain', $subdomain)->where('is_published', true)
            ->with('academy')->firstOrFail();
        $academy = $website->academy;
        $baseUrl = $request->routeIs('public.tenant.*') ? '' : '/sites/'.$website->subdomain;

        return [$website, [
            'academy' => [
                'name' => $academy->name,
                'type' => $academy->academy_type,
                'logo_url' => $academy->logo_path ? $baseUrl.'/logo' : null,
                'member_singular' => $academy->member_label_singular,
                'member_plural' => $academy->member_label_plural,
                'currency' => $academy->currency,
                'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
            ],
            'site' => [
                'subdomain' => $website->subdomain,
                'theme_key' => $website->theme_key,
                'sections' => $website->resolvedSections(),
            ],
            'base_url' => $baseUrl,
        ]];
    }

    /** @return array<string, mixed> */
    private function programmeData(Programme $programme, string $baseUrl): array
    {
        return [
            'id' => $programme->id,
            'name' => $programme->name,
            'code' => $programme->code,
            'description' => $programme->description,
            'duration' => $programme->duration,
            'delivery_format' => $programme->delivery_format,
            'programme_type' => $programme->programme_type,
            'public_timezone' => $programme->public_timezone,
            'level' => $programme->level,
            'outcomes_html' => $programme->outcomes_html,
            'skills_gained_html' => $programme->skills_gained_html,
            'curriculum_html' => $programme->curriculum_html,
            'eligibility_html' => $programme->eligibility_html,
            'audience_html' => $programme->audience_html,
            'demand_html' => $programme->demand_html,
            'application_process_html' => $programme->application_process_html,
            'tools_platforms' => $programme->tools_platforms ?? [],
            'faqs' => $programme->faqs ?? [],
            'testimonials' => $programme->testimonials ?? [],
            'image_url' => $programme->hero_image_path
                ? $baseUrl.'/programmes/'.$programme->id.'/image?v='.$programme->updated_at->timestamp
                : null,
            'url' => $baseUrl.'/programmes/'.$programme->id,
        ];
    }

    /** @return array<string, mixed> */
    private function offeringData(ProgrammeCycle $offering): array
    {
        $totalMinor = (int) $offering->feeItems->sum('amount_minor');

        return [
            'id' => $offering->id,
            'cycle_id' => $offering->academy_cycle_id,
            'cycle_name' => $offering->cycle->name,
            'programme_id' => $offering->programme_id,
            'branch_id' => $offering->branch_id,
            'branch_name' => $offering->branch?->name ?? 'All locations',
            'fee_name' => $offering->fee_name,
            'currency' => $offering->currency,
            'application_fee' => Money::fromMinor($offering->application_fee_minor),
            'total' => Money::fromMinor($totalMinor),
            'fee_items' => $offering->feeItems->map(fn ($item): array => [
                'name' => $item->name,
                'amount' => Money::fromMinor($item->amount_minor),
            ]),
            'installments' => $offering->installments->sortBy('sequence')->values()->map(fn ($installment): array => [
                'name' => $installment->name,
                'amount' => Money::fromMinor($installment->amount_minor),
                'due_on' => $installment->due_on?->toDateString(),
                'due_offset_days' => $installment->due_offset_days,
            ]),
        ];
    }
}
