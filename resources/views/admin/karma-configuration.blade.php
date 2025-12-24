@extends('admin.layout')

@section('title', 'Karma & Achievements Configuration')
@section('page-title', 'Karma & Achievements Configuration')

@section('content')
<div class="space-y-6">
    <!-- Karma Levels -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-trophy mr-2 text-yellow-500"></i>
            Karma Levels
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            {{ $karmaLevels->count() }} niveles configurados
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Badge</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required Karma</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($karmaLevels as $level)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4 whitespace-nowrap text-2xl">{{ $level->badge }}</td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ __($level->name) }}</div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 font-semibold">{{ number_format($level->required_karma) }}</div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-600">{{ __($level->description) }}</div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Achievements by Type -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-medal mr-2 text-blue-500"></i>
            Achievements
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            {{ $achievements->flatten()->count() }} logros en {{ $achievements->keys()->count() }} categorías
        </p>

        @foreach($achievements as $type => $typeAchievements)
            <div class="mb-6 last:mb-0">
                <h4 class="text-md font-semibold text-gray-800 mb-3 flex items-center">
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm mr-2">
                        {{ ucfirst($type) }}
                    </span>
                    <span class="text-sm text-gray-500 font-normal">
                        ({{ $typeAchievements->count() }} logros)
                    </span>
                </h4>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logro</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karma Bonus</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requirements</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($typeAchievements as $achievement)
                            @php
                                $colors = ['#f59e0b', '#8b5cf6', '#3b82f6', '#10b981', '#f97316', '#6b7280', '#ef4444', '#ec4899'];
                                $color = $colors[$achievement->id % count($colors)];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="{{ $achievement->icon }} text-xl" style="min-width: 24px; color: {{ $color }};"></i>
                                        <div class="text-sm font-medium text-gray-900">{{ __($achievement->name) }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $achievement->slug }}</code>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-600">{{ __($achievement->description) }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $achievement->karma_bonus > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $achievement->karma_bonus > 0 ? '+' : '' }}{{ $achievement->karma_bonus }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($achievement->requirements)
                                        <code class="text-xs bg-blue-50 text-blue-800 px-2 py-1 rounded block max-w-xs overflow-x-auto">
                                            {{ json_encode($achievement->requirements) }}
                                        </code>
                                    @else
                                        <span class="text-xs text-gray-400 italic">N/A</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Statistics -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar mr-2 text-purple-500"></i>
            Statistics
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Total Karma Levels</p>
                <p class="text-3xl font-bold text-blue-600">{{ $karmaLevels->count() }}</p>
            </div>
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Total Achievements</p>
                <p class="text-3xl font-bold text-green-600">{{ $achievements->flatten()->count() }}</p>
            </div>
            <div class="border rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-2">Total Karma Available</p>
                <p class="text-3xl font-bold text-purple-600">{{ number_format($totalKarmaAvailable) }}</p>
                <p class="text-xs text-gray-500 mt-1">From all achievements</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($achievements as $type => $typeAchievements)
                <div class="border rounded-lg p-3">
                    <p class="text-xs text-gray-500 capitalize">{{ $type }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ $typeAchievements->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        +{{ number_format($typeAchievements->sum('karma_bonus')) }} karma
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
