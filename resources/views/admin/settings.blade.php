@extends('admin.layout')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="p-8">
    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
            <div>
                <h3 class="font-semibold text-blue-900 mb-1">Frontend Configuration Panel</h3>
                <p class="text-sm text-blue-700">
                    These settings control how users interact with the platform frontend. Changes take effect immediately on the public pages.
                </p>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200">
        <div class="p-6">
            <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')

                <!-- Registration Mode -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user-plus mr-2 text-blue-500"></i>
                        Registration Mode
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Control how users can register on the platform</p>

                    <div class="space-y-3">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="registration_mode" value="open"
                                {{ $settings['registration_mode']['value'] === 'open' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Open Registration</span>
                                <p class="text-sm text-gray-500">Anyone can register without an invitation code</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="registration_mode" value="invite_only"
                                {{ $settings['registration_mode']['value'] === 'invite_only' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Invitation Only</span>
                                <p class="text-sm text-gray-500">Users need a valid invitation code to register</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="registration_mode" value="closed"
                                {{ $settings['registration_mode']['value'] === 'closed' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Registration Closed</span>
                                <p class="text-sm text-gray-500">No new registrations allowed</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Registration Approval -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user-check mr-2 text-orange-500"></i>
                        Registration Approval
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Control whether new registrations require administrator approval</p>

                    <div class="space-y-3">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="registration_approval" value="none"
                                {{ $settings['registration_approval']['value'] === 'none' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">None (Auto-approve)</span>
                                <p class="text-sm text-gray-500">New users are automatically approved and can access the platform immediately</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="registration_approval" value="required"
                                {{ $settings['registration_approval']['value'] === 'required' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Required (Manual approval)</span>
                                <p class="text-sm text-gray-500">New users must wait for administrator approval before accessing the platform</p>
                            </div>
                        </label>
                    </div>

                    @if($settings['registration_approval']['value'] === 'required')
                    <div class="mt-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                        <p class="text-sm text-orange-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Pending users can be managed at
                            <a href="{{ route('admin.users.pending') }}" class="font-semibold underline hover:no-underline">
                                /admin/users/pending
                            </a>
                        </p>
                    </div>
                    @endif
                </div>

                <!-- Email Verification -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-envelope-open-text mr-2 text-green-500"></i>
                        Email Verification
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Configure email verification requirements</p>

                    <div class="space-y-3">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="email_verification" value="required"
                                {{ $settings['email_verification']['value'] === 'required' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Required</span>
                                <p class="text-sm text-gray-500">Users must verify their email before using the account</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="email_verification" value="optional"
                                {{ $settings['email_verification']['value'] === 'optional' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Optional</span>
                                <p class="text-sm text-gray-500">Email verification gives benefits but is not required</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="email_verification" value="disabled"
                                {{ $settings['email_verification']['value'] === 'disabled' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Disabled</span>
                                <p class="text-sm text-gray-500">No email verification process</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Guest Access -->
                <div class="pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user-secret mr-2 text-purple-500"></i>
                        Guest Access
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Allow or disable guest user access</p>

                    <div class="space-y-3">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="guest_access" value="enabled"
                                {{ $settings['guest_access']['value'] === 'enabled' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Enabled</span>
                                <p class="text-sm text-gray-500">Allow users to access the platform as guests</p>
                            </div>
                        </label>

                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="guest_access" value="disabled"
                                {{ $settings['guest_access']['value'] === 'disabled' ? 'checked' : '' }}
                                class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">Disabled</span>
                                <p class="text-sm text-gray-500">Guest access is not allowed, users must register or log in</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.dashboard') }}"
                       class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
