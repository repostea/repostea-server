<?php

declare(strict_types=1);

use App\Models\ActivityPubBlockedInstance;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Create admin role if it doesn't exist (required for admin() factory state)
    Role::firstOrCreate(['slug' => 'admin'], ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role']);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

afterEach(function (): void {
    ActivityPubBlockedInstance::query()->delete();
});

describe('BlockedInstanceController', function (): void {
    describe('index', function (): void {
        it('returns list of blocked instances for admin', function (): void {
            ActivityPubBlockedInstance::blockDomain('spam.example.com', 'Spam instance');
            ActivityPubBlockedInstance::blockDomain('bad.example.org', 'Bad behavior');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances');

            $response->assertOk();
            $response->assertJsonCount(2, 'data');
        });

        it('filters by active status', function (): void {
            ActivityPubBlockedInstance::blockDomain('active.example.com');
            $inactive = ActivityPubBlockedInstance::blockDomain('inactive.example.com');
            $inactive->update(['is_active' => false]);

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances?active=true');

            $response->assertOk();
            $response->assertJsonCount(1, 'data');
        });

        it('filters by block type', function (): void {
            ActivityPubBlockedInstance::blockDomain('full.example.com', null, 'full');
            ActivityPubBlockedInstance::blockDomain('silence.example.com', null, 'silence');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances?block_type=silence');

            $response->assertOk();
            $response->assertJsonCount(1, 'data');
            expect($response->json('data.0.domain'))->toBe('silence.example.com');
        });

        it('denies access to non-admin users', function (): void {
            actingAs($this->user, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances');

            $response->assertForbidden();
        });
    });

    describe('store', function (): void {
        it('blocks a new instance', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = postJson('/api/v1/admin/federation/blocked-instances', [
                'domain' => 'spam.example.com',
                'reason' => 'Spam and abuse',
                'block_type' => 'full',
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.domain', 'spam.example.com');
            $response->assertJsonPath('data.reason', 'Spam and abuse');

            expect(ActivityPubBlockedInstance::isBlocked('spam.example.com'))->toBeTrue();
        });

        it('normalizes domain from URL', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = postJson('/api/v1/admin/federation/blocked-instances', [
                'domain' => 'https://spam.example.com/users/someone',
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.domain', 'spam.example.com');
        });

        it('validates required domain', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = postJson('/api/v1/admin/federation/blocked-instances', [
                'reason' => 'No domain provided',
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['domain']);
        });

        it('validates block type', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = postJson('/api/v1/admin/federation/blocked-instances', [
                'domain' => 'test.example.com',
                'block_type' => 'invalid',
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['block_type']);
        });

        it('prevents duplicate blocks', function (): void {
            ActivityPubBlockedInstance::blockDomain('duplicate.example.com');

            actingAs($this->admin, 'sanctum');
            $response = postJson('/api/v1/admin/federation/blocked-instances', [
                'domain' => 'duplicate.example.com',
            ]);

            $response->assertStatus(409);
        });
    });

    describe('update', function (): void {
        it('updates block reason', function (): void {
            $block = ActivityPubBlockedInstance::blockDomain('test.example.com', 'Old reason');

            actingAs($this->admin, 'sanctum');
            $response = patchJson("/api/v1/admin/federation/blocked-instances/{$block->id}", [
                'reason' => 'Updated reason',
            ]);

            $response->assertOk();
            expect($block->fresh()->reason)->toBe('Updated reason');
        });

        it('changes block type', function (): void {
            $block = ActivityPubBlockedInstance::blockDomain('test.example.com', null, 'full');

            actingAs($this->admin, 'sanctum');
            $response = patchJson("/api/v1/admin/federation/blocked-instances/{$block->id}", [
                'block_type' => 'silence',
            ]);

            $response->assertOk();
            expect($block->fresh()->block_type)->toBe('silence');
        });

        it('deactivates block', function (): void {
            $block = ActivityPubBlockedInstance::blockDomain('test.example.com');

            actingAs($this->admin, 'sanctum');
            $response = patchJson("/api/v1/admin/federation/blocked-instances/{$block->id}", [
                'is_active' => false,
            ]);

            $response->assertOk();
            expect(ActivityPubBlockedInstance::isBlocked('test.example.com'))->toBeFalse();
        });
    });

    describe('destroy', function (): void {
        it('deletes block', function (): void {
            $block = ActivityPubBlockedInstance::blockDomain('test.example.com');

            actingAs($this->admin, 'sanctum');
            $response = deleteJson("/api/v1/admin/federation/blocked-instances/{$block->id}");

            $response->assertOk();
            expect(ActivityPubBlockedInstance::where('domain', 'test.example.com')->exists())->toBeFalse();
        });
    });

    describe('check', function (): void {
        it('returns blocked status for blocked domain', function (): void {
            ActivityPubBlockedInstance::blockDomain('blocked.example.com', 'Test reason', 'full');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances/check?domain=blocked.example.com');

            $response->assertOk();
            $response->assertJsonPath('status.blocked', true);
            $response->assertJsonPath('status.reason', 'Test reason');
        });

        it('returns silenced status for silenced domain', function (): void {
            ActivityPubBlockedInstance::blockDomain('silenced.example.com', null, 'silence');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances/check?domain=silenced.example.com');

            $response->assertOk();
            $response->assertJsonPath('status.blocked', false);
            $response->assertJsonPath('status.silenced', true);
        });

        it('returns not blocked status for unblocked domain', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/blocked-instances/check?domain=clean.example.com');

            $response->assertOk();
            $response->assertJsonPath('status.blocked', false);
            $response->assertJsonPath('status.silenced', false);
        });
    });
});

describe('ActivityPubBlockedInstance Model', function (): void {
    it('caches blocked domains', function (): void {
        ActivityPubBlockedInstance::blockDomain('cached.example.com');

        // First call should cache
        $result1 = ActivityPubBlockedInstance::isBlocked('cached.example.com');
        expect($result1)->toBeTrue();

        // Second call should use cache
        $result2 = ActivityPubBlockedInstance::isBlocked('cached.example.com');
        expect($result2)->toBeTrue();
    });

    it('clears cache when domain is blocked', function (): void {
        expect(ActivityPubBlockedInstance::isBlocked('new.example.com'))->toBeFalse();

        ActivityPubBlockedInstance::blockDomain('new.example.com');

        expect(ActivityPubBlockedInstance::isBlocked('new.example.com'))->toBeTrue();
    });

    it('clears cache when domain is unblocked', function (): void {
        ActivityPubBlockedInstance::blockDomain('unblock.example.com');
        expect(ActivityPubBlockedInstance::isBlocked('unblock.example.com'))->toBeTrue();

        ActivityPubBlockedInstance::unblockDomain('unblock.example.com');

        expect(ActivityPubBlockedInstance::isBlocked('unblock.example.com'))->toBeFalse();
    });

    it('respects expiration date', function (): void {
        ActivityPubBlockedInstance::blockDomain(
            'expired.example.com',
            null,
            'full',
            null,
            now()->subHour(),
        );

        expect(ActivityPubBlockedInstance::isBlocked('expired.example.com'))->toBeFalse();
    });

    it('deactivates expired blocks', function (): void {
        $block = ActivityPubBlockedInstance::create([
            'domain' => 'will-expire.example.com',
            'block_type' => 'full',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $count = ActivityPubBlockedInstance::deactivateExpired();

        expect($count)->toBe(1);
        expect($block->fresh()->is_active)->toBeFalse();
    });
});
