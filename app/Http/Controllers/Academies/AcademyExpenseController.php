<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\RecordAcademyExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveAcademyExpenseRequest;
use App\Http\Requests\Academies\SaveExpenseCategoryRequest;
use App\Http\Requests\Academies\SaveExpenseVendorRequest;
use App\Models\AcademyExpense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademyExpenseController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        $academy = $context->academy();
        $categoryId = $request->integer('category_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $cycleId = $request->integer('cycle_id') ?: null;
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_from')) ? (string) $request->query('date_from') : null;
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_to')) ? (string) $request->query('date_to') : null;
        $activeTab = in_array($request->query('tab'), ['categories', 'vendors'], true) ? (string) $request->query('tab') : 'expenses';

        $query = $academy->expenses()->getQuery()->with(['categoryDefinition:id,name', 'branch:id,name', 'cycle:id,name', 'attachments:id,academy_expense_id,original_name,size_bytes']);
        $this->applyFilters($query, $categoryId, $branchId, $cycleId, $dateFrom, $dateTo);
        $summaryQuery = clone $query;

        return Inertia::render('academies/expenses', [
            'expenses' => $query->orderByDesc('incurred_on')->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (AcademyExpense $expense): array => [
                'id' => $expense->id,
                'incurred_on' => $expense->incurred_on->toDateString(),
                'category' => $expense->categoryDefinition?->name ?? str_replace('_', ' ', $expense->category),
                'description' => $expense->description,
                'vendor' => $expense->vendor,
                'reference' => $expense->reference,
                'branch_name' => $expense->branch?->name,
                'cycle_name' => $expense->cycle?->name,
                'currency' => $expense->currency,
                'amount' => Money::fromMinor($expense->amount_minor),
                'source' => $expense->academy_invoice_write_off_id ? 'write_off' : ($expense->referral_reward_id ? 'referral_reward' : 'manual'),
                'attachments' => $expense->attachments->map(fn (ExpenseAttachment $attachment): array => [
                    'id' => $attachment->id, 'name' => $attachment->original_name, 'size' => $attachment->size_bytes,
                ]),
            ]),
            'summary' => [
                'count' => (clone $summaryQuery)->count(),
                'total' => Money::fromMinor((int) (clone $summaryQuery)->sum('amount_minor')),
                'manual' => Money::fromMinor((int) (clone $summaryQuery)->whereNull('academy_invoice_write_off_id')->whereNull('referral_reward_id')->sum('amount_minor')),
                'write_offs' => Money::fromMinor((int) (clone $summaryQuery)->whereNotNull('academy_invoice_write_off_id')->sum('amount_minor')),
                'referral_rewards' => Money::fromMinor((int) (clone $summaryQuery)->whereNotNull('referral_reward_id')->sum('amount_minor')),
                'currency' => $academy->currency,
            ],
            'categories' => $academy->expenseCategories()->withCount('expenses')->orderBy('name')->get(['id', 'name', 'is_active']),
            'vendors' => $academy->expenseVendors()->withCount('expenses')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'address']),
            'branches' => $academy->branches()->orderBy('name')->get(['id', 'name']),
            'cycles' => $academy->cycles()->orderByDesc('starts_on')->get(['id', 'name']),
            'today' => now($academy->timezone)->toDateString(),
            'active_tab' => $activeTab,
            'filters' => ['category_id' => $categoryId, 'branch_id' => $branchId, 'cycle_id' => $cycleId, 'date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    public function store(SaveAcademyExpenseRequest $request, AcademyContext $context, RecordAcademyExpense $record): RedirectResponse
    {
        $record->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense recorded.']);

        return back();
    }

    public function storeCategory(SaveExpenseCategoryRequest $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $category = $context->academy()->expenseCategories()->create(['name' => $request->validated('name'), 'is_active' => true]);
        $audit->record('academy.expense-category.created', $request->user(), $context->academy(), $category, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category created.']);

        return back();
    }

    public function updateCategory(SaveExpenseCategoryRequest $request, ExpenseCategory $category, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $oldName = $category->name;
        $category->update(['name' => $request->validated('name')]);
        $audit->record('academy.expense-category.updated', $request->user(), $context->academy(), $category, ['old_name' => $oldName, 'new_name' => $category->name], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category updated.']);

        return back();
    }

    public function destroyCategory(Request $request, ExpenseCategory $category, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($category->academy_id === $context->id(), 404);
        if ($category->expenses()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category cannot be deleted because expenses are recorded under it.']);
        }
        $audit->record('academy.expense-category.deleted', $request->user(), $context->academy(), $category, ['name' => $category->name], $request);
        $category->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category deleted.']);

        return back();
    }

    public function storeVendor(SaveExpenseVendorRequest $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $vendor = $context->academy()->expenseVendors()->create($request->validated());
        $audit->record('academy.expense-vendor.created', $request->user(), $context->academy(), $vendor, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vendor created.']);

        return back();
    }

    public function updateVendor(SaveExpenseVendorRequest $request, ExpenseVendor $vendor, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $before = $vendor->only(['name', 'email', 'phone', 'address']);
        $vendor->update($request->validated());
        $audit->record('academy.expense-vendor.updated', $request->user(), $context->academy(), $vendor, ['before' => $before], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vendor updated.']);

        return back();
    }

    public function destroyVendor(Request $request, ExpenseVendor $vendor, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($vendor->academy_id === $context->id(), 404);
        DB::transaction(function () use ($request, $vendor, $context, $audit): void {
            $audit->record('academy.expense-vendor.deleted', $request->user(), $context->academy(), $vendor, ['name' => $vendor->name], $request);
            $vendor->expenses()->update(['expense_vendor_id' => null]);
            $vendor->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vendor deleted. Historical expense names were preserved.']);

        return back();
    }

    public function attachment(ExpenseAttachment $attachment, AcademyContext $context): StreamedResponse
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($attachment->academy_id === $context->id(), 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function applyFilters(Builder $query, ?int $categoryId, ?int $branchId, ?int $cycleId, ?string $dateFrom, ?string $dateTo): void
    {
        $query->when($categoryId, fn (Builder $query) => $query->where('expense_category_id', $categoryId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($cycleId, fn (Builder $query) => $query->where('academy_cycle_id', $cycleId))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('incurred_on', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('incurred_on', '<=', $dateTo));
    }
}
