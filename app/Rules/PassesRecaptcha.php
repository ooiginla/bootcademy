<?php

namespace App\Rules;

use App\Support\Recaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates a reCAPTCHA v3 token submitted for the given action. */
class PassesRecaptcha implements ValidationRule
{
    /** Run even when the token is missing, so omitting it fails. */
    public bool $implicit = true;

    public function __construct(private readonly string $action, private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(Recaptcha::class)->passes(is_string($value) ? $value : null, $this->action, $this->ip)) {
            $fail('We could not confirm you are human. Please refresh the page and try again.');
        }
    }
}
