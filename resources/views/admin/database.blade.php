@extends('admin.layout')

@section('title', 'Database')
@section('page-title', 'Database')

@section('content')
<div class="p-8">
    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
            <div>
                <h3 class="font-semibold text-blue-900 mb-1">Database Management</h3>
                <p class="text-sm text-blue-700">
                    Monitor database sizes and create backup copies of both main and media databases.
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Disk Space -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-hdd mr-2 text-gray-500"></i>
                Disk Space
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Total</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['disk_space']['total_gb'] }} GB</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Used</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $stats['disk_space']['used_gb'] }} GB</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Free</p>
                    <p class="text-2xl font-bold text-green-600">{{ $stats['disk_space']['free_gb'] }} GB</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Usage</p>
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-{{ $stats['disk_space']['used_percent'] > 80 ? 'red' : ($stats['disk_space']['used_percent'] > 60 ? 'orange' : 'blue') }}-600 h-2.5 rounded-full" style="width: {{ $stats['disk_space']['used_percent'] }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">{{ $stats['disk_space']['used_percent'] }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Record Counts -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-chart-bar mr-2 text-blue-500"></i>
                Record Counts by Type
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($stats['record_counts'] as $type => $count)
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 capitalize">{{ $type }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($count) }}</p>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Database Sizes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Main Database -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-database mr-2 text-blue-500"></i>
                        Main Database
                    </h3>
                </div>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        Name: <span class="font-mono">{{ $stats['main_database']['name'] }}</span>
                    </p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['main_database']['size_mb'] }} MB</p>
                    @if(isset($stats['main_database']['error']))
                        <p class="text-sm text-red-600">Error: {{ $stats['main_database']['error'] }}</p>
                    @endif
                    <button
                        onclick="createBackup('main')"
                        class="mt-3 inline-flex items-center px-3 py-1.5 text-sm text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                    >
                        <i class="fas fa-download mr-2"></i>
                        Backup Main DB
                    </button>
                </div>
            </div>

            <!-- Media Database -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-images mr-2 text-green-500"></i>
                        Media Database
                    </h3>
                </div>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        Name: <span class="font-mono">{{ $stats['media_database']['name'] }}</span>
                    </p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['media_database']['size_mb'] }} MB</p>
                    @if(isset($stats['media_database']['error']))
                        <p class="text-sm text-red-600">Error: {{ $stats['media_database']['error'] }}</p>
                    @endif
                    <button
                        onclick="createBackup('media')"
                        class="mt-3 inline-flex items-center px-3 py-1.5 text-sm text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors"
                    >
                        <i class="fas fa-download mr-2"></i>
                        Backup Media DB
                    </button>
                </div>
            </div>
        </div>

        <!-- Largest Tables -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-table mr-2 text-purple-500"></i>
                Largest Tables (Top 10)
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($stats['largest_tables'] as $table)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $table['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $table['size_mb'] }} MB</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ number_format($table['rows']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No tables found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Index vs Data Size -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-list mr-2 text-green-500"></i>
                Index vs Data Size (Top 10)
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($stats['index_stats'] as $stat)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $stat['table'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $stat['data_mb'] }} MB</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $stat['index_mb'] }} MB</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $stat['index_percent'] > 50 ? 'bg-red-100 text-red-800' : ($stat['index_percent'] > 30 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                        {{ $stat['index_percent'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No data found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table Fragmentation -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-puzzle-piece mr-2 text-orange-500"></i>
                Table Fragmentation (Top 10)
            </h3>
            <p class="text-sm text-gray-600 mb-4">Tables with free space that could benefit from optimization</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Free Space</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fragmentation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($stats['fragmentation'] as $frag)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $frag['table'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $frag['size_mb'] }} MB</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $frag['free_mb'] }} MB</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $frag['fragmentation_percent'] > 20 ? 'bg-red-100 text-red-800' : ($frag['fragmentation_percent'] > 10 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                        {{ $frag['fragmentation_percent'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    No fragmentation detected
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Slow Queries -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-stopwatch mr-2 text-red-500"></i>
                Slowest Queries (Top 10)
            </h3>
            @if(empty($stats['slow_queries']))
                <div class="text-center py-8">
                    <i class="fas fa-info-circle text-gray-400 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-500">
                        Performance schema is not enabled or no query data available.
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        Enable performance_schema in MySQL to track query performance.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Executions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($stats['slow_queries'] as $query)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                        <div class="max-w-md truncate" title="{{ $query['query'] }}">
                                            {{ $query['query'] }}...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ number_format($query['exec_count']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $query['avg_time'] > 1 ? 'bg-red-100 text-red-800' : ($query['avg_time'] > 0.5 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                            {{ $query['avg_time'] }}s
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $query['max_time'] }}s
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $query['total_time'] }}s
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Queries Without Indexes -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                Queries Without Indexes (Top 10)
            </h3>
            @if(empty($stats['queries_without_indexes']))
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-600">
                        All queries are using indexes properly, or performance schema is not enabled.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Executions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No Index</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bad Index</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($stats['queries_without_indexes'] as $query)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                        <div class="max-w-md truncate" title="{{ $query['query'] }}">
                                            {{ $query['query'] }}...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ number_format($query['exec_count']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($query['no_index_count'] > 0)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                {{ number_format($query['no_index_count']) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($query['bad_index_count'] > 0)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                {{ number_format($query['bad_index_count']) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $query['avg_time'] }}s
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Backup Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-download mr-2 text-orange-500"></i>
                    Complete Backup
                </h3>
                <p class="text-sm text-gray-600 mt-1">Create a complete backup of both databases (main and media) in a single ZIP file</p>
            </div>

            <!-- Backup Button -->
            <button
                id="backup-btn"
                onclick="createBackup('both')"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <i class="fas fa-cloud-download-alt mr-2" id="backup-icon"></i>
                <span id="backup-text">Backup Both Databases</span>
            </button>

            <!-- Success Message -->
            <div id="backup-success" class="hidden mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-sm text-green-800 mb-2" id="backup-success-message"></p>
                <a
                    id="backup-download-link"
                    href="#"
                    class="inline-flex items-center text-sm font-medium text-green-700 hover:text-green-900"
                >
                    <i class="fas fa-file-archive mr-2"></i>
                    Download ZIP file
                </a>
            </div>

            <!-- Error Message -->
            <div id="backup-error" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <p class="text-sm text-red-800" id="backup-error-message"></p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.createBackup = async function(type = 'both') {
        const successDiv = document.getElementById('backup-success');
        const errorDiv = document.getElementById('backup-error');

        // Hide previous messages
        successDiv.classList.add('hidden');
        errorDiv.classList.add('hidden');

        try {
            const endpoint = type === 'both'
                ? '/api/v1/admin/database/backup'
                : `/api/v1/admin/database/backup/${type}`;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin'
            });

            // Check if response is JSON (error) or file download (success)
            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                // It's an error response
                const data = await response.json();
                throw new Error(data.message || 'Failed to create backup');
            } else {
                // It's a file download - create blob and download
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;

                // Get filename from Content-Disposition header
                const disposition = response.headers.get('content-disposition');
                let filename = 'backup.zip';
                if (disposition && disposition.includes('filename=')) {
                    filename = disposition.split('filename=')[1].replace(/"/g, '');
                }

                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                // Show success message
                document.getElementById('backup-success-message').textContent = 'Backup downloaded successfully!';
                successDiv.classList.remove('hidden');

                // Hide link for direct downloads
                document.getElementById('backup-download-link').style.display = 'none';
            }
        } catch (err) {
            console.error('Error creating backup:', err);
            document.getElementById('backup-error-message').textContent = err.message;
            errorDiv.classList.remove('hidden');
        }
    };
});
</script>
@endpush
@endsection
