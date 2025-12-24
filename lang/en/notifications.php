<?php

declare(strict_types=1);

return [

    // General notifications
    'greeting' => 'Hello!',
    'view' => 'View',

    // In-app notification titles
    'new_reply' => 'New reply',
    'new_comment' => 'New comment',
    'new_mention' => 'New mention',
    'new_membership_request' => 'New membership request',

    // In-app notification bodies
    'replied_to_your_comment_in' => 'replied to your comment in:',
    'commented_on' => 'commented on:',
    'mentioned_you_in' => 'mentioned you in:',
    'requested_to_join' => 'has requested to join',
    'manage_requests' => 'Manage requests',
    'whoops' => 'Whoops!',
    'salutation' => 'Best regards!',
    'regards' => 'Kind regards,',
    'trouble_clicking' => 'If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:',
    'all_rights_reserved' => 'All rights reserved.',
    'footer_contact_us' => 'Contact',
    'footer_legal_info' => 'Legal information',
    'footer_privacy_policy' => 'Privacy policy',
    'footer_legal_notice' => 'This email has been sent as part of the service. If you have any questions about the processing of your personal data, please refer to our',
    'footer_text' => 'If you did not request this message, you can safely ignore it.',

    // Password reset
    'password_reset' => [
        'subject' => 'Password Reset',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset Password',
        'expiration' => 'This password reset link will expire in :count minutes.',
        'no_request' => 'If you did not request a password reset, no further action is required.',
        'success' => 'Your password has been successfully reset.',
        'failed' => 'We could not reset your password. Please try again.',
    ],

    // Magic Links
    'magic_link' => [
        'subject' => 'Access Link to Your Account',
        'intro' => 'You requested a magic link to log in to your account.',
        'action' => 'Log In',
        'expiration' => 'This link will expire in 15 minutes.',
        'no_request' => 'If you did not request this link, you can safely ignore this message.',
        'sent' => 'We have sent you an access link via email.',
        'failed' => 'We could not send the access link. Please try again.',
        'invalid_token' => 'The access link is invalid or has expired.',
        'success' => 'You have successfully logged in.',
    ],

    // Email verification
    'email_verification' => [
        'subject' => 'Verify Your Email Address',
        'intro' => 'Thanks for signing up. Please verify your email address by clicking the button below.',
        'action' => 'Verify Email',
        'no_request' => 'If you did not create an account, no action is required.',
        'verified' => 'Your email has been successfully verified.',
        'already_verified' => 'Your email is already verified.',
        'sent' => 'A new verification link has been sent to your email address.',
        'verification_link_sent' => 'A new verification link has been sent to your email address.',
    ],

    // Email change request (sent to current email)
    'email_change_requested' => [
        'subject' => 'Email Change Request',
        'intro' => 'We received a request to change the email address associated with your account.',
        'new_email' => 'The requested new email address is: :email',
        'warning' => 'If you made this request, you will receive a confirmation email at the new address.',
        'not_you' => 'If you did not request this change, we recommend changing your password immediately and contacting support.',
    ],

    // Email change confirmation (sent to new email)
    'email_change_confirmation' => [
        'subject' => 'Confirm Your New Email Address',
        'intro' => 'You have requested to change your email address to this one.',
        'instructions' => 'Click the button below to confirm the change.',
        'action' => 'Confirm New Email',
        'expires' => 'This link will expire in 24 hours.',
        'not_you' => 'If you did not request this change, you can safely ignore this message.',
    ],

    // Authentication
    'auth' => [
        'failed' => 'The provided credentials are incorrect.',
        'password' => 'The provided password is incorrect.',
        'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
        'logout_success' => 'You have successfully logged out.',
        'login_success' => 'You have successfully logged in.',
        'invalid_token' => 'The authentication token is invalid.',
        'expired_token' => 'The authentication token has expired.',
        'user_not_found' => 'User not found.',
        'password_updated' => 'Password successfully updated.',
    ],

    // Account notifications
    'account' => [
        'created' => 'Your account has been successfully created.',
        'updated' => 'Your account has been successfully updated.',
        'deleted' => 'Your account has been successfully deleted.',
        'profile_updated' => 'Your profile has been successfully updated.',
    ],

    // Security changes
    'security' => [
        'password_changed' => 'Your password has been changed. If this wasn’t you, please contact us immediately.',
        'email_changed' => 'Your email address has been changed. If this wasn’t you, please contact us immediately.',
        'suspicious_activity' => 'We have detected suspicious activity on your account. If this wasn’t you, please contact us immediately.',
    ],

    // 2FA
    'two_factor' => [
        'enabled' => 'Two-factor authentication has been successfully enabled.',
        'disabled' => 'Two-factor authentication has been successfully disabled.',
        'code_sent' => 'A verification code has been sent to your device.',
        'recovery_codes' => 'Here are your recovery codes. Store them in a safe place.',
    ],

    // System notifications
    'system' => [
        'maintenance' => 'The site will be under maintenance on :date for :duration hours.',
        'update' => 'The site has been updated with new features.',
        'welcome' => 'Welcome to Renegados. Thanks for joining our community!',
    ],

    // Account approval
    'account_approval' => [
        'approved' => [
            'subject' => 'Your Account Has Been Approved',
            'intro' => 'Great news! Your account registration has been approved by our administrators.',
            'next_steps' => 'You can now log in and start participating in our community.',
            'action' => 'Log In to Your Account',
            'welcome' => 'Welcome to Renegados! We\'re excited to have you in our community.',
        ],
        'rejected' => [
            'subject' => 'Account Registration Update',
            'intro' => 'We regret to inform you that your account registration has not been approved.',
            'reason_label' => 'Reason:',
            'contact' => 'If you have any questions or would like to discuss this decision, please feel free to contact our support team.',
        ],
    ],

    // Karma and Achievements
    'karma_level_up_title' => 'New Karma Level!',
    'karma_level_up_body' => 'You\'ve reached level: :level.',
    'benefits' => 'Benefits',
    'total_karma' => 'Total karma: :karma points',
    'achievement_unlocked_title' => 'Achievement Unlocked!',
    'achievement_unlocked_congrats' => 'Congratulations!',
    'achievement_unlocked_body' => 'You\'ve unlocked: :achievement.',
    'karma_bonus' => 'Karma bonus',
    'karma_earned' => 'You\'ve earned :karma karma points',
    'view_profile' => 'View your profile',
    'keep_participating' => 'Keep participating in the community!',
    'view_achievements' => 'View your achievements',
    'anonymous_user' => 'An anonymous user',

    // Karma events
    'karma_event_types' => [
        'tide' => 'High Tide',
        'boost' => 'Boost',
        'surge' => 'Surge',
        'wave' => 'Wave',
    ],
    'karma_event_starting_subject' => 'Karma :event starting soon!',
    'karma_event_starting_intro' => 'A special karma event is about to begin.',
    'karma_event_multiplier' => 'During this event, all karma you earn will be multiplied by :multiplierx.',
    'karma_event_time' => 'The event starts on :date at :start and ends at :end.',
    'karma_event_opportunity' => 'Don\'t miss this opportunity to boost your karma!',
    'karma_event_title' => 'Karma :event!',
    'karma_event_body' => 'All karma you earn will be multiplied by :multiplierx. Starts at :time. Join in!',
    'participate_now' => 'Participate Now',

    // Agora notifications
    'agora_new_reply' => 'New reply in the Agora',
    'agora_new_mention' => 'New mention in the Agora',
    'agora_replied_to_message' => 'replied to your message in the Agora',
    'agora_mentioned_you' => 'mentioned you in the Agora',

    // Achievement motivational messages
    'achievement_motivation_welcome' => 'Welcome to the community! Keep participating to unlock more achievements.',
    'achievement_motivation_first' => 'Great start! This is the first of many achievements. Keep it up.',
    'achievement_motivation_posts' => 'Excellent! Your content enriches the community. Keep sharing.',
    'achievement_motivation_comments' => 'Fantastic! Your comments spark conversation. Keep participating.',
    'achievement_motivation_votes' => 'Great! Your participation helps highlight the best content. Keep voting.',
    'achievement_motivation_streak' => 'Impressive consistency! Your daily dedication is admirable. Keep the streak going.',
    'achievement_motivation_karma' => 'Incredible! Your karma keeps growing. Keep contributing to the community.',
    'achievement_motivation_community' => 'You\'re a pillar of the community! Your contribution makes a difference.',

    // Report notifications
    'report_resolved_subject' => 'Update on your report',
    'report_resolved_title' => 'Report addressed',
    'report_resolved_body' => 'Your report has been reviewed and appropriate measures have been taken.',
    'report_resolved_thanks' => 'Thank you for helping us keep the community safe.',
    'report_dismissed_subject' => 'Update on your report',
    'report_dismissed_title' => 'Report reviewed',
    'report_dismissed_body' => 'Your report has been reviewed and dismissed.',
    'report_dismissed_explanation' => 'After reviewing the content, we have determined it does not violate our community guidelines.',
    'report_generic_message' => 'If you have any questions, please don\'t hesitate to contact the moderation team.',

    // Admin notifications
    'new_user_registration_title' => 'New pending registration',
    'new_user_registration_body' => 'User :username has registered and is pending approval',

    // Legal reports
    'legal_report' => [
        'new_title' => 'New legal report',
        'new_body' => 'A :type report has been received (Ref: :reference)',
        'received_subject' => 'We have received your report',
        'received_intro' => 'Your report has been registered with reference number :reference.',
        'received_details' => 'Report type: :type',
        'received_timeline' => 'Our legal team will review it within 24-48 hours.',
    ],

    // Push notifications
    'push_subscription_saved' => 'Push notification subscription saved',
    'push_subscription_removed' => 'Push notification subscription removed',
    'preferences_updated' => 'Notification preferences updated',
    'snooze_activated' => 'Notifications snoozed',
    'snooze_cancelled' => 'Snooze cancelled',
    'test_sent' => 'Test notification sent',
    'no_subscriptions' => 'No active push subscriptions',

    // Test notification
    'test' => [
        'title' => 'Test notification!',
        'body' => 'Push notifications are working correctly.',
    ],

    // Push notification messages
    'push' => [
        'comment_reply_title' => 'New reply',
        'comment_reply_body' => '@:user replied: ":preview"',
        'post_comment_title' => 'New comment',
        'post_comment_body' => '@:user commented on ":post"',
        'mention_title' => 'You were mentioned',
        'mention_body' => '@:user mentioned you: ":preview"',
        'agora_reply_title' => 'Reply in Agora',
        'agora_reply_body' => '@:user replied to your message',
        'agora_mention_title' => 'Mention in Agora',
        'agora_mention_body' => '@:user mentioned you in Agora',
        'achievement_title' => 'Achievement unlocked!',
        'achievement_body' => 'You unlocked: :achievement',
    ],

    // Quiet hours summary
    'quiet_hours_summary' => [
        'title' => 'Quiet hours summary',
        'body' => '{1} You have :count new notification|[2,*] You have :count new notifications',
    ],
];
