@extends('layouts.app')

@section('title', __('translations.about.title'))

@section('page', 'about')

@section('content')
    <!-- Hero Section -->
    <section class="hero-mini gradient-bg text-white py-20 relative overflow-hidden">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-6">{{ __('translations.about.title') }}</h1>
                <p class="text-xl opacity-90">{{ __('translations.about.subtitle') }}</p>
            </div>
        </div>
    </section>

    <!-- Vision Section -->
    <section class="section py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold mb-8">{{ __('translations.about.vision_title') }}</h2>
                <div class="bg-gray-50 rounded-xl p-8 border border-gray-200 mb-8">
                    <p class="text-xl leading-relaxed">
                        {{ __('translations.about.vision_highlight') }}
                    </p>
                </div>

                <p class="text-gray-700 mb-6">
                    {{ __('translations.about.vision_p1') }}
                </p>

                <p class="text-gray-700 mb-6">
                    {{ __('translations.about.vision_p2') }}
                </p>

                <p class="text-gray-700">
                    {{ __('translations.about.vision_p3') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Manifesto Quote -->
    <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-blue-50 rounded-xl p-8 border border-blue-100 my-12 text-center">
                <p class="text-xl italic leading-relaxed text-blue-800">
                    {{ __('translations.about.manifesto_quote') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Why Section -->
    <section class="section py-16 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold mb-8">{{ __('translations.about.why_title') }}</h2>
                <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
                    <p class="text-xl leading-relaxed">
                        {{ __('translations.about.why_highlight') }}
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-xl font-bold mb-4">{{ __('translations.about.differences_title') }}</h3>
                        <ul class="space-y-3">
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span>{{ __('translations.about.difference1') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span>{{ __('translations.about.difference2') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span>{{ __('translations.about.difference3') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span>{{ __('translations.about.difference4') }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-4">{{ __('translations.about.values_title') }}</h3>
                        <ul class="space-y-3">
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <span>{{ __('translations.about.value1') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-code"></i>
                                </div>
                                <span>{{ __('translations.about.value2') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span>{{ __('translations.about.value3') }}</span>
                            </li>
                            <li class="flex">
                                <div class="flex-shrink-0 text-blue-600 mr-2">
                                    <i class="fas fa-hand-holding-heart"></i>
                                </div>
                                <span>{{ __('translations.about.value4') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="bg-gray-100 rounded-lg p-6 border border-gray-200">
                    <h3 class="text-xl font-bold mb-4">{{ __('translations.about.commitments_title') }}</h3>

                    <div class="mb-4">
                        <h4 class="font-bold">1. {{ __('translations.about.commitment1_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment1_desc') }}</p>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-bold">2. {{ __('translations.about.commitment2_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment2_desc') }}</p>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-bold">3. {{ __('translations.about.commitment3_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment3_desc') }}</p>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-bold">4. {{ __('translations.about.commitment4_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment4_desc') }}</p>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-bold">5. {{ __('translations.about.commitment5_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment5_desc') }}</p>
                    </div>

                    <div>
                        <h4 class="font-bold">6. {{ __('translations.about.commitment6_title') }}</h4>
                        <p class="text-gray-700">{{ __('translations.about.commitment6_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Future Vision Section -->
    <section class="section py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold mb-8">{{ __('translations.about.future_title') }}</h2>

                <div class="grid md:grid-cols-2 gap-8">
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <div class="text-4xl mb-4">🌐</div>
                        <h3 class="text-xl font-bold mb-2">{{ __('translations.about.future1_title') }}</h3>
                        <p class="text-gray-700">{{ __('translations.about.future1_desc') }}</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <div class="text-4xl mb-4">🏆</div>
                        <h3 class="text-xl font-bold mb-2">{{ __('translations.about.future2_title') }}</h3>
                        <p class="text-gray-700">{{ __('translations.about.future2_desc') }}</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <div class="text-4xl mb-4">🧠</div>
                        <h3 class="text-xl font-bold mb-2">{{ __('translations.about.future3_title') }}</h3>
                        <p class="text-gray-700">{{ __('translations.about.future3_desc') }}</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <div class="text-4xl mb-4">💡</div>
                        <h3 class="text-xl font-bold mb-2">{{ __('translations.about.future4_title') }}</h3>
                        <p class="text-gray-700">{{ __('translations.about.future4_desc') }}</p>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <a href="{{ localized_route('manifesto') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md inline-block transition">
                        {{ __('translations.about.read_manifesto') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection

@push('styles')
    <style>
        .gradient-bg {
            background: linear-gradient(145deg, #2563eb, #3b82f6);
        }

        .hero-mini {
            padding-top: 6rem;
            padding-bottom: 4rem;
        }
    </style>
@endpush
