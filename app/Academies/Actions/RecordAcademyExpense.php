<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyExpense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecordAcademyExpense
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request): AcademyExpense
    {
        $category = ExpenseCategory::query()->where('academy_id', $academy->id)->findOrFail($data['expense_category_id']);
        $vendor = isset($data['expense_vendor_id'])
            ? ExpenseVendor::query()->where('academy_id', $academy->id)->findOrFail($data['expense_vendor_id'])
            : null;
        $typedVendor = $data['vendor'] ?? null;
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($academy, $actor, $data, $request, $category, $vendor, $typedVendor, &$storedPaths): AcademyExpense {
                $expense = AcademyExpense::query()->create([
                    'academy_id' => $academy->id,
                    'academy_cycle_id' => $data['academy_cycle_id'] ?? null,
                    'expense_category_id' => $category->id,
                    'expense_vendor_id' => $typedVendor ? null : $vendor?->id,
                    'branch_id' => $data['branch_id'] ?? null,
                    'category' => $category->name,
                    'description' => $data['description'],
                    'vendor' => $typedVendor ?: $vendor?->name,
                    'reference' => $data['reference'] ?? null,
                    'currency' => $academy->currency,
                    'amount_minor' => Money::toMinor((string) $data['amount']),
                    'incurred_on' => $data['incurred_on'],
                    'created_by_user_id' => $actor->id,
                ]);

                /** @var UploadedFile $file */
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store("academies/{$academy->id}/expenses/{$expense->id}", 'local');
                    $storedPaths[] = $path;
                    ExpenseAttachment::query()->create([
                        'academy_id' => $academy->id,
                        'academy_expense_id' => $expense->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'uploaded_by_user_id' => $actor->id,
                    ]);
                }

                $this->audit->record('academy.expense.recorded', $actor, $academy, $expense, [
                    'amount_minor' => $expense->amount_minor,
                    'category_id' => $category->id,
                    'branch_id' => $expense->branch_id,
                    'attachment_count' => count($storedPaths),
                ], $request);

                return $expense;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }
}
