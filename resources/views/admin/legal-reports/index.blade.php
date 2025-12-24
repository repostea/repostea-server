@extends('admin.layout')

@section('title', 'Legal Reports')
@section('page-title', 'Legal Reports Management (DMCA / Abuse)')

@section('content')
<div class="bg-white rounded-lg shadow">
    <!-- Search and Filters -->
    <x-admin.search-form placeholder="Search by reference number..." searchName="reference" :searchValue="request('reference')">
        <x-slot name="filters">
            <select
                name="status"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All Status</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>Under Review</option>
                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <select
                name="type"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All Types</option>
                <option value="copyright" {{ request('type') === 'copyright' ? 'selected' : '' }}>Copyright</option>
                <option value="illegal" {{ request('type') === 'illegal' ? 'selected' : '' }}>Illegal Content</option>
                <option value="harassment" {{ request('type') === 'harassment' ? 'selected' : '' }}>Harassment</option>
                <option value="privacy" {{ request('type') === 'privacy' ? 'selected' : '' }}>Privacy</option>
                <option value="spam" {{ request('type') === 'spam' ? 'selected' : '' }}>Spam</option>
                <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </x-slot>
    </x-admin.search-form>

    <!-- Legal Reports Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($reports as $report)
                    <tr class="hover:bg-gray-50 {{ $report->status === 'pending' ? 'bg-yellow-50' : '' }}">
                        <td class="px-6 py-4">
                            <div class="max-w-md">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-admin.badge type="purple" :label="ucfirst($report->type)" />
                                    <span class="inline-flex px-2 py-1 text-xs font-mono rounded bg-gray-100 text-gray-700 whitespace-nowrap">
                                        {{ $report->reference_number }}
                                    </span>
                                </div>
                                @if($report->description)
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                        {{ Str::limit($report->description, 120) }}
                                    </p>
                                @endif
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $report->reporter_name }}</p>
                                <p class="text-xs text-gray-500">
                                    @php
                                        // Mask email: show first 2 chars of local + *** + @first2chars***.ext
                                        $email = $report->reporter_email;
                                        $parts = explode('@', $email);
                                        $localPart = $parts[0];
                                        $domain = $parts[1] ?? '';

                                        // Mask local part
                                        $charsToShow = min(2, strlen($localPart));
                                        $charsToMask = max(0, min(strlen($localPart) - $charsToShow, 4));
                                        $maskedLocal = substr($localPart, 0, $charsToShow) . str_repeat('*', $charsToMask);

                                        // Mask domain part
                                        $domainParts = explode('.', $domain);
                                        if (count($domainParts) >= 2) {
                                            $maskedDomain = substr($domainParts[0], 0, 2) . '***.' . end($domainParts);
                                        } else {
                                            $maskedDomain = '***';
                                        }

                                        echo $maskedLocal . '@' . $maskedDomain;
                                    @endphp
                                </p>
                                @if($report->reporter_organization)
                                    <p class="text-xs text-gray-400 mt-1">{{ $report->reporter_organization }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4">
                            <div class="max-w-xs">
                                <x-admin.action-link :href="$report->content_url" :external="true" class="text-xs break-all">
                                    {{ Str::limit($report->content_url, 50) }}
                                </x-admin.action-link>
                                @if($report->original_url)
                                    <p class="text-xs text-gray-400 mt-1">
                                        Original: {{ Str::limit($report->original_url, 40) }}
                                    </p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($report->status === 'pending')
                                <x-admin.badge type="pending" label="Pending" />
                            @elseif($report->status === 'under_review')
                                <x-admin.badge type="info" label="Under Review" />
                            @elseif($report->status === 'resolved')
                                <x-admin.badge type="success" label="Resolved" />
                            @elseif($report->status === 'rejected')
                                <x-admin.badge type="danger" label="Rejected" />
                            @else
                                <x-admin.badge type="default" :label="ucfirst(str_replace('_', ' ', $report->status))" />
                            @endif
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $report->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $report->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <x-admin.action-link :href="route('admin.legal-reports.view', $report)">
                                View Details
                            </x-admin.action-link>
                            @if($report->status === 'pending')
                                <form method="POST" action="{{ route('admin.legal-reports.update-status', $report) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="under_review">
                                    <button type="submit" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        Mark Reviewing
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="gavel" message="No legal reports found" colspan="6" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Legal Reports Cards - Mobile -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($reports as $report)
            <div class="p-3 {{ $report->status === 'pending' ? 'bg-yellow-50' : '' }}">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <x-admin.badge type="purple" :label="ucfirst($report->type)" />
                    <span class="inline-flex px-2 py-1 text-xs font-mono rounded bg-gray-100 text-gray-700 whitespace-nowrap">
                        {{ $report->reference_number }}
                    </span>
                </div>
                @if($report->description)
                    <p class="text-xs text-gray-600 mt-1 mb-2 line-clamp-2">
                        {{ Str::limit($report->description, 120) }}
                    </p>
                @endif
                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    <div>
                        <x-admin.mobile-label label="Reporter" />
                        {{ $report->reporter_name }}
                        @if($report->reporter_organization)
                            • {{ $report->reporter_organization }}
                        @endif
                    </div>
                    <div>
                        <x-admin.mobile-label label="Status" />
                        @if($report->status === 'pending')
                            <x-admin.badge type="pending" label="Pending" />
                        @elseif($report->status === 'under_review')
                            <x-admin.badge type="info" label="Under Review" />
                        @elseif($report->status === 'resolved')
                            <x-admin.badge type="success" label="Resolved" />
                        @elseif($report->status === 'rejected')
                            <x-admin.badge type="danger" label="Rejected" />
                        @else
                            <x-admin.badge type="default" :label="ucfirst(str_replace('_', ' ', $report->status))" />
                        @endif
                        •
                        <x-admin.mobile-label label="Date" />
                        {{ $report->created_at->format('d/m/Y H:i') }}
                    </div>
                    <div>
                        <x-admin.mobile-label label="Content" />
                        <x-admin.action-link :href="$report->content_url" :external="true" class="text-xs break-all">
                            {{ Str::limit($report->content_url, 40) }}
                        </x-admin.action-link>
                    </div>
                </div>
                <div class="flex gap-3 text-sm">
                    <x-admin.action-link :href="route('admin.legal-reports.view', $report)">
                        View Details
                    </x-admin.action-link>
                    @if($report->status === 'pending')
                        <form method="POST" action="{{ route('admin.legal-reports.update-status', $report) }}" class="inline">
                            @csrf
                            <input type="hidden" name="status" value="under_review">
                            <button type="submit" class="text-blue-600 hover:text-blue-800 hover:underline">
                                Mark Reviewing
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <x-admin.empty-state-mobile icon="gavel" message="No legal reports found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$reports" />
</div>
@endsection
