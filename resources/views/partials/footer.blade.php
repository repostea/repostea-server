<footer class="gradient-bg text-white py-6">
    <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div>
                <h3 class="text-lg font-bold mb-4">{{ config('site.name') }}</h3>
                <ul class="space-y-2">
                    <li><a href="{{ localized_route('home') }}"
                           class="hover:text-blue-200 transition">{{ __('translations.navigation.home') }}</a></li>
                    <li><a href="{{ localized_route('manifesto') }}"
                           class="hover:text-blue-200 transition">{{ __('translations.navigation.manifesto') }}</a></li>
                    <li><a href="{{ config('app.client_url') }}/{{ app()->getLocale() }}/"
                           class="hover:text-blue-200 transition">{{ __('translations.navigation.access') }}</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-lg font-bold mb-4">{{ __('translations.navigation.contact') }}</h3>
                <ul class="space-y-2">
                    <li class="flex items-center"><i class="fas fa-envelope mr-2"></i> {{ config('site.contact.email') }}</li>
                </ul>

            </div>

            <div>
                <h3 class="text-lg font-bold mb-4">{{ __('translations.navigation.purpose') }}</h3>
                <p class="text-white text-sm opacity-80" style="line-height: 1.5;">
                    {{ __('translations.footer.purpose_text') }}
                </p>
            </div>
        </div>
        <div class="border-t border-blue-500 pt-6 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <span>&copy; {{ date('Y') }} {{ config('site.name') }}. {{ __('translations.footer.rights_reserved') }}</span>
            </div>
            <div class="flex space-x-4">
                <a href="{{ localized_route('privacy') }}"
                   class="text-white hover:text-blue-200 transition text-sm">{{ __('translations.footer.privacy_policy') }}</a>
                <a href="{{ localized_route('terms') }}"
                   class="text-white hover:text-blue-200 transition text-sm">{{ __('translations.footer.terms') }}</a>
                <a href="{{ localized_route('cookies') }}"
                   class="text-white hover:text-blue-200 transition text-sm">{{ __('translations.footer.cookies') }}</a>
            </div>
        </div>

        <div class="footer-tag mt-6 text-center opacity-70 text-sm border-t border-opacity-10 border-white pt-6">
            <p>{{ __('translations.footer.tagline') }}</p>
        </div>
    </div>
</footer>
