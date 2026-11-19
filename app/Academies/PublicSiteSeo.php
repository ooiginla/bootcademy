<?php

namespace App\Academies;

use App\Models\AcademyWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Title, description, keywords, share image and favicon for an academy's public website.
 *
 * The values come from the academy (name, logo, type, programmes, branch cities), with optional
 * overrides in the website editor's header section. They are rendered on the server so link
 * previews (WhatsApp, Facebook, X) and search engines see the academy, not the platform.
 * Pages that carry a private application reference or order link are marked noindex.
 */
class PublicSiteSeo
{
    /**
     * @param  array{title?: string|null, description?: string|null, image_url?: string|null, indexable?: bool}  $page
     * @return array{title: string, description: string, keywords: string, canonical_url: string, image_url: string|null, icon_url: string|null, robots: string, structured_data: array<string, mixed>}
     */
    public function for(Request $request, AcademyWebsite $website, string $baseUrl, array $page = []): array
    {
        $academy = $website->academy;
        $sections = $website->resolvedSections();
        $logoUrl = $this->logoUrl($request, $website, $baseUrl);
        $programmes = $this->strings($academy->programmes()->where('is_active', true)->orderBy('name')->limit(8)->pluck('name')->all());
        $cities = $this->strings($academy->branches()->where('is_active', true)->whereNotNull('city')->distinct()->orderBy('city')->limit(5)->pluck('city')->all());
        $type = $this->typeLabel($academy->academy_type);

        $siteTitle = $this->text(data_get($sections, 'header.seo_title')) ?: $academy->name;
        $siteDescription = $this->text(data_get($sections, 'header.seo_description'))
            ?: $this->edited($sections, 'about.body')
            ?: $this->edited($sections, 'hero.description')
            ?: $this->generatedDescription($academy->name, $type, $cities, $programmes);
        $keywords = $this->text(data_get($sections, 'header.seo_keywords')) ?: collect([
            $academy->name,
            $type ? $type.' academy' : null,
            ...array_map(fn (string $city): string => ($type ? $type.' academy ' : 'academy ').'in '.$city, $cities),
            ...$programmes,
            $academy->member_label_plural ? Str::lower($academy->member_label_plural).' programmes' : null,
            'academy registration',
        ])->filter()->unique()->implode(', ');

        $title = Str::limit($this->text($page['title'] ?? null) ?: $siteTitle, 70, '');
        $description = Str::limit($this->text($page['description'] ?? null) ?: $siteDescription, 160, '');
        $canonical = $request->url();
        $image = ($page['image_url'] ?? null) ? $this->absolute($request, (string) $page['image_url']) : $logoUrl;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => Str::limit($keywords, 500, ''),
            'canonical_url' => $canonical,
            'image_url' => $image,
            'icon_url' => $logoUrl,
            'robots' => ($page['indexable'] ?? true) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow',
            'structured_data' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'EducationalOrganization',
                'name' => $academy->name,
                'url' => $request->getSchemeAndHttpHost().($baseUrl === '' ? '/' : $baseUrl),
                'logo' => $logoUrl,
                'description' => Str::limit($siteDescription, 160, ''),
                'areaServed' => $cities === [] ? null : $cities,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /** An absolute, cache-busted logo URL, so share previews and favicons can fetch it. */
    public function logoUrl(Request $request, AcademyWebsite $website, string $baseUrl): ?string
    {
        $academy = $website->academy;

        return $academy->logo_path
            ? $request->getSchemeAndHttpHost().$baseUrl.'/logo?v='.($academy->updated_at->timestamp ?? 1)
            : null;
    }

    /**
     * @param  list<string>  $cities
     * @param  list<string>  $programmes
     */
    private function generatedDescription(string $name, ?string $type, array $cities, array $programmes): string
    {
        $sentence = $name.' is '.($type ? (preg_match('/^[aeiou]/i', $type) ? 'an ' : 'a ').$type.' academy' : 'an academy');
        if ($cities !== []) {
            $sentence .= ' in '.$this->list($cities);
        }
        $sentence .= '.';
        if ($programmes !== []) {
            $sentence .= ' Programmes include '.$this->list(array_slice($programmes, 0, 3)).'.';
        }

        return $sentence.' Apply online.';
    }

    /** @param list<string> $items */
    private function list(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }
        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    private function typeLabel(?string $type): ?string
    {
        $type = Str::lower(trim((string) $type));

        return $type === '' || $type === 'general' ? null : str_replace(['_', '-'], ' ', $type);
    }

    private function absolute(Request $request, string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : $request->getSchemeAndHttpHost().'/'.ltrim($url, '/');
    }

    /**
     * Website copy the academy has written itself. The editor's placeholder text is the same for
     * every academy, so it says nothing about this one.
     *
     * @param  array<string, mixed>  $sections
     */
    private function edited(array $sections, string $path): string
    {
        $value = $this->text(data_get($sections, $path));

        return $value === $this->text(data_get(AcademyWebsiteDefaults::sections(), $path)) ? '' : $value;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values)));
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? Str::squish(strip_tags(html_entity_decode($value))) : '';
    }
}
