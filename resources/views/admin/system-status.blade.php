@extends('admin.layout')

@section('title', 'System Status')
@section('page-title', 'System Status')

@section('content')
<div class="space-y-6">
    <!-- Server Health -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-server mr-2 text-blue-500"></i>
            Server Health
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Load Average</p>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">1 min</span>
                        <span class="font-semibold {{ $status['server']['load_average']['1min'] > 2 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $status['server']['load_average']['1min'] }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">5 min</span>
                        <span class="font-semibold">{{ $status['server']['load_average']['5min'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">15 min</span>
                        <span class="font-semibold">{{ $status['server']['load_average']['15min'] }}</span>
                    </div>
                </div>
            </div>

            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Memory</p>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Total</span>
                        <span class="font-semibold">{{ $status['server']['memory']['total_gb'] }} GB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Used</span>
                        <span class="font-semibold text-orange-600">{{ $status['server']['memory']['used_gb'] }} GB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Free</span>
                        <span class="font-semibold text-green-600">{{ $status['server']['memory']['free_gb'] }} GB</span>
                    </div>
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-{{ $status['server']['memory']['used_percent'] > 80 ? 'red' : ($status['server']['memory']['used_percent'] > 60 ? 'orange' : 'blue') }}-600 h-2 rounded-full"
                                 style="width: {{ $status['server']['memory']['used_percent'] }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $status['server']['memory']['used_percent'] }}% used</p>
                    </div>
                </div>
            </div>

            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Uptime</p>
                <div class="text-center py-4">
                    <p class="text-3xl font-bold text-blue-600">{{ $status['server']['uptime_days'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PHP Information -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fab fa-php mr-2 text-purple-500"></i>
            PHP Configuration
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($status['php'] as $key => $value)
                <div class="border rounded-lg p-3">
                    <p class="text-xs text-gray-500 capitalize">{{ str_replace('_', ' ', $key) }}</p>
                    <p class="font-semibold text-gray-900 mt-1">
                        @if(is_bool($value))
                            <span class="px-2 py-1 text-xs rounded-full {{ $value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $value ? 'Enabled' : 'Disabled' }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Laravel Configuration -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fab fa-laravel mr-2 text-red-500"></i>
            Laravel Configuration
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($status['laravel'] as $key => $value)
                <div class="border rounded-lg p-3">
                    <p class="text-xs text-gray-500 capitalize">{{ str_replace('_', ' ', $key) }}</p>
                    <p class="font-semibold text-gray-900 mt-1">
                        @if(is_bool($value))
                            <span class="px-2 py-1 text-xs rounded-full {{ $value ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                {{ $value ? 'On' : 'Off' }}
                            </span>
                        @else
                            <span class="font-mono text-sm">{{ $value }}</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Services Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-network-wired mr-2 text-green-500"></i>
            Services Status
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($status['services'] as $service => $info)
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 rounded-full {{ $info['status'] === 'up' ? 'bg-green-500' : 'bg-red-500' }} animate-pulse"></div>
                            <div>
                                <p class="font-semibold text-gray-900 capitalize">{{ $service }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $info['message'] }}</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $info['status'] === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ strtoupper($info['status']) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Queue Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-tasks mr-2 text-yellow-500"></i>
                Queue Status
            </h3>
            @if($status['queue']['failed_jobs'] > 0)
                <a href="{{ url('/telescope/failed-jobs') }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                    View in Telescope <i class="fas fa-external-link-alt ml-1"></i>
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600">Queue Driver</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">{{ $status['queue']['driver'] }}</p>
            </div>
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600">Failed Jobs</p>
                <p class="text-2xl font-bold {{ $status['queue']['failed_jobs'] > 0 ? 'text-red-600' : 'text-green-600' }} mt-2">
                    {{ number_format($status['queue']['failed_jobs']) }}
                </p>
            </div>
        </div>

        @if($status['queue']['failed_jobs'] > 0 && count($status['queue']['failed_jobs_details']) > 0)
            <div class="border-t pt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Recent Failed Jobs</h4>
                <div class="space-y-3">
                    @foreach($status['queue']['failed_jobs_details'] as $job)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 text-xs font-semibold bg-red-200 text-red-800 rounded">
                                            {{ $job['queue'] }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}
                                        </span>
                                    </div>
                                    <p class="text-xs font-mono text-gray-700 mt-2 break-words">
                                        {{ $job['exception'] }}...
                                    </p>
                                </div>
                                <a href="{{ url('/telescope/failed-jobs/' . $job['id']) }}" target="_blank" class="ml-3 text-xs text-blue-600 hover:text-blue-800 whitespace-nowrap">
                                    View <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($status['queue']['failed_jobs'] > 10)
                    <p class="text-xs text-gray-500 mt-3 text-center">
                        Showing 10 of {{ number_format($status['queue']['failed_jobs']) }} failed jobs.
                        <a href="{{ url('/telescope/failed-jobs') }}" target="_blank" class="text-blue-600 hover:text-blue-800">View all in Telescope →</a>
                    </p>
                @endif
            </div>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-bolt mr-2 text-orange-500"></i>
            Quick Actions
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button onclick="clearCache('config')" class="px-4 py-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-sm font-medium text-blue-700 transition-colors">
                <i class="fas fa-sync mr-2"></i>Clear Config
            </button>
            <button onclick="clearCache('cache')" class="px-4 py-3 bg-green-50 hover:bg-green-100 rounded-lg text-sm font-medium text-green-700 transition-colors">
                <i class="fas fa-sync mr-2"></i>Clear Cache
            </button>
            <button onclick="clearCache('view')" class="px-4 py-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-sm font-medium text-purple-700 transition-colors">
                <i class="fas fa-sync mr-2"></i>Clear Views
            </button>
            <button onclick="clearCache('route')" class="px-4 py-3 bg-orange-50 hover:bg-orange-100 rounded-lg text-sm font-medium text-orange-700 transition-colors">
                <i class="fas fa-sync mr-2"></i>Clear Routes
            </button>
        </div>

        <div id="clear-cache-message" class="hidden mt-4 p-3 rounded-lg"></div>
    </div>
</div>

@push('scripts')
<script>
async function clearCache(type) {
    const messageDiv = document.getElementById('clear-cache-message');
    messageDiv.classList.add('hidden');

    try {
        const response = await fetch(`/api/v1/admin/clear-cache/${type}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        messageDiv.className = 'mt-4 p-3 rounded-lg ' + (data.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800');
        messageDiv.textContent = data.message;
        messageDiv.classList.remove('hidden');

        setTimeout(() => {
            messageDiv.classList.add('hidden');
        }, 3000);
    } catch (error) {
        messageDiv.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-800';
        messageDiv.textContent = 'Error: ' + error.message;
        messageDiv.classList.remove('hidden');
    }
}
</script>
@endpush
@endsection
