<?php

declare(strict_types=1);

return [
    // Authentication
    'auth' => [
        'failed' => 'These credentials do not match our records.',
        'logout_success' => 'Successfully logged out.',
        'login_required' => 'You must be logged in to import content.',
        'magic_link_sent' => 'Magic link sent to your email.',
        'user_not_found' => 'User not found.',
    ],

    // Passwords
    'passwords' => [
        'updated' => 'Password updated successfully.',
        'update_error' => 'Error updating password.',
    ],

    // Comments
    'comments' => [
        'deleted' => 'Comment deleted successfully.',
        'too_old' => 'This post is too old to receive comments.',
        'hidden_by_user' => 'This user has chosen to hide their comments list.',
    ],

    // Agora
    'agora' => [
        'message_not_found' => 'Message not found.',
        'unauthorized' => 'Unauthorized.',
        'deleted' => 'Message deleted successfully.',
        'vote_registered' => 'Vote registered successfully.',
        'vote_removed' => 'Vote removed successfully.',
        'expiry_too_soon' => 'The chosen duration would make the message expire immediately. Please choose a longer duration.',
    ],

    // Content
    'content' => [
        'attached_image' => '[attached image]',
    ],

    // External Content
    'external' => [
        'rss_error' => 'Error fetching RSS feed.',
        'data_error' => 'Error fetching data from Mediatize.',
        'source_not_implemented' => 'Source not implemented in proxy.',
        'mediatize_fetch_error' => 'Error fetching Mediatize news.',
        'mediatize_processing_error' => 'Error processing Mediatize RSS feed.',
        'techcrunch_fetch_error' => 'Error fetching TechCrunch news.',
        'techcrunch_processing_error' => 'Error processing TechCrunch RSS feed.',
        'import_error' => 'Error importing external content.',
        'no_title' => 'No title',
    ],

    // Media
    'media' => [
        'youtube_id_extraction_error' => 'Could not extract YouTube video ID.',
        'vimeo_id_extraction_error' => 'Could not extract Vimeo video ID.',
        'avatar_uploaded' => 'Avatar uploaded successfully.',
        'avatar_deleted' => 'Avatar deleted successfully.',
        'avatar_upload_failed' => 'Failed to upload avatar.',
        'avatar_validation_error' => 'Invalid avatar image.',
        'thumbnail_uploaded' => 'Thumbnail uploaded successfully.',
        'thumbnail_deleted' => 'Thumbnail deleted successfully.',
        'thumbnail_upload_failed' => 'Failed to upload thumbnail.',
        'thumbnail_download_failed' => 'Failed to download and upload thumbnail.',
        'thumbnail_validation_error' => 'Invalid thumbnail image.',
        'image_uploaded' => 'Image uploaded successfully.',
        'image_upload_failed' => 'Failed to upload image.',
        'image_validation_error' => 'Invalid image.',
    ],

    // Posts
    'posts' => [
        'not_found' => 'Post not found.',
        'removed_or_not_found' => 'This content has been removed or does not exist.',
        'cannot_change_hidden_status' => 'You cannot change the status of a post that has been hidden by a moderator.',
        'cannot_delete_with_comments_after_hours' => 'This post has comments and it has been more than :hours hours since its creation. If you need to delete it, please contact an administrator.',
        'no_permission_to_delete' => 'You do not have permission to delete this post.',
        'deleted' => 'Post deleted successfully.',
        'imported_successfully' => 'Content imported successfully.',
        'import_error' => 'Error importing content.',
        'update_error' => 'Error updating post.',
        'url_already_imported' => 'This URL has already been imported.',
        'view_registered' => 'View registered successfully.',
        'view_already_registered' => 'View already registered recently.',
        'no_post_ids' => 'No post IDs provided.',
        'invalid_post_ids' => 'Invalid post IDs format.',
    ],

    // Users
    'users' => [
        'not_found' => 'User not found.',
        'account_deleted' => 'This user account has been deleted.',
        'not_found_or_deleted' => 'User not found or deleted.',
    ],

    // Profile
    'profile' => [
        'email_change_disabled' => 'Email change temporarily disabled.',
        'email_not_allowed' => 'Email change is not allowed at this time.',
        'updated' => 'Profile updated successfully.',
    ],

    // Email Change
    'email_change' => [
        'same_email' => 'The new email is the same as the current one.',
        'verification_sent' => 'A verification link has been sent to the new email address.',
        'request_error' => 'Error requesting email change.',
        'invalid_token' => 'The verification link is invalid.',
        'token_expired' => 'The verification link has expired. Please request a new change.',
        'success' => 'Your email has been changed successfully.',
        'confirm_error' => 'Error confirming email change.',
        'no_pending_change' => 'There is no pending email change.',
        'cancelled' => 'The email change has been cancelled.',
        'cancel_error' => 'Error cancelling email change.',
    ],

    // Validation
    'validation' => [
        'invalid_data' => 'The provided data is invalid.',
    ],

    // Settings
    'settings' => [
        'updated' => 'User settings updated successfully.',
    ],

    // Karma
    'karma' => [
        'streak_updated' => 'Streak updated successfully.',
        'streak_update_failed' => 'Failed to update streak.',
        'event_created' => 'Karma event created successfully.',
        'event_updated' => 'Karma event updated successfully.',
        'event_deleted' => 'Karma event deleted successfully.',
        'event_already_started' => 'Cannot notify for an event that has already started.',
        'notifications_sent' => 'Notifications sent successfully.',
        'notifications_failed' => 'Failed to send notifications.',
    ],

    // Polls
    'polls' => [
        'not_a_poll' => 'This post is not a poll.',
        'options_not_found' => 'Poll options not found.',
        'invalid_option' => 'Invalid poll option.',
        'expired' => 'This poll has expired.',
        'already_voted' => 'You have already voted for this option.',
        'vote_recorded' => 'Vote recorded successfully.',
        'vote_removed' => 'Vote removed successfully.',
        'no_votes_to_remove' => 'No votes found to remove.',
        'login_required_vote' => 'You must be logged in to vote.',
        'login_required_remove' => 'You must be logged in to remove votes.',
        'error_loading' => 'Error loading poll results.',
        'error_voting' => 'Error recording vote.',
        'error_removing' => 'Error removing vote.',
    ],

    // Votes
    'votes' => [
        'invalid_type' => 'Invalid vote type for this value.',
        'invalid_type_allowed' => 'The provided vote type is not valid. Allowed types: :types',
        'already_voted' => 'You have already voted with this type.',
        'updated' => 'Vote updated.',
        'recorded' => 'Vote recorded.',
        'removed' => 'Vote removed.',
        'too_old' => 'This post is too old to receive votes.',
        'cannot_update_others' => 'You cannot update another user\'s vote.',
        'cannot_delete_others' => 'You cannot delete another user\'s vote.',
    ],

    // Saved Lists
    'savedlists' => [
        'type_exists' => 'A list of this type already exists.',
        'cannot_change_special_type' => 'Cannot change type of special lists.',
        'cannot_delete_special' => 'Cannot delete special system lists.',
        'deleted' => 'List deleted successfully.',
        'post_already_in_list' => 'Post is already in this list.',
        'post_added' => 'Post added to list successfully.',
        'post_removed' => 'Post removed from list successfully.',
        'removed_from_favorites' => 'Post removed from favorites.',
        'added_to_favorites' => 'Post added to favorites.',
        'removed_from_read_later' => 'Post removed from read later.',
        'added_to_read_later' => 'Post added to read later.',
        'post_not_in_list' => 'Post not found in this list.',
        'notes_updated' => 'Notes updated successfully.',
        'cannot_clear_special' => 'Cannot clear special system lists.',
        'cleared' => 'List cleared successfully.',
    ],

    // URL Validation
    'url_validation' => [
        'not_allowed' => 'The provided URL is not allowed.',
    ],

    // Errors
    'errors' => [
        'generic' => 'An error occurred. Please try again.',
    ],

    // Notifications
    'notifications' => [
        'magic_link' => [
            'invalid_token' => 'Invalid or expired token.',
        ],
        'not_found' => 'Notification not found.',
        'marked_as_read' => 'Notification marked as read.',
        'all_marked_as_read' => 'All notifications marked as read.',
        'deleted' => 'Notification deleted successfully.',
        'all_deleted' => 'All notifications deleted successfully.',
        'older_deleted' => 'Notifications older than :days days deleted successfully.',
        'invalid_category' => 'Invalid category.',
        'view_timestamp_updated' => 'View timestamp updated.',
    ],

    // Admin
    'admin' => [
        'backup_created' => 'Backup created successfully.',
        'backup_failed' => 'Failed to create backup.',
        'invalid_database' => 'Invalid database specified.',
        'cache_clear_error' => 'Error clearing cache.',
        'command_error' => 'Error executing command.',
        'notification_error' => 'Error sending notification email.',
    ],

    // Sessions
    'sessions' => [
        'revoked' => 'Session revoked successfully.',
        'all_revoked' => ':count sessions revoked successfully.',
        'not_found' => 'Session not found.',
        'cannot_revoke_current' => 'Cannot revoke current session.',
    ],

    // Email footer
    'All rights reserved.' => 'All rights reserved.',
    'If you have any questions, contact us at' => 'If you have any questions, contact us at',

    // Email template defaults
    'Hello!' => 'Hello!',
    'Whoops!' => 'Whoops!',
    'Regards,' => 'Regards,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:' => "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your web browser:",
];
