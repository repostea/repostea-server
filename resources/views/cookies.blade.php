@extends('layouts.app')

@section('title', __('legal.cookies.page_title'))

@section('page', 'cookies')

@section('content')
    <div class="container mx-auto px-6 py-16">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">{{ __('legal.cookies.title') }}</h1>

            <div class="bg-gray-50 p-6 rounded-lg mb-8 border border-gray-200">
                <p class="text-lg text-gray-700">{{ __('legal.cookies.summary') }}</p>
            </div>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.what_are.title') }}</h2>
            <p class="mb-6">{{ __('legal.cookies.what_are.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.types_used.title') }}</h2>
            <p class="mb-4">{{ __('legal.cookies.types_used.content') }}</p>

            <ul class="list-disc pl-6 mb-6 space-y-2">
                <li><strong>{{ __('legal.cookies.types_used.essential.title') }}:</strong> {{ __('legal.cookies.types_used.essential.content') }}</li>
                <li><strong>{{ __('legal.cookies.types_used.session.title') }}:</strong> {{ __('legal.cookies.types_used.session.content') }}</li>
                <li><strong>{{ __('legal.cookies.types_used.preferences.title') }}:</strong> {{ __('legal.cookies.types_used.preferences.content') }}</li>
            </ul>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.types_not_used.title') }}</h2>
            <p class="mb-4">{{ __('legal.cookies.types_not_used.content') }}</p>

            <ul class="list-disc pl-6 mb-6 space-y-2">
                <li><strong>{{ __('legal.cookies.types_not_used.analytics.title') }}:</strong> {{ __('legal.cookies.types_not_used.analytics.content') }}</li>
                <li><strong>{{ __('legal.cookies.types_not_used.advertising.title') }}:</strong> {{ __('legal.cookies.types_not_used.advertising.content') }}</li>
                <li><strong>{{ __('legal.cookies.types_not_used.third_party.title') }}:</strong> {{ __('legal.cookies.types_not_used.third_party.content') }}</li>
                <li><strong>{{ __('legal.cookies.types_not_used.tracking.title') }}:</strong> {{ __('legal.cookies.types_not_used.tracking.content') }}</li>
            </ul>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.managing.title') }}</h2>
            <p class="mb-6">{{ __('legal.cookies.managing.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.changes.title') }}</h2>
            <p class="mb-6">{{ __('legal.cookies.changes.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.cookies.contact.title') }}</h2>
            <p>{{ __('legal.cookies.contact.content', ['contact_email' => config('site.contact.email')]) }}</p>

            <div class="mt-12 text-sm text-gray-600">
                <p>{{ __('legal.last_updated', ['date' => '10/11/2025']) }}</p>
            </div>
        </div>
    </div>
@endsection
