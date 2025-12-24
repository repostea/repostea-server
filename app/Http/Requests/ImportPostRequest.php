<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ImportPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // We authorize through the auth:sanctum middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:255',
            'url' => 'nullable|url',
            'content' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'source' => 'nullable|string|max:255',
            'source_name' => 'nullable|string|max:255',
            'source_url' => 'nullable|url',
            'external_source' => 'nullable|string|max:255',
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
            'title.required' => 'The title is required',
            'title.min' => 'The title must have at least :min characters',
            'title.max' => 'The title cannot exceed :max characters',
            'url.url' => 'The URL must have a valid format',
            'thumbnail_url.url' => 'The image URL must have a valid format',
            'source_url.url' => 'The source URL must have a valid format',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Verify there's at least URL or content
            if (empty($this->url) && empty($this->content)) {
                $validator->errors()->add('url', 'You must provide either a URL or content');
                $validator->errors()->add('content', 'You must provide either a URL or content');
            }
        });
    }
}
