<?php

declare(strict_types=1);

return [
    'categories' => [
        'karma' => 'Karma',
        'seals' => 'Seals',
        'voting' => 'Voting',
        'posts' => 'Submissions',
        'lists' => 'Lists',
        'community' => 'Community',
        'account' => 'Account',
        'relationships' => 'Submission Relationships',
        'search' => 'Search',
    ],

    'karma' => [
        'what_is' => [
            'question' => 'What is karma?',
            'answer' => '<p class="mb-3">Karma is a points system that reflects your participation and contribution to the community.</p><p class="mb-3">You earn karma when other users upvote your submissions and comments.</p><p>Karma helps you <strong>unlock achievements</strong> and demonstrates your reputation on the platform.</p>',
        ],
        'how_to_earn' => [
            'question' => 'How can I earn karma?',
            'answer' => '<p class="mb-3">You can earn karma in several ways:</p><ul class="list-disc list-inside space-y-2 ml-2"><li>Posting <strong>quality content</strong> that other users upvote</li><li>Writing <strong>helpful</strong> or interesting comments</li><li><strong>Actively participating</strong> in the community</li></ul><p class="mt-3">Each upvote on your submissions and comments adds points to your karma.</p>',
        ],
    ],

    'seals' => [
        'what_are' => [
            'question' => 'What are seals?',
            'answer' => '<p class="mb-3">Seals are special marks you can award to submissions and comments to draw attention to them.</p><p class="mb-3">There are <strong>two types of seals</strong>:</p><ul class="list-disc list-inside space-y-2 ml-2"><li><strong>Recommend:</strong> To highlight content you consider very valuable</li><li><strong>Advise Against:</strong> To flag problematic or low-quality content</li></ul><p class="mt-3 text-sm"><strong>Important:</strong> Seals do NOT affect karma or frontpage position. They are just a way to express your opinion about the content.</p>',
        ],
        'how_to_use' => [
            'question' => 'How do I use seals?',
            'answer' => '<p class="mb-3">To use a seal, click the seals button that appears on each submission or comment, then select the type of seal you want to award.</p><p class="mb-3">You can remove a seal by clicking it again.</p><p class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⚠️ Seals are limited:</strong> You only have a certain amount of seals per week. Use them carefully on content that truly deserves it. Seals expire automatically after some time.</p>',
        ],
    ],

    'voting' => [
        'types' => [
            'question' => 'What types of votes exist?',
            'answer' => '<p class="mb-3">The voting system works differently for submissions and comments:</p><div class="space-y-4"><div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 pl-4 p-3"><p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📰 Submissions:</p><p class="text-sm">Simple vote: <strong>+1 point</strong>. No negative votes or types. You only vote in favor to add karma to the author.</p></div><div class="bg-gray-50 dark:bg-gray-800 border-l-4 border-gray-500 pl-4 p-3"><p class="font-semibold text-gray-800 dark:text-gray-300 mb-2">💬 Comments:</p><p class="text-sm mb-3">Detailed system with specific types:</p><div class="space-y-3 ml-2"><div><p class="font-semibold text-green-700 dark:text-green-400 text-sm mb-1">Positive Votes (+1 karma):</p><ul class="list-disc list-inside space-y-1 text-xs ml-2"><li><strong>Didactic:</strong> Educational</li><li><strong>Interesting:</strong> Catches attention</li><li><strong>Elaborate:</strong> Well-crafted</li><li><strong>Funny:</strong> Entertaining</li></ul></div><div><p class="font-semibold text-red-700 dark:text-red-400 text-sm mb-1">Negative Votes (-1 karma):</p><ul class="list-disc list-inside space-y-1 text-xs ml-2"><li><strong>Incomplete:</strong> Missing information</li><li><strong>Irrelevant:</strong> Doesn\'t contribute</li><li><strong>False:</strong> Incorrect information</li><li><strong>Out of Place:</strong> Doesn\'t belong</li></ul></div></div></div></div><p class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⚠️ Important:</strong> All votes are only available to registered users.</p>',
        ],
        'comments' => [
            'question' => 'Are votes on comments different?',
            'answer' => '<p class="mb-3">Yes, unlike submissions, comments have a <strong>more complex voting system</strong>.</p><p class="mb-3">When you vote on a comment, you must choose a <strong>specific type</strong> that describes why you\'re voting:</p><ul class="list-disc list-inside space-y-2 ml-2"><li><strong>Positive</strong> votes (+1 karma) help highlight valuable comments</li><li><strong>Negative</strong> votes (-1 karma) point out issues constructively</li><li>The vote type helps the author better understand the feedback</li></ul><p class="mt-3 text-sm bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 pl-3 py-2">This encourages quality in debates and allows constructive content moderation.</p>',
        ],
    ],

    'posts' => [
        'types' => [
            'question' => 'What types of submissions can I create?',
            'answer' => 'You can create several types of submissions: links (URLs), text, images, videos, audio, and polls. Each type has its own characteristics. For example, text submissions allow markdown formatting, while polls let other users vote between several options.',
        ],
        'anonymous' => [
            'question' => 'Can I submission anonymously?',
            'answer' => 'Yes, you can mark a submission as anonymous when creating it. When you submit anonymously, your username won\'t appear associated with the post. However, note that the content must still comply with community guidelines.',
        ],
        'frontpage' => [
            'question' => 'How does a submission reach the frontpage?',
            'answer' => '<p class="mb-3">A submission reaches the frontpage through a <strong>competitive system</strong> based on votes.</p><div class="space-y-3"><div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-3"><p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📋 Frontpage Requirements:</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Minimum:</strong> 2 positive votes</li><li><strong>Max age:</strong> Less than 48 hours since publication</li><li><strong>Status:</strong> Published (not draft)</li><li><strong>Competition:</strong> Maximum 24 submissions on frontpage (last 24h)</li></ul></div><div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-3"><p class="font-semibold text-green-800 dark:text-green-300 mb-2">🏆 Competitive System:</p><p class="text-sm mb-2">If there are already 24 submissions on frontpage, only those with <strong>more votes</strong> than current ones can enter.</p><p class="text-xs text-green-700 dark:text-green-400">Example: If all have 3+ votes, you need 4+ votes to enter.</p></div></div><p class="mt-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⏰ Important:</strong> Submissions automatically leave frontpage after 24 hours. Only positive votes count.</p>',
        ],
        'time_limits' => [
            'question' => 'Are there time limits for voting or commenting?',
            'answer' => '<p class="mb-3">Yes, there are time limits to keep the platform dynamic and focused on current content:</p><div class="space-y-3"><div class="border-l-4 border-orange-500 pl-4 bg-orange-50 dark:bg-orange-900/20 p-3"><p class="font-semibold text-orange-800 dark:text-orange-300 mb-2">🗳️ Votes:</p><p class="text-sm">You can vote during the first <strong>7 days</strong> after publication. After that time, content no longer accepts votes.</p></div><div class="border-l-4 border-purple-500 pl-4 bg-purple-50 dark:bg-purple-900/20 p-3"><p class="font-semibold text-purple-800 dark:text-purple-300 mb-2">💬 Comments:</p><p class="text-sm">You can comment during the first <strong>month (30 days)</strong> after publication. After that, the conversation closes.</p></div></div><p class="mt-3 text-xs text-gray-600 dark:text-gray-400">These limits help maintain active debates and prevent old content from being manipulated.</p>',
        ],
    ],

    'lists' => [
        'favorites' => [
            'question' => 'What are favorite lists?',
            'answer' => 'The favorites list is a personal collection where you can save submissions you like or want to revisit later. There\'s also a "Read later" list for submissions you want to review when you have time. These lists are private by default.',
        ],
        'custom' => [
            'question' => 'Can I create my own lists?',
            'answer' => 'Yes, you can create custom lists to organize submissions by topics or categories of your choice. For example, you can create lists like "Tutorials", "Important news", etc. You can add notes to each submission within a list.',
        ],
        'public' => [
            'question' => 'Can I make my lists public?',
            'answer' => 'Yes, when creating a custom list you can mark it as public. Public lists can be viewed by other users, allowing you to share collections of interesting content. Only you can add or remove submissions from your lists, even if they are public.',
        ],
    ],

    'community' => [
        'subs' => [
            'question' => 'What are subs?',
            'answer' => 'Subs are thematic subcommunities where you can submission specific content. Each sub has its own theme, rules, and moderators. You can join the subs that interest you to see their content in your personalized feed.',
        ],
        'moderation' => [
            'question' => 'How does moderation work?',
            'answer' => '<p class="mb-3">Each sub has moderators responsible for reviewing reported content and ensuring compliance with rules.</p><div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-3 mb-3"><p class="font-semibold text-red-800 dark:text-red-300 mb-2">⚖️ Fundamental principle:</p><p class="text-sm"><strong>Only content that violates the law is moderated.</strong> Content will never be moderated for ideological reasons. All ideas and debates are welcome on the platform.</p></div><div class="space-y-3"><div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-3"><p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📋 Moderation process:</p><ol class="list-decimal list-inside space-y-1 text-sm ml-2"><li>Users <strong>report</strong> illegal content (it\'s everyone\'s right and duty)</li><li>Moderators <strong>review</strong> the report</li><li>If the violation is confirmed, the <strong>content is removed</strong></li><li>A <strong>sanction</strong> is imposed based on severity and recurrence</li></ol></div><div class="bg-orange-50 dark:bg-orange-900/20 border-l-4 border-orange-500 p-3"><p class="font-semibold text-orange-800 dark:text-orange-300 mb-2">⚠️ Available sanctions:</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Hide content:</strong> Content is removed from public view</li><li><strong>Strike (warning):</strong> A warning is recorded in the user\'s history</li><li><strong>Temporary ban:</strong> Account suspension for a set period</li><li><strong>Permanent ban:</strong> Only in cases of serious recurrence</li></ul></div></div><p class="mt-3 text-xs text-gray-600 dark:text-gray-400">The sanction depends on the severity of the violation and the user\'s history. Repeat offenses result in more severe sanctions.</p>',
        ],
        'reports' => [
            'question' => 'What types of reports can I make?',
            'answer' => '<p class="mb-3">There are <strong>two reporting systems</strong> depending on the type of content:</p><div class="space-y-3"><div class="bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-500 p-3"><p class="font-semibold text-purple-800 dark:text-purple-300 mb-2">🚨 Moderation reports (illegal content):</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Spam:</strong> Repetitive or advertising content</li><li><strong>Harassment:</strong> Harassment or intimidation</li><li><strong>Inappropriate content:</strong> Unsuitable material</li><li><strong>Misinformation:</strong> Deliberate false information</li><li><strong>Hate speech:</strong> Incitement to hatred</li><li><strong>Violence:</strong> Threats or violent content</li><li><strong>Illegal content:</strong> Any law violation</li></ul><p class="text-xs mt-2">Moderators review and take immediate action.</p></div><div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-3"><p class="font-semibold text-green-800 dark:text-green-300 mb-2">⚖️ Legal reports (legal team):</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Copyright (DMCA):</strong> Copyright violation</li><li><strong>Privacy:</strong> Personal data violation</li><li><strong>Serious harassment:</strong> Cases requiring legal intervention</li><li><strong>Serious illegal content:</strong> Criminal offenses</li></ul><p class="text-xs mt-2">Legal team reviews within 24-48 hours. For DMCA reports, you must be authorized by the rights holder.</p></div></div><p class="mt-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>💡 Important:</strong> Reporting content that violates the law is everyone\'s right and duty. You help keep the platform safe and legal.</p>',
        ],
        'rules' => [
            'question' => 'Where can I see the rules?',
            'answer' => 'Each sub has its own rules that you can see on its main page. There are also general platform rules that apply to all users. It\'s important to read the rules before submitting to avoid having your content rejected.',
        ],
    ],

    'account' => [
        'privacy' => [
            'question' => 'How do I protect my privacy?',
            'answer' => '<p class="mb-3">You can control your privacy in several ways:</p><ul class="list-disc list-inside space-y-2 ml-2"><li>Submit <strong>anonymously</strong></li><li>Make your lists <strong>private</strong></li><li>Manage what information you share on your profile</li></ul><p class="mt-3 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-3"><strong>Available privacy options:</strong></p><ul class="list-disc list-inside space-y-2 ml-2 mt-2"><li><strong>Hide profile achievements:</strong> Your achievements list won\'t be visible on your public profile</li><li><strong>Hide profile comments list:</strong> Your comments list won\'t be visible on your public profile (individual comments in posts remain visible)</li></ul><p class="mt-3 text-sm text-gray-600 dark:text-gray-400"><strong>Important note:</strong> Your karma is always visible. Your email address is never publicly visible. These options can be configured in your profile preferences section.</p>',
        ],
        'delete' => [
            'question' => 'Can I delete my account?',
            'answer' => 'Yes, you can delete your account from your profile settings. When you delete your account, all your personal data will be erased. Note that your submissions and comments may remain but will appear as from a deleted user.',
        ],
        'notifications' => [
            'question' => 'How do I manage notifications?',
            'answer' => 'You can configure your notification preferences from your profile. You can choose to receive notifications for new comments on your submissions, replies to your comments, mentions, and system messages. You can also disable all notifications if you prefer.',
        ],
    ],

    'relationships' => [
        'what_are' => [
            'question' => 'What are submission relationships?',
            'answer' => 'Submission relationships allow you to link related submissions. For example, you can mark a submission as a "continuation" of another, or as "related to" another topic. This helps create conversation threads and allows following topics that develop across multiple submissions.',
        ],
        'types' => [
            'question' => 'What types of relationships exist?',
            'answer' => 'There are several types of relationships: "Continuation" for submissions that follow a story, "Related to" for similar topics, "Update" for new versions, "Responds to" for replies, and more. Users can vote on relationships to validate if they are appropriate.',
        ],
    ],

    'search' => [
        'how_to' => [
            'question' => 'How do I search for content?',
            'answer' => 'You can use the search bar at the top to find submissions by title, content, or author. Search supports keywords and phrases. You can also filter results by content type, date, sub, and more.',
        ],
        'filters' => [
            'question' => 'What search filters are available?',
            'answer' => 'You can filter by content type (text, image, video, audio), by sub, by date range, by minimum score, and by author. You can also sort results by relevance, date, or popularity. Filters help you find exactly what you\'re looking for.',
        ],
    ],
];
