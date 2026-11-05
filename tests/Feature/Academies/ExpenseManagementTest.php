<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyExpense;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_categories_and_record_an_expense_with_private_evidence(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->owner();
        $branch = Branch::factory()->for($academy)->create();
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2026 Cycle', 'type' => 'fixed',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);

        $this->asAcademy($owner, $academy)->post(route('academy.expense-categories.store'), [
            'name' => 'Equipment',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Expense category created.');
        $category = ExpenseCategory::query()->where('academy_id', $academy->id)->sole();
        $this->asAcademy($owner, $academy)->post(route('academy.expense-vendors.store'), [
            'name' => 'Sports Market', 'email' => 'sales@example.test', 'phone' => '08010000000',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Vendor created.');
        $vendor = ExpenseVendor::query()->where('academy_id', $academy->id)->sole();

        $this->asAcademy($owner, $academy)->post(route('academy.expenses.store'), [
            'expense_category_id' => $category->id,
            'expense_vendor_id' => $vendor->id,
            'branch_id' => $branch->id,
            'academy_cycle_id' => $cycle->id,
            'description' => 'Training cones and bibs',
            'reference' => 'RCPT-101',
            'amount' => '1250.50',
            'incurred_on' => '2026-11-01',
            'attachments' => [UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf')],
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Expense recorded.');

        $expense = AcademyExpense::query()->where('academy_id', $academy->id)->sole();
        $this->assertSame(125050, $expense->amount_minor);
        $this->assertSame($category->id, $expense->expense_category_id);
        $this->assertSame($vendor->id, $expense->expense_vendor_id);
        $this->assertSame('Sports Market', $expense->vendor);
        $this->assertSame($branch->id, $expense->branch_id);
        $attachment = ExpenseAttachment::query()->where('academy_expense_id', $expense->id)->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.expense.recorded']);

        $this->asAcademy($owner, $academy)->get(route('academy.expense-attachments.show', $attachment))
            ->assertOk()->assertHeader('cache-control', 'no-store, private');
        [$foreignOwner, $foreignAcademy] = $this->owner();
        $this->asAcademy($foreignOwner, $foreignAcademy)
            ->get(route('academy.expense-attachments.show', $attachment))
            ->assertNotFound();
    }

    public function test_categories_and_vendors_can_be_managed_without_rewriting_expense_history(): void
    {
        [$owner, $academy] = $this->owner();
        $unused = ExpenseCategory::query()->create(['academy_id' => $academy->id, 'name' => 'Unused']);
        $used = ExpenseCategory::query()->create(['academy_id' => $academy->id, 'name' => 'Equipment']);
        $vendor = ExpenseVendor::query()->create(['academy_id' => $academy->id, 'name' => 'Old Vendor']);
        $expense = AcademyExpense::query()->create([
            'academy_id' => $academy->id, 'expense_category_id' => $used->id, 'expense_vendor_id' => $vendor->id,
            'category' => 'Equipment', 'description' => 'Balls', 'vendor' => 'Old Vendor', 'currency' => 'NGN',
            'amount_minor' => 100000, 'incurred_on' => '2026-11-01', 'created_by_user_id' => $owner->id,
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.expense-categories.update', $used), ['name' => 'Training equipment'])
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Expense category updated.');
        $this->asAcademy($owner, $academy)->delete(route('academy.expense-categories.destroy', $used))
            ->assertSessionHasErrors('category');
        $this->asAcademy($owner, $academy)->delete(route('academy.expense-categories.destroy', $unused))
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Expense category deleted.');
        $this->assertDatabaseMissing('expense_categories', ['id' => $unused->id]);

        $this->asAcademy($owner, $academy)->patch(route('academy.expense-vendors.update', $vendor), [
            'name' => 'New Vendor', 'email' => 'new@example.test',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Vendor updated.');
        $this->asAcademy($owner, $academy)->delete(route('academy.expense-vendors.destroy', $vendor))
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Vendor deleted. Historical expense names were preserved.');
        $expense->refresh();
        $this->assertNull($expense->expense_vendor_id);
        $this->assertSame('Old Vendor', $expense->vendor);
    }

    public function test_expense_register_filters_and_summary_are_tenant_scoped(): void
    {
        [$owner, $academy] = $this->owner();
        $branch = Branch::factory()->for($academy)->create();
        $otherBranch = Branch::factory()->for($academy)->create();
        $category = ExpenseCategory::query()->create(['academy_id' => $academy->id, 'name' => 'Transport']);
        AcademyExpense::query()->create([
            'academy_id' => $academy->id, 'expense_category_id' => $category->id, 'branch_id' => $branch->id,
            'category' => 'Transport', 'description' => 'Team bus', 'currency' => 'NGN', 'amount_minor' => 500000,
            'incurred_on' => '2026-11-01', 'created_by_user_id' => $owner->id,
        ]);
        AcademyExpense::query()->create([
            'academy_id' => $academy->id, 'expense_category_id' => $category->id, 'branch_id' => $otherBranch->id,
            'category' => 'Transport', 'description' => 'Other trip', 'currency' => 'NGN', 'amount_minor' => 200000,
            'incurred_on' => '2026-11-02', 'created_by_user_id' => $owner->id,
        ]);
        [, $foreignAcademy] = $this->owner();
        AcademyExpense::query()->create([
            'academy_id' => $foreignAcademy->id, 'category' => 'Hidden', 'description' => 'Foreign expense',
            'currency' => 'NGN', 'amount_minor' => 900000, 'incurred_on' => '2026-11-01',
        ]);

        $this->asAcademy($owner, $academy)->get(route('academy.expenses.index', ['branch_id' => $branch->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/expenses')->has('expenses.data', 1)
            ->where('expenses.data.0.description', 'Team bus')
            ->where('summary.total', '5000.00')->where('summary.count', 1));
    }

    public function test_cross_tenant_values_and_unauthorized_users_cannot_manage_expenses(): void
    {
        [$owner, $academy] = $this->owner();
        [$foreignOwner, $foreignAcademy] = $this->owner();
        $foreignBranch = Branch::factory()->for($foreignAcademy)->create();
        $foreignCategory = ExpenseCategory::query()->create(['academy_id' => $foreignAcademy->id, 'name' => 'Foreign']);
        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();

        $this->asAcademy($owner, $academy)->post(route('academy.expenses.store'), [
            'expense_category_id' => $foreignCategory->id,
            'branch_id' => $foreignBranch->id,
            'description' => 'Should fail', 'amount' => '100.00', 'incurred_on' => '2026-11-01',
        ])->assertSessionHasErrors(['expense_category_id', 'branch_id']);
        $this->assertDatabaseMissing('academy_expenses', ['academy_id' => $academy->id]);

        $this->asAcademy($coach, $academy)->get(route('academy.expenses.index'))->assertForbidden();
        $this->asAcademy($coach, $academy)->post(route('academy.expense-categories.store'), ['name' => 'Blocked'])->assertForbidden();
        $this->asAcademy($foreignOwner, $foreignAcademy)->get(route('academy.expenses.index'))->assertOk();
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
