<?php

declare(strict_types=1);

return [
    // Custom app validation rules
    'title_must_contain_letters' => 'The title must contain at least one letter.',
    'title_must_have_alphanumeric' => 'The title must contain valid alphanumeric characters.',
    'safe_external_url' => 'The URL must point to a public external server.',

    'sub' => [
        'name_required' => 'The subcommunity name is required.',
        'name_unique' => 'A subcommunity with that name already exists.',
        'name_alpha_dash' => 'The name can only contain letters, numbers, dashes and underscores.',
        'name_min' => 'The name must be at least 3 characters.',
        'name_max' => 'The name cannot exceed 50 characters.',
        'display_name_required' => 'The display name is required.',
        'display_name_min' => 'The display name must be at least 3 characters.',
        'display_name_max' => 'The display name cannot exceed 100 characters.',
        'description_max' => 'The description cannot exceed 500 characters.',
        'rules_max' => 'The rules cannot exceed 2000 characters.',
        'color_regex' => 'The color must be a valid hexadecimal code (e.g., #3B82F6).',
        'visibility_in' => 'The visibility must be: visible, hidden or private.',
        'limit_reached' => 'You have reached the limit of %d subcommunity(ies) for your level. Level up to create more.',
    ],

    'post' => [
        'title_required' => 'The title is required',
        'title_min' => 'The title must be at least :min characters',
        'title_max' => 'The title cannot exceed :max characters',
        'url_invalid' => 'The URL is invalid',
        'url_required' => 'The URL is required for this type of content',
        'content_required' => 'Content is required for text posts',
        'category_required' => 'You must select a category',
        'category_exists' => 'The selected category does not exist',
        'content_type_in' => 'The content type must be link, text, video, audio, image or poll',
    ],
];
