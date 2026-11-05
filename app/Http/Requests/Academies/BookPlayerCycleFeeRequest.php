<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Player;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookPlayerCycleFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');
        $context = app(AcademyContext::class);
        abort_unless($player instanceof Player && $player->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();

        return ['fee_id' => ['required', 'integer', Rule::exists('academy_cycle_fees', 'id')->where(fn (Builder $query) => $query->where('academy_id', $academyId)->where('is_active', true))]];
    }
}
