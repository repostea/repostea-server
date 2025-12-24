@extends('layouts.app')

@section('page', 'home')

@section('content')
    <!-- Hero Section -->
    <section class="hero gradient-bg text-white min-h-screen flex items-center py-24 -mt-24 relative overflow-hidden">
        <div class="container mx-auto px-6">
            <div class="hero-content max-w-3xl mx-auto text-center">
                <span
                    class="ethical-badge inline-flex items-center bg-white bg-opacity-20 text-white px-4 py-2 rounded-full text-sm font-semibold mb-6">
                    <i class="fas fa-shield-alt mr-2"></i> {{ __('translations.home.ethical_badge') }}
                </span>
                <h1 class="text-4xl md:text-5xl font-bold mb-6 leading-tight">{{ __('translations.home.hero_title') }}</h1>
                <p class="text-xl mb-8 opacity-90">{{ __('translations.home.hero_description') }}</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ config('app.client_url') }}/{{ app()->getLocale() }}/"
                       class="bg-white text-blue-600 hover:bg-opacity-90 px-6 py-3 rounded-lg font-semibold transition">
                        {{ __('translations.home.hero_button_primary') }}
                    </a>
                    <a href="{{ localized_route('manifesto') }}"
                       class="btn-secondary bg-transparent border-2 border-white text-white hover:bg-white hover:bg-opacity-10 px-6 py-3 rounded-lg font-semibold transition">
                        {{ __('translations.home.hero_button_secondary') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section py-16 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="section-title text-3xl font-bold text-center mb-12">{{ __('translations.home.features_title') }}</h2>
            <div class="features grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="feature-card bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition text-center">
                    <div
                        class="feature-icon w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 text-xl mx-auto mb-6">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">{{ __('translations.home.feature1_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.feature1_description') }}</p>
                </div>
                <div class="feature-card bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition text-center">
                    <div
                        class="feature-icon w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 text-xl mx-auto mb-6">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">{{ __('translations.home.feature2_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.feature2_description') }}</p>
                </div>
                <div class="feature-card bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition text-center">
                    <div
                        class="feature-icon w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 text-xl mx-auto mb-6">
                        <i class="fas fa-code"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">{{ __('translations.home.feature3_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.feature3_description') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section gradient-bg text-white py-16 text-center">
        <div class="container mx-auto px-6">
            <h2 class="section-title text-3xl font-bold mb-8 text-white">{{ __('translations.home.cta_title') }}</h2>

            <div class="cta-cards grid md:grid-cols-3 gap-6 max-w-4xl mx-auto mb-8">
                <div class="cta-card bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                    <div class="cta-card-icon text-4xl mb-4">🧠</div>
                    <h3 class="text-xl font-bold mb-2">{{ __('translations.home.cta_feature1_title') }}</h3>
                    <p class="text-white text-opacity-80">{{ __('translations.home.cta_feature1_desc') }}</p>
                </div>

                <div class="cta-card bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                    <div class="cta-card-icon text-4xl mb-4">🏆</div>
                    <h3 class="text-xl font-bold mb-2">{{ __('translations.home.cta_feature2_title') }}</h3>
                    <p class="text-white text-opacity-80">{{ __('translations.home.cta_feature2_desc') }}</p>
                </div>

                <div class="cta-card bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                    <div class="cta-card-icon text-4xl mb-4">👥</div>
                    <h3 class="text-xl font-bold mb-2">{{ __('translations.home.cta_feature3_title') }}</h3>
                    <p class="text-white text-opacity-80">{{ __('translations.home.cta_feature3_desc') }}</p>
                </div>
            </div>

            <div class="cta-buttons flex flex-wrap justify-center gap-4">
                <a href="{{ config('app.client_url') }}/{{ app()->getLocale() }}/auth/register"
                   class="bg-white text-blue-600 hover:bg-opacity-90 px-6 py-3 rounded-lg font-semibold transition">
                    {{ __('translations.home.cta_button_primary') }}
                </a>
                <a href="{{ localized_route('manifesto') }}"
                   class="bg-transparent border-2 border-white text-white hover:bg-white hover:bg-opacity-10 px-6 py-3 rounded-lg font-semibold transition">
                    {{ __('translations.home.cta_button_secondary') }}
                </a>
            </div>
        </div>
    </section>

    <!-- How it Works Section -->
    <section class="section py-16 bg-gray-50">
        <div class="container mx-auto px-6">
            <h2 class="section-title text-3xl font-bold text-center mb-12">{{ __('translations.home.how_it_works_title') }}</h2>
            <div class="steps grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="step-card bg-white rounded-lg p-6 text-center relative">
                    <div
                        class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-xl">
                        1
                    </div>
                    <h3 class="text-xl font-bold mt-6 mb-4">{{ __('translations.home.how_it_works_step1_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.how_it_works_step1_desc') }}</p>
                </div>
                <div class="step-card bg-white rounded-lg p-6 text-center relative">
                    <div
                        class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-xl">
                        2
                    </div>
                    <h3 class="text-xl font-bold mt-6 mb-4">{{ __('translations.home.how_it_works_step2_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.how_it_works_step2_desc') }}</p>
                </div>
                <div class="step-card bg-white rounded-lg p-6 text-center relative">
                    <div
                        class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-xl">
                        3
                    </div>
                    <h3 class="text-xl font-bold mt-6 mb-4">{{ __('translations.home.how_it_works_step3_title') }}</h3>
                    <p class="text-gray-600">{{ __('translations.home.how_it_works_step3_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Karma System Section -->
    <section class="section py-16 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="section-title text-3xl font-bold text-center mb-12">{{ __('translations.home.karma_system_title') }}</h2>
            <div class="karma-system grid md:grid-cols-2 gap-12 max-w-5xl mx-auto">
                <div class="karma-explanation">
                    <h3 class="text-2xl font-bold mb-6">{{ __('translations.home.karma_explanation_title') }}</h3>
                    <p class="text-gray-600 mb-8">{{ __('translations.home.karma_explanation_desc') }}</p>

                    <div class="karma-point flex items-start mb-6">
                        <div
                            class="karma-icon w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mr-4">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div>
                            <h4 class="font-bold mb-1">{{ __('translations.home.karma_upvotes') }}</h4>
                            <p class="text-gray-600">{{ __('translations.home.karma_upvotes_desc') }}</p>
                        </div>
                    </div>

                    <div class="karma-point flex items-start mb-6">
                        <div
                            class="karma-icon w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mr-4">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div>
                            <h4 class="font-bold mb-1">{{ __('translations.home.karma_comments') }}</h4>
                            <p class="text-gray-600">{{ __('translations.home.karma_comments_desc') }}</p>
                        </div>
                    </div>

                    <div class="karma-point flex items-start">
                        <div
                            class="karma-icon w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mr-4">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h4 class="font-bold mb-1">{{ __('translations.home.karma_levels') }}</h4>
                            <p class="text-gray-600">{{ __('translations.home.karma_levels_desc') }}</p>
                        </div>
                    </div>
                </div>

                <div class="karma-levels bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h3 class="text-xl font-bold mb-6 text-center">{{ __('translations.home.karma_levels_examples') }}</h3>

                    <div class="karma-level flex justify-between items-center py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <span class="level-icon text-2xl mr-3">🌱</span>
                            <span class="font-medium">{{ __('translations.home.karma_novice') }}</span>
                        </div>
                        <span class="text-gray-500">0 {{ __('translations.home.karma_points') }}</span>
                    </div>

                    <div class="karma-level flex justify-between items-center py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <span class="level-icon text-2xl mr-3">🌟</span>
                            <span class="font-medium">{{ __('translations.home.karma_contributor') }}</span>
                        </div>
                        <span class="text-gray-500">200 {{ __('translations.home.karma_points') }}</span>
                    </div>

                    <div class="karma-level flex justify-between items-center py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <span class="level-icon text-2xl mr-3">🏆</span>
                            <span class="font-medium">{{ __('translations.home.karma_expert') }}</span>
                        </div>
                        <span class="text-gray-500">500 {{ __('translations.home.karma_points') }}</span>
                    </div>

                    <div class="karma-level flex justify-between items-center py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <span class="level-icon text-2xl mr-3">👑</span>
                            <span class="font-medium">{{ __('translations.home.karma_mentor') }}</span>
                        </div>
                        <span class="text-gray-500">1000 {{ __('translations.home.karma_points') }}</span>
                    </div>

                    <div class="karma-level flex justify-between items-center py-4">
                        <div class="flex items-center">
                            <span class="level-icon text-2xl mr-3">⭐</span>
                            <span class="font-medium">{{ __('translations.home.karma_legend') }}</span>
                        </div>
                        <span class="text-gray-500">5000 {{ __('translations.home.karma_points') }}</span>
                    </div>

                    <p class="text-center text-gray-500 text-sm mt-6">
                        {{ __('translations.home.karma_permanent_note') }}
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
