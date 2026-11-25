<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\DeleteAcademyResource;
use App\Academies\Actions\StoreAcademyResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\StoreAcademyResourceRequest;
use App\Models\AcademyResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademyResourceController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        Gate::authorize('manage', $context->academy());
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($validated['search'] ?? ''));

        $resources = AcademyResource::query()
            ->where('academy_id', $context->id())
            ->when($search !== '', fn ($query) => $query->where('original_name', 'like', '%'.$search.'%'))
            ->latest()
            ->simplePaginate(24)
            ->withQueryString();

        return Inertia::render('academies/resources', [
            'resources' => [
                'data' => $resources->getCollection()->map(fn (AcademyResource $resource): array => [
                    'id' => $resource->public_id,
                    'name' => $resource->original_name,
                    'mime_type' => $resource->mime_type,
                    'extension' => $resource->extension,
                    'size_bytes' => $resource->size_bytes,
                    'public_url' => route('public.resources.show', $resource),
                    'uploaded_at' => $resource->created_at?->timezone($context->academy()->timezone)->toIso8601String(),
                    'is_image' => str_starts_with($resource->mime_type, 'image/'),
                ])->values(),
                'from' => $resources->firstItem(),
                'to' => $resources->lastItem(),
                'next_page_url' => $resources->nextPageUrl(),
                'prev_page_url' => $resources->previousPageUrl(),
            ],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(
        StoreAcademyResourceRequest $request,
        AcademyContext $context,
        StoreAcademyResource $storeResource,
    ): RedirectResponse {
        $file = $request->file('resource');
        abort_unless($file !== null, 422);

        $storeResource->handle($context->academy(), $request->user(), $file, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resource uploaded.']);

        return back();
    }

    public function destroy(
        Request $request,
        AcademyResource $resource,
        AcademyContext $context,
        DeleteAcademyResource $deleteResource,
    ): RedirectResponse {
        abort_unless($resource->academy_id === $context->id(), 404);
        Gate::authorize('manage', $context->academy());

        $deleteResource->handle($resource, $request->user(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resource deleted.']);

        return back();
    }
}
