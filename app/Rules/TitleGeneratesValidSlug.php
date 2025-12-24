<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TitleGeneratesValidSlug implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = \Illuminate\Support\Str::slug($value);

        // Title cannot be only numbers (would conflict with ID-based routes)
        if (preg_match('/^\d+$/', $slug)) {
            $fail(__('validation.title_must_contain_letters'));
        }

        // Title must generate a valid slug
        if (empty($slug)) {
            $fail(__('validation.title_must_have_alphanumeric'));
        }
    }
}
