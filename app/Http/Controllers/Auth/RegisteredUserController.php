<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\NewUserRegistrationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

final class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        // Set locale from request for validation messages
        $locale = $request->input('locale', 'es');
        if (in_array($locale, ['es', 'en'])) {
            app()->setLocale($locale);
        }

        // Check registration mode
        $registrationMode = SystemSetting::get('registration_mode', 'invite_only');
        $registrationApproval = SystemSetting::get('registration_approval', 'none');

        if ($registrationMode === 'closed') {
            return response()->json([
                'message' => 'Registration is currently closed.',
            ], 403);
        }

        // Build validation rules
        $rules = [
            'username' => ['required', 'string', 'max:25', 'alpha_dash', 'unique:users'],
            'email' => ['required', 'string', 'lowercase',  'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cf-turnstile-response' => ['required', Rule::turnstile()],
            'locale' => ['sometimes', 'string', 'size:2'],
        ];

        // Add invitation requirement based on registration mode
        if ($registrationMode === 'invite_only') {
            $rules['invitation'] = ['required', 'string', 'exists:invitations,code'];
        }

        $validator = validator($request->all(), $rules);
        $validator->validate();

        // Verify invitation if in invite-only mode
        $invitation = null;
        if ($registrationMode === 'invite_only') {
            $invitation = Invitation::findValidByCode($request->invitation);

            if (! $invitation) {
                throw ValidationException::withMessages([
                    'invitation' => ['The invitation code is invalid, expired, or has been fully used.'],
                ]);
            }
        }

        // Determine user status based on approval setting
        $status = $registrationApproval === 'required' ? 'pending' : 'approved';

        $user = User::create([
            'username' => Str::slug($request->username),
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $status,
            'karma_points' => 0,
            'locale' => $request->input('locale') ?? 'en',
        ]);

        $noviceLevel = \App\Models\KarmaLevel::where('required_karma', 0)->first();
        if ($noviceLevel !== null) {
            $user->highest_level_id = $noviceLevel->id;
            $user->save();
        }

        // Mark invitation as used (only if invitation mode was used)
        if ($invitation) {
            $invitation->markAsUsed($user->id);
        }

        event(new Registered($user));

        // Notify admins if user is pending approval
        if ($status === 'pending') {
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $admins = $adminRole->users;
                foreach ($admins as $admin) {
                    $admin->notify(new NewUserRegistrationNotification($user));
                }
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Check if email verification is required
        $emailVerificationMode = SystemSetting::get('email_verification', 'optional');
        $requiresVerification = $emailVerificationMode === 'required' && ! $user->hasVerifiedEmail();

        // Return different response based on user status
        if ($status === 'pending') {
            return response()->json([
                'user' => new UserResource($user),
                'token' => $token,
                'status' => 'pending',
                'message' => 'Your account has been created and is pending approval. You will receive a notification once an administrator reviews your registration.',
                'email_verification_required' => $requiresVerification,
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'email_verification_required' => $requiresVerification,
        ]);
    }
}
