<?php

declare(strict_types=1);

namespace App\Http\Requests;

use const PHP_INT_MAX;

use App\Models\KarmaLevel;
use App\Models\Sub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class StoreSubRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // If user has special permission to create subs, allow without karma restrictions
        if ($user->can_create_subs) {
            return true;
        }

        // Verify user has at least Collaborator level (1000 karma)
        $colaboradorLevel = KarmaLevel::where('required_karma', 1000)->first();

        if (! $colaboradorLevel) {
            return false;
        }

        // Verify user has minimum level
        if ($user->highest_level_id < $colaboradorLevel->id) {
            throw ValidationException::withMessages([
                'karma' => 'Necesitas al menos nivel Colaborador (1000 karma) para crear una subcomunidad.',
            ]);
        }

        // Get sub limit based on user level
        $maxSubsAllowed = $this->getMaxSubsForLevel($user->highest_level_id);
        $userSubsCount = Sub::where('created_by', $user->id)->count();

        if ($userSubsCount >= $maxSubsAllowed) {
            throw ValidationException::withMessages([
                'limit' => sprintf(__('validation.sub.limit_reached'), $maxSubsAllowed),
            ]);
        }

        return true;
    }

    /**
     * Get the maximum number of subs a user can create based on their level.
     */
    private function getMaxSubsForLevel(int $levelId): int
    {
        return match ($levelId) {
            3 => 1,      // Colaborador: 1 sub
            4 => 3,      // Experto: 3 subs
            5 => 5,      // Mentor: 5 subs
            default => PHP_INT_MAX, // Sabio y Leyenda: ilimitado
        };
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:subs,name'],
            'display_name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'is_private' => ['boolean'],
            'is_adult' => ['boolean'],
            'visibility' => ['in:visible,hidden,private'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.sub.name_required'),
            'name.unique' => __('validation.sub.name_unique'),
            'name.alpha_dash' => __('validation.sub.name_alpha_dash'),
            'name.min' => __('validation.sub.name_min'),
            'name.max' => __('validation.sub.name_max'),
            'display_name.required' => __('validation.sub.display_name_required'),
            'display_name.min' => __('validation.sub.display_name_min'),
            'display_name.max' => __('validation.sub.display_name_max'),
            'description.max' => __('validation.sub.description_max'),
            'rules.max' => __('validation.sub.rules_max'),
            'color.regex' => __('validation.sub.color_regex'),
            'visibility.in' => __('validation.sub.visibility_in'),
        ];
    }
}
