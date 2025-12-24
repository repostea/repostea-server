@extends('admin.layout')

@section('title', 'Error Logs')
@section('page-title', 'Error Logs')

@section('content')
<div class="space-y-6">
    <!-- Header with actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Error Logs</h1>
            <p class="text-gray-600 text-sm mt-1">View application and system errors</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- Limit selector -->
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="source" value="{{ $currentSource }}">
                <label for="limit" class="text-sm text-gray-600">Show:</label>
                <select name="limit" id="limit" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="25" {{ $currentLimit == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ $currentLimit == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $currentLimit == 100 ? 'selected' : '' }}>100</option>
                    <option value="200" {{ $currentLimit == 200 ? 'selected' : '' }}>200</option>
                </select>
            </form>

            <!-- Download button -->
            <a href="{{ route('admin.error-logs.download', ['source' => $currentSource]) }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                <i class="fas fa-download mr-2"></i>
                Download
            </a>

            <!-- Clear button (only for Laravel logs) -->
            @if($currentSource === 'laravel')
                <form method="POST" action="{{ route('admin.error-logs.clear') }}" onsubmit="return confirmSubmit(this, 'Are you sure you want to clear the log file? This cannot be undone.', {title: 'Clear Logs', type: 'danger', confirmText: 'Clear'})">
                    @csrf
                    <input type="hidden" name="source" value="{{ $currentSource }}">
                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        <i class="fas fa-trash mr-2"></i>
                        Clear Logs
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Log Source Tabs -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
            <nav class="flex flex-wrap -mb-px" aria-label="Tabs">
                <!-- Laravel Tab -->
                <a href="{{ route('admin.error-logs', ['source' => 'laravel', 'limit' => $currentLimit]) }}"
                   class="px-4 py-3 text-sm font-medium border-b-2 {{ $currentSource === 'laravel' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fab fa-laravel mr-2"></i>
                    Laravel
                </a>

                <!-- System Log Tabs -->
                @foreach($systemLogs as $source => $log)
                    <a href="{{ route('admin.error-logs', ['source' => $source, 'limit' => $currentLimit]) }}"
                       class="px-4 py-3 text-sm font-medium border-b-2 flex items-center gap-2
                              {{ $currentSource === $source ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}
                              {{ !$log['found'] ? 'opacity-50' : '' }}">
                        <i class="fab {{ $log['icon'] }} {{ !$log['found'] ? 'text-gray-400' : '' }}"></i>
                        {{ $log['name'] }}
                        @if(!$log['found'])
                            <span class="text-xs text-gray-400">(not found)</span>
                        @elseif(!$log['readable'])
                            <span class="text-xs text-yellow-500"><i class="fas fa-lock"></i></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <!-- Error message if log not readable -->
    @if(isset($errorMessage) && $errorMessage)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start">
                <i class="fas fa-terminal text-blue-600 mr-3 mt-1"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-blue-800">View logs via SSH</h3>
                    <p class="text-sm text-blue-700 mt-1">For security reasons, system logs are not accessible from the web interface.</p>

                    @if($currentSource === 'php_fpm')
                        <div class="mt-3 bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 overflow-x-auto">
                            <p class="text-gray-500"># View last 50 PHP-FPM errors</p>
                            <p>sudo tail -50 /var/log/php8.4-fpm.log</p>
                            <p class="text-gray-500 mt-2"># Follow logs in real-time</p>
                            <p>sudo tail -f /var/log/php8.4-fpm.log</p>
                            <p class="text-gray-500 mt-2"># Search for errors</p>
                            <p>sudo grep -i "error\|fatal\|warning" /var/log/php8.4-fpm.log | tail -20</p>
                        </div>
                    @elseif($currentSource === 'syslog')
                        <div class="mt-3 bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 overflow-x-auto">
                            <p class="text-gray-500"># View last 50 system log entries</p>
                            <p>sudo tail -50 /var/log/syslog</p>
                            <p class="text-gray-500 mt-2"># Filter by service (php, nginx, mysql)</p>
                            <p>sudo grep -i "php\|nginx\|mysql" /var/log/syslog | tail -20</p>
                            <p class="text-gray-500 mt-2"># Follow logs in real-time</p>
                            <p>sudo tail -f /var/log/syslog</p>
                        </div>
                    @elseif($currentSource === 'nginx_error')
                        <div class="mt-3 bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 overflow-x-auto">
                            <p class="text-gray-500"># View last 50 Nginx errors</p>
                            <p>sudo tail -50 /var/log/nginx/error.log</p>
                            <p class="text-gray-500 mt-2"># Follow logs in real-time</p>
                            <p>sudo tail -f /var/log/nginx/error.log</p>
                        </div>
                    @else
                        <div class="mt-3 bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 overflow-x-auto">
                            <p class="text-gray-500"># Connect to server</p>
                            <p>ssh your-server</p>
                            <p class="text-gray-500 mt-2"># View system logs</p>
                            <p>sudo tail -f /var/log/syslog</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 rounded-full">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Errors Found</p>
                    <p class="text-2xl font-bold text-gray-900">{{ count($errors) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-file text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Log Size</p>
                    <p class="text-2xl font-bold text-gray-900">
                        @if($totalSize > 1024 * 1024)
                            {{ number_format($totalSize / 1024 / 1024, 2) }} MB
                        @else
                            {{ number_format($totalSize / 1024, 2) }} KB
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <i class="fas fa-clock text-green-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Last Modified</p>
                    <p class="text-lg font-bold text-gray-900">
                        @if($lastModified)
                            {{ \Carbon\Carbon::createFromTimestamp($lastModified)->diffForHumans() }}
                        @else
                            N/A
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-full">
                    <i class="fas fa-folder text-purple-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Source</p>
                    <p class="text-lg font-bold text-gray-900">
                        @if($currentSource === 'laravel')
                            Laravel
                        @else
                            {{ $systemLogs[$currentSource]['name'] ?? ucfirst($currentSource) }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Current log file path -->
    @if($logFilePath)
        <div class="bg-gray-50 rounded-lg p-3 text-sm">
            <span class="text-gray-500">Log file:</span>
            <code class="ml-2 text-gray-700 bg-white px-2 py-1 rounded">{{ $logFilePath }}</code>
        </div>
    @endif

    <!-- Error list -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                @if($currentSource === 'laravel')
                    Recent Laravel Errors
                @elseif($currentSource === 'nginx_access')
                    Recent HTTP Errors (4xx/5xx)
                @else
                    Recent Errors
                @endif
            </h2>
        </div>

        @if(count($errors) > 0)
            <div class="divide-y divide-gray-200">
                @foreach($errors as $index => $error)
                    <div class="p-4 hover:bg-gray-50 cursor-pointer" onclick="toggleErrorDetail({{ $index }})">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <!-- Level badge -->
                                    @php
                                        $levelColors = [
                                            'ERROR' => 'bg-red-100 text-red-800',
                                            'CRITICAL' => 'bg-red-600 text-white',
                                            'ALERT' => 'bg-orange-500 text-white',
                                            'EMERGENCY' => 'bg-red-900 text-white',
                                            'WARNING' => 'bg-yellow-100 text-yellow-800',
                                            'WARN' => 'bg-yellow-100 text-yellow-800',
                                            'INFO' => 'bg-blue-100 text-blue-800',
                                        ];
                                        $levelColor = $levelColors[$error['level']] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $levelColor }}">
                                        {{ $error['level'] }}
                                    </span>

                                    <!-- Environment/Source badge -->
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600">
                                        {{ $error['environment'] }}
                                    </span>

                                    <!-- Stack trace indicator -->
                                    @if($error['has_stack_trace'] ?? false)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                                            <i class="fas fa-layer-group mr-1"></i>Stack Trace
                                        </span>
                                    @endif

                                    <!-- Attack detection badge -->
                                    @if(isset($error['attack_type']) && $error['attack_type'])
                                        @php
                                            $severityColors = [
                                                'critical' => 'bg-red-600 text-white',
                                                'high' => 'bg-orange-500 text-white',
                                                'medium' => 'bg-yellow-500 text-white',
                                                'low' => 'bg-blue-100 text-blue-800',
                                                'info' => 'bg-gray-100 text-gray-600',
                                            ];
                                            $severityIcons = [
                                                'critical' => 'fa-skull-crossbones',
                                                'high' => 'fa-exclamation-triangle',
                                                'medium' => 'fa-shield-alt',
                                                'low' => 'fa-search',
                                                'info' => 'fa-info-circle',
                                            ];
                                            $attackColor = $severityColors[$error['attack_severity']] ?? 'bg-gray-100 text-gray-600';
                                            $attackIcon = $severityIcons[$error['attack_severity']] ?? 'fa-bug';
                                        @endphp
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $attackColor }}" title="{{ $error['attack_description'] }}">
                                            <i class="fas {{ $attackIcon }} mr-1"></i>{{ strtoupper(str_replace('_', ' ', $error['attack_type'])) }}
                                        </span>
                                    @endif

                                    <!-- Timestamp -->
                                    <span class="text-xs text-gray-500">
                                        <i class="far fa-clock mr-1"></i>{{ $error['timestamp'] }}
                                    </span>
                                </div>

                                <!-- Error message (truncated) -->
                                <p class="text-sm text-gray-900 font-mono truncate">
                                    {{ Str::limit($error['message'], 150) }}
                                </p>

                                <!-- Attack description -->
                                @if(isset($error['attack_description']) && $error['attack_description'])
                                    <p class="text-xs text-orange-600 mt-1">
                                        <i class="fas fa-shield-virus mr-1"></i>{{ $error['attack_description'] }}
                                    </p>
                                @endif
                            </div>

                            <!-- Copy and Expand buttons -->
                            <div class="ml-4 flex-shrink-0 flex items-center gap-2">
                                <button onclick="event.stopPropagation(); copyToClipboard({{ $index }})" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Copy error">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <i class="fas fa-chevron-down text-gray-400 transition-transform" id="chevron-{{ $index }}"></i>
                            </div>
                        </div>

                        <!-- Expandable detail section -->
                        <div class="hidden mt-4" id="error-detail-{{ $index }}">
                            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap break-words">{{ $error['full_message'] }}</pre>
                            </div>
                            <div class="mt-2 flex justify-end">
                                <button onclick="event.stopPropagation(); copyToClipboard({{ $index }})" class="text-xs text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-copy mr-1"></i>Copy to clipboard
                                </button>
                            </div>
                            <textarea class="hidden" id="error-text-{{ $index }}">{{ $error['full_message'] }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No errors found</h3>
                <p class="text-gray-500">
                    @if($currentSource === 'nginx_access')
                        No HTTP 4xx/5xx errors in the access log.
                    @else
                        The log file is empty or contains no errors.
                    @endif
                </p>
            </div>
        @endif
    </div>

    <!-- Laravel log files section (only show on Laravel tab) -->
    @if($currentSource === 'laravel' && count($logFiles) > 0)
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Laravel Log Files</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Modified</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($logFiles as $file)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                    <i class="fas fa-file-alt text-gray-400 mr-2"></i>
                                    {{ $file['name'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    @if($file['size'] > 1024 * 1024)
                                        {{ number_format($file['size'] / 1024 / 1024, 2) }} MB
                                    @else
                                        {{ number_format($file['size'] / 1024, 2) }} KB
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ \Carbon\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i:s') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- System logs status -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">System Log Status</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($systemLogs as $source => $log)
                    <div class="border rounded-lg p-3 {{ $log['found'] && $log['readable'] ? 'border-green-200 bg-green-50' : ($log['found'] ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200 bg-gray-50') }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-gray-900">
                                <i class="fab {{ $log['icon'] }} mr-2"></i>
                                {{ $log['name'] }}
                            </span>
                            @if($log['found'] && $log['readable'])
                                <span class="text-green-600"><i class="fas fa-check-circle"></i></span>
                            @elseif($log['found'])
                                <span class="text-yellow-600" title="Found but not readable"><i class="fas fa-lock"></i></span>
                            @else
                                <span class="text-gray-400"><i class="fas fa-times-circle"></i></span>
                            @endif
                        </div>
                        @if($log['path'])
                            <p class="text-xs text-gray-500 font-mono truncate" title="{{ $log['path'] }}">{{ $log['path'] }}</p>
                        @else
                            <p class="text-xs text-gray-400 italic">Not found</p>
                        @endif
                        @if($log['size'] > 0)
                            <p class="text-xs text-gray-500 mt-1">
                                Size: {{ number_format($log['size'] / 1024, 2) }} KB
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function toggleErrorDetail(index) {
        const detail = document.getElementById('error-detail-' + index);
        const chevron = document.getElementById('chevron-' + index);

        if (detail.classList.contains('hidden')) {
            detail.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            detail.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function copyToClipboard(index) {
        const text = document.getElementById('error-text-' + index).value;
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target.closest('button');
            const icon = btn.querySelector('i');
            const originalClass = icon.className;

            // Change icon to checkmark
            icon.className = 'fas fa-check';
            btn.classList.remove('text-gray-400', 'hover:text-blue-600');
            btn.classList.add('text-green-600');

            setTimeout(() => {
                icon.className = originalClass;
                btn.classList.remove('text-green-600');
                btn.classList.add('text-gray-400', 'hover:text-blue-600');
            }, 1500);
        });
    }
</script>
@endpush
@endsection
