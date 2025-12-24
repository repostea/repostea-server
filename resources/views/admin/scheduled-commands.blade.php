@extends('admin.layout')

@section('title', 'Scheduled Commands')
@section('page-title', 'Scheduled Commands')

@section('content')
<div class="bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b border-gray-200">
        <p class="text-sm text-gray-600">
            Scheduled commands that run automatically in the system.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Task</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Frequency</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Command</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @php
                $commands = [
                    // === MBIN SYNC ===
                    [
                        'title' => 'Mbin Content Sync',
                        'description' => 'Imports and syncs posts, comments and votes from Mbin (last 24 hours)',
                        'frequency' => 'Every minute',
                        'frequency_color' => 'blue',
                        'command' => 'mbin:import --all --sync --hours=24 --no-interaction',
                        'category' => 'Mbin Sync',
                    ],
                    [
                        'title' => 'Sync User Avatars from Mbin',
                        'description' => 'Downloads and synchronizes user avatar images from Mbin server',
                        'frequency' => 'Every hour',
                        'frequency_color' => 'blue',
                        'command' => 'mbin:sync-avatars',
                        'category' => 'Mbin Sync',
                    ],
                    [
                        'title' => 'Sync Media from Mbin',
                        'description' => 'Synchronizes all media (images, thumbnails, source URLs) for Mbin imported posts',
                        'frequency' => 'Manual',
                        'frequency_color' => 'blue',
                        'command' => 'mbin:sync-media',
                        'manual' => true,
                        'category' => 'Mbin Sync',
                    ],

                    // === KARMA & ACHIEVEMENTS ===
                    [
                        'title' => 'Calculate Achievements (Active Users)',
                        'description' => 'Calculates and unlocks achievements for users active in the last 12 hours',
                        'frequency' => 'Every hour',
                        'frequency_color' => 'orange',
                        'command' => 'achievements:calculate --recent=12',
                        'category' => 'Karma & Achievements',
                    ],
                    [
                        'title' => 'Calculate Achievements (All Users)',
                        'description' => 'Complete achievement recalculation. Unlocks special achievements and grants karma bonuses',
                        'frequency' => 'Daily at 4:00',
                        'frequency_color' => 'orange',
                        'command' => 'achievements:calculate --all',
                        'category' => 'Karma & Achievements',
                    ],
                    [
                        'title' => 'Recalculate All Karma',
                        'description' => 'Recalculates karma for all users based on karma_histories table. Use with caution!',
                        'frequency' => 'Manual',
                        'frequency_color' => 'red',
                        'command' => 'karma:recalculate-all',
                        'manual' => true,
                        'category' => 'Karma & Achievements',
                    ],
                    [
                        'title' => 'Recalculate User Levels',
                        'description' => 'Recalculates karma levels for all users based on their current karma points',
                        'frequency' => 'Manual',
                        'frequency_color' => 'orange',
                        'command' => 'karma:recalculate-levels',
                        'manual' => true,
                        'category' => 'Karma & Achievements',
                    ],

                    // === COUNTERS & RECALCULATION ===
                    [
                        'title' => 'Recalculate Vote Counts',
                        'description' => 'Keeps vote counters synchronized to ensure accuracy',
                        'frequency' => 'Every 5 minutes',
                        'frequency_color' => 'cyan',
                        'command' => 'votes:recalculate',
                        'category' => 'Counters',
                    ],
                    [
                        'title' => 'Recalculate Post Counters',
                        'description' => 'Recalculates votes_count and comments_count for posts from the last 48 hours',
                        'frequency' => 'Every 6 hours',
                        'frequency_color' => 'cyan',
                        'command' => 'posts:recalculate-counts --hours=48',
                        'category' => 'Counters',
                    ],

                    // === RATE LIMITING ===
                    [
                        'title' => 'Rate Limiting Log Cleanup',
                        'description' => 'Removes old rate limiting logs to keep the database clean',
                        'frequency' => 'Daily at 3:00',
                        'frequency_color' => 'purple',
                        'command' => 'rate-limit:cleanup --force',
                        'category' => 'Rate Limiting',
                    ],
                    [
                        'title' => 'Clear Rate Limits',
                        'description' => 'Clears all rate limit caches (throttle, views, reports). Useful for development and testing',
                        'frequency' => 'Manual',
                        'frequency_color' => 'red',
                        'command' => 'rate-limit:clear',
                        'manual' => true,
                        'category' => 'Rate Limiting',
                    ],

                    // === STATISTICS & REPORTING ===
                    [
                        'title' => 'Calculate Transparency Statistics',
                        'description' => 'Generates statistics for the public transparency page (posts, users, reports)',
                        'frequency' => 'Every hour',
                        'frequency_color' => 'yellow',
                        'command' => 'transparency:calculate',
                        'category' => 'Statistics',
                    ],

                    // === SYSTEM & ADMIN ===
                    [
                        'title' => 'Create Invitation Code',
                        'description' => 'Generates a new invitation code for user registration',
                        'frequency' => 'Manual',
                        'frequency_color' => 'pink',
                        'command' => 'invitation:create',
                        'manual' => true,
                        'category' => 'System',
                    ],
                    [
                        'title' => 'Test System Emails',
                        'description' => 'Sends all notification emails to a test address in both Spanish and English',
                        'frequency' => 'Manual',
                        'frequency_color' => 'pink',
                        'command' => 'emails:test ' . config('app.admin_email', 'admin@example.com'),
                        'manual' => true,
                        'category' => 'System',
                    ],
                ];
                @endphp

                @php
                $currentCategory = null;
                @endphp
                @foreach($commands as $index => $cmd)
                @if($currentCategory !== ($cmd['category'] ?? null))
                    @php
                    $currentCategory = $cmd['category'] ?? null;
                    @endphp
                    @if($currentCategory)
                    <tr class="bg-gray-100 border-t-2 border-gray-300">
                        <td colspan="4" class="px-6 py-3">
                            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">
                                <i class="fas fa-folder mr-2"></i>{{ $currentCategory }}
                            </h3>
                        </td>
                    </tr>
                    @endif
                @endif
                <tr class="hover:bg-gray-50" id="row_{{ $index }}">
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-900 font-medium">{{ $cmd['title'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $cmd['description'] }}</p>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-{{ $cmd['frequency_color'] }}-100 text-{{ $cmd['frequency_color'] }}-800">
                            <i class="fas fa-clock mr-1"></i> {{ $cmd['frequency'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-600 block mb-2">{{ $cmd['command'] }}</code>
                        @if(isset($cmd['manual']) && $cmd['manual'] && str_starts_with($cmd['command'], 'emails:test'))
                        <div class="mt-2">
                            <label for="email_input_{{ $index }}" class="block text-xs text-gray-600 mb-1">
                                Destination email:
                            </label>
                            <input
                                type="email"
                                id="email_input_{{ $index }}"
                                value="{{ config('app.admin_email', 'admin@example.com') }}"
                                class="text-sm border border-gray-300 rounded-md px-2 py-1 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                onchange="handleEmailChange({{ $index }})"
                                placeholder="email@example.com"
                            >
                            <p id="email_warning_{{ $index }}" class="text-xs text-amber-600 mt-1 flex items-start hidden">
                                <i class="fas fa-exclamation-triangle mr-1 mt-0.5"></i>
                                <span>This will send all notification email types to this address in both Spanish and English</span>
                            </p>
                        </div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <form id="form_{{ $index }}" method="POST" action="{{ route('admin.scheduled-commands.execute') }}" class="inline" onsubmit="handleCommandSubmit(event, {{ $index }})">
                            @csrf
                            @php
                            // For emails:test, only send base command (email will be added separately)
                            $commandToSend = str_starts_with($cmd['command'], 'emails:test')
                                ? 'emails:test'
                                : $cmd['command'];
                            @endphp
                            <input type="hidden" name="command" value="{{ $commandToSend }}">
                            @if(str_starts_with($cmd['command'], 'emails:test'))
                            <input type="hidden" id="email_hidden_{{ $index }}" name="email" value="{{ config('app.admin_email', 'admin@example.com') }}">
                            @endif
                            <button type="submit" id="btn_{{ $index }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <i class="fas fa-play-circle mr-1"></i>Run now
                            </button>
                        </form>
                    </td>
                </tr>
                <!-- Output row spanning last 2 columns -->
                <tr id="output_row_{{ $index }}" class="hidden bg-gray-50">
                    <td colspan="2" class="px-6 py-4">
                        <!-- Empty space for first 2 columns -->
                    </td>
                    <td colspan="2" class="px-6 py-4">
                        <!-- Message container for success/error messages -->
                        <div id="message_{{ $index }}" class="mb-2 hidden"></div>

                        <div id="realtime_output_{{ $index }}" class="bg-gray-900 text-green-400 p-3 rounded text-xs font-mono overflow-x-auto max-h-96 overflow-y-auto">
                            <pre id="output_pre_{{ $index }}" class="whitespace-pre-wrap"></pre>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <button onclick="copyRealtimeOutput({{ $index }})" class="text-xs text-gray-600 hover:text-gray-800 flex items-center" title="Copy output">
                                <i class="fas fa-copy mr-1"></i>
                                <span id="copy_realtime_{{ $index }}">Copy</span>
                            </button>
                            <button onclick="hideRealtimeOutput({{ $index }})" class="text-xs text-gray-600 hover:text-gray-800 flex items-center" title="Hide output">
                                <i class="fas fa-times mr-1"></i>
                                Hide
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Info Box -->
    <div class="px-6 py-4 border-t border-gray-200 bg-blue-50">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 mr-3 mt-1"></i>
            <div class="text-sm text-blue-900">
                <p class="font-semibold mb-1">Scheduled Commands Information</p>
                <ul class="list-disc list-inside space-y-1 text-blue-800">
                    <li>Commands run automatically according to their schedule</li>
                    <li>Configuration is in <code class="bg-blue-100 px-1 rounded">bootstrap/app.php</code></li>
                    <li>You can execute them manually with the "Run now" button</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function handleCommandSubmit(event, index) {
    event.preventDefault();

    const form = document.getElementById(`form_${index}`);
    const button = document.getElementById(`btn_${index}`);
    const messageContainer = document.getElementById(`message_${index}`);
    const outputRow = document.getElementById(`output_row_${index}`);
    const outputPre = document.getElementById(`output_pre_${index}`);

    // Hide previous message and clear output
    messageContainer.classList.add('hidden');
    outputPre.textContent = '';
    outputRow.classList.remove('hidden');

    // Disable button and show loading state
    button.disabled = true;
    button.classList.add('opacity-50', 'cursor-not-allowed');
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Executing...';

    // Get form data
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    // Use EventSource for streaming
    const url = `${form.action}?${params.toString()}`;
    const eventSource = new EventSource(url);

    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);

        if (data.type === 'output') {
            // Append output line by line
            outputPre.textContent += data.line + '\n';
            // Auto-scroll to bottom
            outputPre.parentElement.scrollTop = outputPre.parentElement.scrollHeight;
        } else if (data.type === 'done') {
            // Command finished
            eventSource.close();

            messageContainer.innerHTML = `
                <div class="text-xs bg-green-50 border border-green-200 text-green-800 px-3 py-2 rounded">
                    <i class="fas fa-check-circle mr-1"></i>${data.message}
                </div>
            `;
            messageContainer.classList.remove('hidden');

            // Reset button
            button.disabled = false;
            button.classList.remove('opacity-50', 'cursor-not-allowed');
            button.innerHTML = '<i class="fas fa-play-circle mr-1"></i>Run now';
        } else if (data.type === 'error') {
            // Command failed
            eventSource.close();

            messageContainer.innerHTML = `
                <div class="text-xs bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded">
                    <i class="fas fa-exclamation-circle mr-1"></i>${data.message}
                </div>
            `;
            messageContainer.classList.remove('hidden');

            // Reset button
            button.disabled = false;
            button.classList.remove('opacity-50', 'cursor-not-allowed');
            button.innerHTML = '<i class="fas fa-play-circle mr-1"></i>Run now';
        }
    };

    eventSource.onerror = function(error) {
        eventSource.close();

        messageContainer.innerHTML = `
            <div class="text-xs bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded">
                <i class="fas fa-exclamation-circle mr-1"></i>Connection error
            </div>
        `;
        messageContainer.classList.remove('hidden');

        // Reset button
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        button.innerHTML = '<i class="fas fa-play-circle mr-1"></i>Run now';
    };

    return false;
}

function toggleOutput(outputId) {
    const outputDiv = document.getElementById(outputId);
    const icon = document.getElementById(`${outputId}_icon`);

    if (outputDiv.classList.contains('hidden')) {
        outputDiv.classList.remove('hidden');
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
    } else {
        outputDiv.classList.add('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyOutput(outputId) {
    const outputDiv = document.getElementById(outputId);
    const text = outputDiv.querySelector('pre').textContent;
    const copyButton = document.getElementById(`${outputId}_copy_text`);

    navigator.clipboard.writeText(text).then(() => {
        // Show feedback
        const originalText = copyButton.textContent;
        copyButton.textContent = 'Copied!';
        copyButton.parentElement.classList.add('text-green-600');

        setTimeout(() => {
            copyButton.textContent = originalText;
            copyButton.parentElement.classList.remove('text-green-600');
            copyButton.parentElement.classList.add('text-gray-600');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        copyButton.textContent = 'Failed';
        setTimeout(() => {
            copyButton.textContent = 'Copy';
        }, 2000);
    });
}

function copyRealtimeOutput(index) {
    const outputPre = document.getElementById(`output_pre_${index}`);
    const copyButton = document.getElementById(`copy_realtime_${index}`);
    const text = outputPre.textContent;

    navigator.clipboard.writeText(text).then(() => {
        copyButton.textContent = 'Copied!';
        copyButton.parentElement.classList.add('text-green-600');

        setTimeout(() => {
            copyButton.textContent = 'Copy';
            copyButton.parentElement.classList.remove('text-green-600');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        copyButton.textContent = 'Failed';
        setTimeout(() => {
            copyButton.textContent = 'Copy';
        }, 2000);
    });
}

function hideRealtimeOutput(index) {
    const outputRow = document.getElementById(`output_row_${index}`);
    outputRow.classList.add('hidden');
}

function handleEmailChange(index) {
    const emailInput = document.getElementById(`email_input_${index}`);
    const emailHidden = document.getElementById(`email_hidden_${index}`);
    const warning = document.getElementById(`email_warning_${index}`);

    // Update hidden input
    emailHidden.value = emailInput.value;

    // Show warning only if email is different from default
    const defaultEmail = '{{ config('app.admin_email', 'admin@example.com') }}';
    if (emailInput.value !== defaultEmail) {
        warning.classList.remove('hidden');
    } else {
        warning.classList.add('hidden');
    }
}
</script>
@endsection
