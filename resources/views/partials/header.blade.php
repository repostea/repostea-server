<header class="gradient-bg text-white py-4 sticky top-0 z-40 transition-all duration-300">
    <div class="container mx-auto px-6">
        <div class="flex justify-between items-center">
            <div class="text-2xl font-bold flex items-center">
                <a href="{{ localized_route('home') }}" class="flex items-center hover:opacity-90 transition gap-3">
                    <img src="{{ asset('favicon-96x96.png') }}" alt="{{ config('site.name') }}" class="w-10 h-10 rounded-lg shadow-md">
                    <span>{{ config('site.name') }}</span>
                </a>
            </div>
            <div class="hidden md:flex items-center space-x-6">
                <a href="{{ localized_route('home') }}" class="text-white hover:text-blue-200 transition px-3 py-1 rounded">
                    {{ __('translations.navigation.home') }}
                </a>
                <a href="{{ localized_route('about') }}" class="text-white hover:text-blue-200 transition px-3 py-1 rounded">
                    {{ __('translations.navigation.about') }}
                </a>
                <a href="{{ localized_route('manifesto') }}" class="text-white hover:text-blue-200 transition px-3 py-1 rounded">
                    {{ __('translations.navigation.manifesto') }}
                </a>
                <a href="{{ config('app.client_url') }}/{{ app()->getLocale() }}/" class="text-white hover:text-blue-200 transition px-3 py-1 rounded">
                    {{ __('translations.navigation.access') }}
                </a>

                <div id="localized-language-selector"
                     data-languages='@json(get_language_switchers())'
                     data-current-locale="{{ app()->getLocale() }}"
                     data-title="{{ __('Seleccionar idioma') }}"
                     class="language-selector-container"
                ></div>
            </div>

            <!-- Mobile controls -->
            <div class="md:hidden flex items-center">
                <!-- Mobile language selector with icon -->
                <div id="localized-language-selector"
                     data-languages='@json(get_language_switchers())'
                     data-current-locale="{{ app()->getLocale() }}"
                     data-title="{{ __('Seleccionar idioma') }}"
                     class="language-selector-container"
                ></div>

                <!-- Mobile menu button -->
                <button class="text-white focus:outline-none ml-4" id="mobile-menu-toggle">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden hidden absolute top-16 inset-x-0 z-40 bg-blue-800 shadow-md">
        <div class="py-2 px-4">
            <a href="{{ localized_route('home') }}" class="block py-2 text-white hover:text-blue-200">
                {{ __('translations.navigation.home') }}
            </a>
            <a href="{{ localized_route('about') }}" class="block py-2 text-white hover:text-blue-200">
                {{ __('translations.navigation.about') }}
            </a>
            <a href="{{ localized_route('manifesto') }}" class="block py-2 text-white hover:text-blue-200">
                {{ __('translations.navigation.manifesto') }}
            </a>
            <a href="{{ config('app.client_url') }}/{{ app()->getLocale() }}/" class="block py-2 text-white hover:text-blue-200">
                {{ __('translations.navigation.access') }}
            </a>

            <!-- Language selection option in mobile menu -->
            <div id="mobile-language-selector"
                 data-languages='@json(get_language_switchers())'
                 data-current-locale="{{ app()->getLocale() }}"
                 data-title="{{ __('translations.navigation.language') }}"
                 class="language-selector-mobile-menu"
            ></div>
        </div>
    </div>
</header>
