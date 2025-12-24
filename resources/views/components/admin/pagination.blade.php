@props(['paginator'])

@if($paginator->hasPages())
    <div class="px-3 md:px-6 py-2 md:py-4 border-t border-gray-200">
        <div class="flex flex-col md:flex-row items-center justify-between gap-2 md:gap-3">
            <!-- Results info -->
            <div class="text-xs text-gray-600 order-2 md:order-1">
                <span class="font-medium">{{ $paginator->firstItem() }}</span>-<span class="font-medium">{{ $paginator->lastItem() }}</span> of <span class="font-medium">{{ $paginator->total() }}</span>
            </div>

            <!-- Navigation buttons -->
            <div class="flex items-center gap-3 md:gap-4 order-1 md:order-2">
                @if($paginator->onFirstPage())
                    <span class="inline-flex items-center text-gray-400 cursor-not-allowed text-xs">
                        <i class="fas fa-chevron-left mr-1"></i>
                        <span class="hidden sm:inline">Prev</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}"
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline transition-colors text-xs font-medium">
                        <i class="fas fa-chevron-left mr-1"></i>
                        <span class="hidden sm:inline">Prev</span>
                    </a>
                @endif

                <!-- Page indicator -->
                <span class="text-xs text-gray-700 font-medium">
                    {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
                </span>

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}"
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline transition-colors text-xs font-medium">
                        <span class="hidden sm:inline">Next</span>
                        <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                @else
                    <span class="inline-flex items-center text-gray-400 cursor-not-allowed text-xs">
                        <span class="hidden sm:inline">Next</span>
                        <i class="fas fa-chevron-right ml-1"></i>
                    </span>
                @endif
            </div>
        </div>
    </div>
@endif
