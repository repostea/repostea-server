<?php

declare(strict_types=1);

return [
    'types' => [
        'continuation' => 'Continuation',
        'correction' => 'Correction',
        'update' => 'Update',
        'reply' => 'Reply',
        'related' => 'Related',
        'duplicate' => 'Duplicate',
    ],
    'descriptions' => [
        'continuation' => 'This post is a continuation of another post',
        'correction' => 'This post corrects information from another post',
        'update' => 'This post updates or provides new information about another post',
        'reply' => 'This post is a response to another post',
        'related' => 'This post is related to another post',
        'duplicate' => 'This post is a duplicate of another post',
    ],
    'errors' => [
        'self_relation' => 'A post cannot be related to itself',
        'only_author_can_create' => 'Only the author of this post can mark it as a continuation or correction',
        'cannot_reply_own_post' => 'You cannot reply to your own post',
        'already_exists' => 'This relationship already exists',
        'create_failed' => 'Failed to create relationship',
        'delete_failed' => 'Failed to delete relationship',
        'not_found' => 'Relationship not found for this post',
        'no_permission' => 'You do not have permission to delete this relationship',
    ],
    'success' => [
        'created' => 'Relationship created successfully',
        'deleted' => 'Relationship deleted successfully',
    ],
];
