@props(['headers' => []])

<div class="bg-white rounded-lg shadow">
    {{ $search ?? '' }}

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            @if(!empty($headers))
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        @foreach($headers as $header)
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider {{ $header['class'] ?? '' }}">
                                {{ $header['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-gray-200">
                {{ $desktop ?? $slot }}
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden divide-y divide-gray-200">
        {{ $mobile ?? $slot }}
    </div>

    {{ $pagination ?? '' }}
</div>
