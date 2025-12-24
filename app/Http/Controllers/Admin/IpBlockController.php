<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBlock;
use App\Models\ModerationLog;
use App\Models\RateLimitLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class IpBlockController extends Controller
{
    /**
     * Display IP blocks dashboard.
     */
    public function index(Request $request)
    {
        $query = IpBlock::with('blockedBy:id,username')->orderByDesc('created_at');

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'expired') {
                $query->expired();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by block_type
        if ($request->filled('block_type')) {
            $query->where('block_type', $request->block_type);
        }

        // Search by IP
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $blocks = $query->paginate(50);

        $stats = [
            'total_blocks' => IpBlock::count(),
            'active_blocks' => IpBlock::active()->count(),
            'expired_blocks' => IpBlock::expired()->count(),
            'permanent_blocks' => IpBlock::permanent()->count(),
            'temporary_blocks' => IpBlock::temporary()->count(),
            'total_hits_blocked' => IpBlock::sum('hit_count'),
            'most_hit_ips' => IpBlock::getMostBlockedIps(5),
        ];

        return view('admin.ip-blocks.index', compact('blocks', 'stats'));
    }

    /**
     * Show form to create new IP block.
     */
    public function create(Request $request)
    {
        // Pre-fill IP if coming from abuse monitoring
        $suggestedIp = $request->query('ip');
        $suggestedReason = $request->query('reason');

        // Get recent violations for this IP if available
        $recentViolations = null;
        if ($suggestedIp) {
            $recentViolations = RateLimitLog::byIp($suggestedIp)
                ->recent(24)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        return view('admin.ip-blocks.create', compact('suggestedIp', 'suggestedReason', 'recentViolations'));
    }

    /**
     * Store new IP block.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|string|max:255',
            'type' => 'required|in:single,range,pattern',
            'ip_range_start' => 'required_if:type,range|nullable|ip',
            'ip_range_end' => 'required_if:type,range|nullable|ip',
            'block_type' => 'required|in:temporary,permanent',
            'expires_at' => 'required_if:block_type,temporary|nullable|date|after:now',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $data['blocked_by'] = $request->user()->id;
        $data['is_active'] = true;

        $block = IpBlock::create($data);

        // Clear cache for this IP
        IpBlock::clearIpCache($data['ip_address']);

        // Log the action
        ModerationLog::logAction(
            moderatorId: $request->user()->id,
            action: 'block_ip',
            reason: "Blocked IP: {$data['ip_address']} - {$data['reason']}",
            metadata: [
                'ip_address' => $data['ip_address'],
                'type' => $data['type'],
                'block_type' => $data['block_type'],
                'expires_at' => $data['expires_at'] ?? null,
            ],
        );

        return redirect()->route('admin.ip-blocks.index')
            ->with('success', 'IP address blocked successfully.');
    }

    /**
     * Show IP block details.
     */
    public function show(IpBlock $ipBlock)
    {
        $ipBlock->load('blockedBy:id,username,email');

        // Get recent violations from this IP
        $recentViolations = RateLimitLog::byIp($ipBlock->ip_address)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Get moderation logs related to this block
        $moderationLogs = ModerationLog::where('metadata->ip_address', $ipBlock->ip_address)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.ip-blocks.show', compact('ipBlock', 'recentViolations', 'moderationLogs'));
    }

    /**
     * Show form to edit IP block.
     */
    public function edit(IpBlock $ipBlock)
    {
        return view('admin.ip-blocks.edit', compact('ipBlock'));
    }

    /**
     * Update IP block.
     */
    public function update(Request $request, IpBlock $ipBlock)
    {
        $validator = Validator::make($request->all(), [
            'block_type' => 'required|in:temporary,permanent',
            'expires_at' => 'required_if:block_type,temporary|nullable|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $oldData = $ipBlock->only(['block_type', 'expires_at', 'reason', 'is_active']);
        $ipBlock->update($validator->validated());

        // Clear cache
        IpBlock::clearIpCache($ipBlock->ip_address);

        // Log the change
        ModerationLog::logAction(
            moderatorId: $request->user()->id,
            action: 'update_ip_block',
            reason: "Updated IP block: {$ipBlock->ip_address}",
            metadata: [
                'ip_address' => $ipBlock->ip_address,
                'old_data' => $oldData,
                'new_data' => $validator->validated(),
            ],
        );

        return redirect()->route('admin.ip-blocks.show', $ipBlock)
            ->with('success', 'IP block updated successfully.');
    }

    /**
     * Remove IP block.
     */
    public function destroy(Request $request, IpBlock $ipBlock)
    {
        $ipAddress = $ipBlock->ip_address;

        $ipBlock->delete();

        // Clear cache
        IpBlock::clearIpCache($ipAddress);

        // Log the action
        ModerationLog::logAction(
            moderatorId: $request->user()->id,
            action: 'unblock_ip',
            reason: "Removed IP block: {$ipAddress}",
            metadata: [
                'ip_address' => $ipAddress,
                'reason' => $ipBlock->reason,
                'hit_count' => $ipBlock->hit_count,
            ],
        );

        return redirect()->route('admin.ip-blocks.index')
            ->with('success', 'IP block removed successfully.');
    }

    /**
     * Bulk block IPs from abuse monitoring.
     */
    public function bulkBlock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ips' => 'required|array|min:1',
            'ips.*' => 'required|ip',
            'block_type' => 'required|in:temporary,permanent',
            'expires_at' => 'required_if:block_type,temporary|nullable|date|after:now',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $blocked = 0;
        $skipped = 0;

        foreach ($request->ips as $ip) {
            // Check if already blocked
            if (IpBlock::isIpBlocked($ip)) {
                $skipped++;

                continue;
            }

            IpBlock::create([
                'ip_address' => $ip,
                'type' => 'single',
                'block_type' => $request->block_type,
                'expires_at' => $request->expires_at,
                'reason' => $request->reason,
                'blocked_by' => $request->user()->id,
                'is_active' => true,
            ]);

            IpBlock::clearIpCache($ip);
            $blocked++;
        }

        // Log bulk action
        ModerationLog::logAction(
            moderatorId: $request->user()->id,
            action: 'bulk_block_ips',
            reason: "Bulk blocked {$blocked} IPs: {$request->reason}",
            metadata: [
                'blocked_count' => $blocked,
                'skipped_count' => $skipped,
                'ips' => $request->ips,
            ],
        );

        return redirect()->route('admin.ip-blocks.index')
            ->with('success', "Blocked {$blocked} IP(s). Skipped {$skipped} already blocked.");
    }

    /**
     * Quick block from abuse monitoring page.
     */
    public function quickBlock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip',
            'duration' => 'required|in:1h,24h,7d,30d,permanent',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Check if already blocked
        if (IpBlock::isIpBlocked($request->ip)) {
            return response()->json(['error' => 'IP is already blocked'], 400);
        }

        $blockType = $request->duration === 'permanent' ? 'permanent' : 'temporary';
        $expiresAt = null;

        if ($blockType === 'temporary') {
            $expiresAt = match ($request->duration) {
                '1h' => now()->addHour(),
                '24h' => now()->addDay(),
                '7d' => now()->addWeek(),
                '30d' => now()->addMonth(),
                default => null,
            };
        }

        $block = IpBlock::create([
            'ip_address' => $request->ip,
            'type' => 'single',
            'block_type' => $blockType,
            'expires_at' => $expiresAt,
            'reason' => $request->reason,
            'blocked_by' => $request->user()->id,
            'is_active' => true,
        ]);

        IpBlock::clearIpCache($request->ip);

        ModerationLog::logAction(
            moderatorId: $request->user()->id,
            action: 'quick_block_ip',
            reason: "Quick blocked IP: {$request->ip} for {$request->duration}",
        );

        return response()->json([
            'success' => true,
            'message' => 'IP blocked successfully',
            'block' => $block,
        ]);
    }
}
