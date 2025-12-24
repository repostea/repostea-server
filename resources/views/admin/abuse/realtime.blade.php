@extends('admin.layout')

@section('title', 'Real-Time Statistics')
@section('page-title', 'Real-Time Abuse Statistics')

@section('content')
<div class="space-y-6">
    <!-- Refresh Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
            <div>
                <p class="text-sm text-blue-800">
                    <strong>What is this?</strong> This dashboard shows real-time abuse attempt activity.
                    Automatically refreshes every 30 seconds.
                </p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Violations Last Hour -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Abuse Attempts (Last Hour)</h3>
                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900" id="violations-last-hour">
                <i class="fas fa-spinner fa-spin text-gray-400"></i>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Number of detected spam, rate limiting, or abuse attempts in the last 60 minutes
            </p>
        </div>

        <!-- Violations Last 5 Min -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Recent Attempts (5 min)</h3>
                <i class="fas fa-bolt text-orange-500 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900" id="violations-last-5min">
                <i class="fas fa-spinner fa-spin text-gray-400"></i>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Very recent activity - indicates if there's an active attack right now
            </p>
        </div>

        <!-- Unique Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Affected Users</h3>
                <i class="fas fa-users text-blue-500 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900" id="unique-users">
                <i class="fas fa-spinner fa-spin text-gray-400"></i>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Unique users who exceeded security limits in the last hour
            </p>
        </div>
    </div>

    <!-- Top Action -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar mr-2"></i>Most Abused Action (Last Hour)
        </h3>
        <div id="top-action-container">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-4">
            <strong>What does this mean?</strong> Shows which type of action (create posts, comments, votes, etc.) is being most affected by abuse attempts.
            If an attacker is trying to spam comments, this metric will show it.
        </p>
    </div>

    <!-- Recent Violations -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-history mr-2"></i>Recent Abuse Attempts (Last 10)
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full" id="recent-violations-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="violations-tbody">
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-4">
            <strong>What does this mean?</strong> Chronological list of the most recent attempts to exceed security limits.
            Each row represents a blocked attempt to perform too many actions too quickly.
        </p>
    </div>

    <!-- Back Button -->
    <div>
        <a href="{{ route('admin.abuse') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Abuse Dashboard
        </a>
    </div>
</div>

<script>
let refreshInterval;

function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds

    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleString('en-US', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function updateStats() {
    fetch('{{ route('admin.abuse.realtime') }}', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            // Update counters
            document.getElementById('violations-last-hour').textContent = data.violations_last_hour || 0;
            document.getElementById('violations-last-5min').textContent = data.violations_last_5_min || 0;
            document.getElementById('unique-users').textContent = data.unique_users_last_hour || 0;

            // Update top action
            const topActionContainer = document.getElementById('top-action-container');
            if (data.top_action_last_hour && data.top_action_last_hour.action) {
                const actionLabels = {
                    'create_post': 'Create Posts',
                    'create_comment': 'Create Comments',
                    'vote_post': 'Vote Posts',
                    'vote_comment': 'Vote Comments',
                    'login': 'Login',
                    'register': 'Register',
                    'api_request': 'API Requests'
                };
                const actionLabel = actionLabels[data.top_action_last_hour.action] || data.top_action_last_hour.action;
                topActionContainer.innerHTML = `
                    <div class="flex items-center justify-between bg-red-50 border border-red-200 rounded-lg p-4">
                        <div>
                            <p class="text-lg font-semibold text-red-900">${actionLabel}</p>
                            <p class="text-sm text-red-700">This action has been blocked ${data.top_action_last_hour.count} times in the last hour</p>
                        </div>
                        <div class="text-3xl font-bold text-red-600">${data.top_action_last_hour.count}</div>
                    </div>
                `;
            } else {
                topActionContainer.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-2"></i>
                        <p>No recent abuse attempts</p>
                    </div>
                `;
            }

            // Update recent violations table
            const tbody = document.getElementById('violations-tbody');
            if (data.recent_violations && data.recent_violations.length > 0) {
                tbody.innerHTML = data.recent_violations.map(violation => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">${formatTime(violation.created_at)}</td>
                        <td class="px-4 py-3 text-sm">
                            ${violation.user ?
                                `<a href="/admin/abuse/user/${violation.user.id}" class="text-blue-600 hover:underline">${violation.user.username}</a>` :
                                '<span class="text-gray-400">Guest</span>'
                            }
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">${violation.ip_address || 'N/A'}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                ${violation.action || 'N/A'}
                            </span>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p>No recent violations</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
        });
}

// Initial load
updateStats();

// Auto-refresh every 30 seconds
refreshInterval = setInterval(updateStats, 30000);

// Stop refresh when leaving page
window.addEventListener('beforeunload', () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
@endsection
