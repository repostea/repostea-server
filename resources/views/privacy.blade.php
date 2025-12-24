@extends('layouts.app')

@section('title', __('legal.privacy.page_title'))

@section('page', 'privacy')

@section('content')
    <div class="container mx-auto px-6 py-16">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">{{ __('legal.privacy.title') }}</h1>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.responsible.title') }}</h2>
            <ul class="list-none pl-0 mb-6 space-y-2">
                <li>{{ __('legal.privacy.responsible.owner', ['owner_name' => config('site.owner.name')]) }}</li>
                <li>{{ __('legal.privacy.responsible.dni', ['owner_dni' => config('site.owner.dni')]) }}</li>
                <li>{{ __('legal.privacy.responsible.email', ['contact_email' => config('site.contact.email')]) }}</li>
                <li>{{ __('legal.privacy.responsible.website', ['site_name' => config('site.name')]) }}</li>
            </ul>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.purpose.title') }}</h2>
            <p class="mb-4">{{ __('legal.privacy.purpose.intro') }}</p>
            <ul class="list-disc pl-6 mb-4 space-y-2">
                <li>{{ __('legal.privacy.purpose.item_1', ['site_name' => config('site.name')]) }}</li>
                <li>{{ __('legal.privacy.purpose.item_2', ['site_name' => config('site.name')]) }}</li>
                <li>{{ __('legal.privacy.purpose.item_3') }}</li>
            </ul>
            <p class="mb-6">{{ __('legal.privacy.purpose.note') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.legal_basis.title') }}</h2>
            <p class="mb-6">{{ __('legal.privacy.legal_basis.content', ['site_name' => config('site.name')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.data_retention.title') }}</h2>
            <p class="mb-6">{{ __('legal.privacy.data_retention.content', ['contact_email' => config('site.contact.email')]) }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.third_parties.title') }}</h2>
            <p class="mb-6">{{ __('legal.privacy.third_parties.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.user_rights.title') }}</h2>
            <p class="mb-4">{{ __('legal.privacy.user_rights.intro', ['contact_email' => config('site.contact.email')]) }}</p>
            <p class="mb-6">{{ __('legal.privacy.user_rights.complaint') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.data_security.title') }}</h2>
            <p class="mb-6">{{ __('legal.privacy.data_security.content') }}</p>

            <h2 class="text-2xl font-bold mb-4">{{ __('legal.privacy.cookies.title') }}</h2>
            <p class="mb-6">{{ __('legal.privacy.cookies.content', ['site_name' => config('site.name')]) }}</p>

            <div class="mt-12 text-sm text-gray-600">
                <p>{{ __('legal.last_updated', ['date' => '10/11/2025']) }}</p>
            </div>
        </div>
    </div>
@endsection
