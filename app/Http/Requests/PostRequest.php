<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\TitleGeneratesValidSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        // Detect if this is an update
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        // If only changing status, don't validate other fields
        $isStatusOnlyUpdate = $this->isMethod('PATCH') || ($this->isMethod('PUT') && $this->has('status') && count($this->all()) <= 2);

        $rules = [
            'title' => $isStatusOnlyUpdate ? 'sometimes|string|min:5|max:255' : ($isUpdate ? ['sometimes', 'string', 'min:5', 'max:255', new TitleGeneratesValidSlug()] : ['required', 'string', 'min:5', 'max:255', new TitleGeneratesValidSlug()]),
            'content' => 'nullable|string|max:50000',
            'url' => ['nullable', 'url', 'max:2000', 'regex:/^https?:\/\//i'],
            'thumbnail_url' => ['nullable', 'url', 'max:2000', 'regex:/^https?:\/\//i'],
            'language_code' => 'nullable|string|size:2',
            'source' => 'nullable|string|max:100',
            'content_type' => 'nullable|string|in:link,text,video,audio,poll,image',
            'media_provider' => 'nullable|string|max:50',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'poll_options' => 'nullable|array|min:2|max:10',
            'poll_options.*' => 'string|max:255',
            'expires_at' => 'nullable|date',
            'allow_multiple_options' => 'nullable|boolean',
            'is_anonymous' => 'nullable|boolean',
            'is_nsfw' => 'nullable|boolean',
            'is_original' => 'nullable|boolean',
            'status' => 'nullable|string|in:published,draft,pending,hidden',
            'sub_id' => 'nullable|integer|exists:subs,id',
            'should_federate' => 'nullable|boolean',
        ];

        if (! $isStatusOnlyUpdate && ! $isUpdate) {
            // Only apply these validations on post creation
            if ($this->input('content_type') === 'text') {
                $rules['content'] = 'required|string|max:50000';
            } elseif ($this->input('content_type') === 'poll') {
                $rules['poll_options'] = 'required|array|min:2|max:10';
                $rules['content'] = 'nullable|string|max:50000';
            } elseif (in_array($this->input('content_type'), ['link', 'video', 'audio', 'image'])) {
                $rules['url'] = ['required', 'url', 'max:2000', 'regex:/^https?:\/\//i'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => __('validation.post.title_required'),
            'title.min' => __('validation.post.title_min', ['min' => ':min']),
            'title.max' => __('validation.post.title_max', ['max' => ':max']),
            'url.url' => __('validation.post.url_invalid'),
            'url.required' => __('validation.post.url_required'),
            'content.required' => __('validation.post.content_required'),
            'content_type.in' => __('validation.post.content_type_in'),
        ];
    }
}
