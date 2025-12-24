<?php

declare(strict_types=1);

use App\Http\Middleware\ActivityPubRateLimiter;
use App\Models\ActivityPubBlockedInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

afterEach(function (): void {
    ActivityPubBlockedInstance::query()->delete();
    Cache::flush();
});

describe('ActivityPubRateLimiter', function (): void {
    describe('blocked instances', function (): void {
        it('rejects requests from blocked instances', function (): void {
            ActivityPubBlockedInstance::blockDomain('blocked.example.com');

            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://blocked.example.com/users/spammer',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));

            expect($response->getStatusCode())->toBe(403);
            expect($response->getData(true)['error'])->toBe('Instance is blocked.');
        });

        it('allows requests from non-blocked instances', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://friendly.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));

            expect($response->getStatusCode())->toBe(200);
        });
    });

    describe('rate limiting', function (): void {
        it('enforces rate limit after max attempts', function (): void {
            $middleware = new ActivityPubRateLimiter();
            $maxAttempts = 5;

            // Simulate max attempts
            Cache::put('activitypub:rate_limit:test.example.com', $maxAttempts, now()->addMinutes(1));

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://test.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]), $maxAttempts, 1);

            expect($response->getStatusCode())->toBe(429);
            expect($response->getData(true)['error'])->toBe('Rate limit exceeded. Please slow down.');
            expect($response->headers->has('Retry-After'))->toBeTrue();
        });

        it('allows requests below rate limit', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://test.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]), 100, 1);

            expect($response->getStatusCode())->toBe(200);
        });

        it('increments rate limit counter', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://counter.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            // Make 3 requests
            for ($i = 0; $i < 3; $i++) {
                $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));
            }

            $count = Cache::get('activitypub:rate_limit:counter.example.com');
            expect($count)->toBe(3);
        });
    });

    describe('rate limit headers', function (): void {
        it('adds rate limit headers to response', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://headers.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]), 300, 1);

            expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
            expect($response->headers->get('X-RateLimit-Limit'))->toBe('300');
            expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
            expect($response->headers->has('X-RateLimit-Reset'))->toBeTrue();
        });

        it('decreases remaining count with each request', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://remaining.example.com/users/alice',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            // First request
            $response1 = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]), 100, 1);

            // Second request
            $response2 = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]), 100, 1);

            expect((int) $response1->headers->get('X-RateLimit-Remaining'))->toBeGreaterThan(
                (int) $response2->headers->get('X-RateLimit-Remaining'),
            );
        });
    });

    describe('domain extraction', function (): void {
        it('extracts domain from actor URL', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://mastodon.social/users/alice',
                'type' => 'Follow',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            // Block mastodon.social to verify extraction worked
            ActivityPubBlockedInstance::blockDomain('mastodon.social');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));

            expect($response->getStatusCode())->toBe(403);
        });

        it('extracts domain from HTTP Signature keyId', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'type' => 'Follow',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');
            $request->headers->set('Signature', 'keyId="https://lemmy.world/u/testuser#main-key",algorithm="rsa-sha256",headers="..."');

            // Block lemmy.world to verify extraction worked
            ActivityPubBlockedInstance::blockDomain('lemmy.world');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));

            expect($response->getStatusCode())->toBe(403);
        });

        it('falls back to IP-based rate limiting when domain cannot be extracted', function (): void {
            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'type' => 'Follow',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            // Make multiple requests to verify IP-based limiting is used
            for ($i = 0; $i < 3; $i++) {
                $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));
            }

            // Check that IP-based key was used (requests come from 127.0.0.1)
            $count = Cache::get('activitypub:rate_limit:ip:127.0.0.1');
            expect($count)->toBe(3);
        });
    });

    describe('silenced instances', function (): void {
        it('allows requests from silenced instances (only full blocks are enforced)', function (): void {
            // Silenced instances can still send activities, they're just hidden from public feeds
            ActivityPubBlockedInstance::blockDomain('silenced.example.com', null, 'silence');

            $middleware = new ActivityPubRateLimiter();

            $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], json_encode([
                'actor' => 'https://silenced.example.com/users/user',
                'type' => 'Create',
            ]));
            $request->headers->set('Content-Type', 'application/activity+json');

            $response = $middleware->handle($request, fn ($req) => new JsonResponse(['success' => true]));

            expect($response->getStatusCode())->toBe(200);
        });
    });
});
