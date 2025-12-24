@props(['placeholder' => 'Search...', 'searchValue' => '', 'searchName' => 'search'])

<div class="px-3 md:px-6 py-3 md:py-4 border-b border-gray-200">
    <form method="GET" class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
        <div class="flex-1">
            <input
                type="text"
                name="{{ $searchName }}"
                value="{{ $searchValue ?: request($searchName) }}"
                placeholder="{{ $placeholder }}"
                class="w-full px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
        </div>
        <div class="flex gap-2">
            {{ $filters ?? '' }}
            <button type="submit" class="px-4 md:px-6 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 whitespace-nowrap">
                <i class="fas fa-search md:mr-2"></i><span class="hidden md:inline">Search</span>
            </button>
        </div>
    </form>
</div>
