<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2',
            'content_type' => 'nullable|string|in:text,link,video,audio,poll',
            'is_featured' => 'nullable|in:true,false,1,0',
            'search_in_comments' => 'nullable|in:true,false,1,0',
            'sort_by' => 'nullable|string|in:created_at,votes_count,comment_count,lastActive',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Map 'q' to 'search' for the service layer
        if ($this->has('q')) {
            $this->merge(['search' => $this->input('q')]);
        }
    }
}
