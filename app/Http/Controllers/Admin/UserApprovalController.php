<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Http\Request;

final class UserApprovalController extends Controller
{
    /**
     * List all pending users.
     */
    public function index(Request $request)
    {
        $query = User::pending()
            ->orderBy('created_at', 'desc');

        // Optional search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'users' => AdminUserResource::collection($users),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ]);
        }

        // Get stats for the view
        $stats = [
            'pending' => User::pending()->count(),
            'approved' => User::approved()->count(),
            'rejected' => User::rejected()->count(),
        ];

        // Return Blade view for web requests
        return view('admin.users.pending', [
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Get statistics about user approvals.
     */
    public function stats()
    {
        return response()->json([
            'pending' => User::pending()->count(),
            'approved' => User::approved()->count(),
            'rejected' => User::rejected()->count(),
        ]);
    }

    /**
     * Approve a user.
     */
    public function approve(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if ($user->status === 'approved') {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'User is already approved.',
                ], 400);
            }

            return redirect()->back()->with('error', 'User is already approved.');
        }

        $user->status = 'approved';
        $user->rejection_reason = null; // Clear any previous rejection reason
        $user->save();

        // Send notification to user about approval
        $user->notify(new AccountApprovedNotification());

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'User approved successfully.',
                'user' => new AdminUserResource($user),
            ]);
        }

        return redirect()->route('admin.users.pending')->with('success', 'User approved successfully!');
    }

    /**
     * Reject a user.
     */
    public function reject(Request $request, int $userId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = User::findOrFail($userId);

        if ($user->status === 'rejected') {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'User is already rejected.',
                ], 400);
            }

            return redirect()->back()->with('error', 'User is already rejected.');
        }

        $user->status = 'rejected';
        $user->rejection_reason = $request->input('reason');
        $user->save();

        // Send notification to user about rejection with reason
        $user->notify(new AccountRejectedNotification($user->rejection_reason));

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'User rejected successfully.',
                'user' => new AdminUserResource($user),
            ]);
        }

        return redirect()->route('admin.users.pending')->with('success', 'User rejected successfully!');
    }

    /**
     * Get a specific user by ID (for review).
     */
    public function show(int $userId)
    {
        $user = User::findOrFail($userId);

        return response()->json([
            'user' => new AdminUserResource($user),
        ]);
    }
}
