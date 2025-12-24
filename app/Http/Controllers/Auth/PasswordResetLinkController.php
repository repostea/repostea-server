<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PasswordResetLinkController extends Controller
{
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'email' => 'required|email',
            'cf-turnstile-response' => ['required', Rule::turnstile()],
        ]);

        $validator->validate();

        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}
