@extends('layouts.app')

@section('title', __('legal.conditions.page_title'))

@section('page', 'terms')

@section('content')
    <div class="container mx-auto px-6 py-16">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">{{ __('legal.conditions.title') }}</h1>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.site_owner.title') }}</h2>
            <p class="mb-4">{{ __('legal.conditions.site_owner.intro') }}</p>
            <ul class="list-none pl-0 mb-6 space-y-2">
                <li>{{ __('legal.conditions.site_owner.site_name', ['site_name' => config('site.name')]) }}</li>
                <li>{{ __('legal.conditions.site_owner.owner', ['owner_name' => config('site.owner.name')]) }}</li>
                <li>{{ __('legal.conditions.site_owner.dni', ['owner_dni' => config('site.owner.dni')]) }}</li>
                <li>{{ __('legal.conditions.site_owner.contact_email', ['contact_email' => config('site.contact.email')]) }}</li>
                <li>{{ __('legal.conditions.site_owner.purpose', ['site_purpose' => config('site.purpose')]) }}</li>
            </ul>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.usage.title') }}</h2>
            <p class="mb-6">{{ __('legal.conditions.usage.content', ['site_name' => config('site.name')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.intellectual_property.title') }}</h2>
            <p class="mb-4">{{ __('legal.conditions.intellectual_property.content') }}</p>
            <p class="mb-6">{{ __('legal.conditions.intellectual_property.aggregator_note', ['site_name' => config('site.name')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.external_links.title') }}</h2>
            <p class="mb-6">{{ __('legal.conditions.external_links.content', ['site_name' => config('site.name')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.liability.title') }}</h2>
            <p class="mb-6">{{ __('legal.conditions.liability.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.moderation.title') }}</h2>
            <p class="mb-6">{{ __('legal.conditions.moderation.content', ['site_name' => config('site.name')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.conditions.law.title') }}</h2>
            <p class="mb-6">{{ __('legal.conditions.law.content', ['jurisdiction' => config('site.jurisdiction')]) }}</p>

            <div class="mt-12 text-sm text-gray-600">
                <p>{{ __('legal.last_updated', ['date' => '10/11/2025']) }}</p>
            </div>
        </div>
    </div>
@endsection
