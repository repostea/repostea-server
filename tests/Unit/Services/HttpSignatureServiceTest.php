<?php

declare(strict_types=1);

use App\Services\HttpSignatureService;
use App\Services\UrlValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['activitypub.signatures.require' => true]);
    config(['activitypub.signatures.log_failures' => true]);
});

describe('HttpSignatureService', function (): void {
    it('returns invalid when Signature header is missing', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [], '{"type": "Like"}');

        $result = $service->verifyRequest($request);

        expect($result['valid'])->toBeFalse();
        expect($result['error'])->toBe('Missing Signature header');
    });

    it('returns invalid when Signature header is malformed', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'invalid-signature-format',
        ], '{"type": "Like"}');

        $result = $service->verifyRequest($request);

        expect($result['valid'])->toBeFalse();
        expect($result['error'])->toBe('Invalid Signature header format');
    });

    it('parses Signature header correctly', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        // Mock the remote actor fetch to fail (we just want to test parsing)
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $signatureHeader = 'keyId="https://example.com/users/alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date",signature="abc123"';

        $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => $signatureHeader,
            'HTTP_HOST' => 'test.example.com',
            'HTTP_DATE' => gmdate('D, d M Y H:i:s') . ' GMT',
        ], '{"type": "Like"}');

        $result = $service->verifyRequest($request);

        // Will fail because we can't fetch the key, but should have parsed keyId
        expect($result['valid'])->toBeFalse();
        expect($result['keyId'])->toBe('https://example.com/users/alice#main-key');
    });

    it('verifies digest header when present', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $body = '{"type": "Like"}';
        $correctDigest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));

        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        // With correct digest
        $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'keyId="https://example.com/users/alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="abc123"',
            'HTTP_HOST' => 'test.example.com',
            'HTTP_DATE' => gmdate('D, d M Y H:i:s') . ' GMT',
            'HTTP_DIGEST' => $correctDigest,
        ], $body);

        $result = $service->verifyRequest($request);

        // Should fail on key fetch, not digest
        expect($result['error'])->not->toBe('Digest mismatch');
    });

    it('rejects mismatched digest', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $body = '{"type": "Like"}';
        $wrongDigest = 'SHA-256=' . base64_encode(hash('sha256', 'different body', true));

        $request = Request::create('/activitypub/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'keyId="https://example.com/users/alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="abc123"',
            'HTTP_HOST' => 'test.example.com',
            'HTTP_DATE' => gmdate('D, d M Y H:i:s') . ' GMT',
            'HTTP_DIGEST' => $wrongDigest,
        ], $body);

        $result = $service->verifyRequest($request);

        expect($result['valid'])->toBeFalse();
        expect($result['error'])->toBe('Digest mismatch');
    });

    it('should not enforce in testing environment', function (): void {
        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        expect($service->shouldEnforce())->toBeFalse();
    });

    it('respects require config setting', function (): void {
        config(['activitypub.signatures.require' => false]);

        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        // Even outside testing, should respect config
        expect($service->shouldEnforce())->toBeFalse();
    });

    it('verifies signature with valid public key', function (): void {
        // Generate a test key pair
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        $publicKey = $publicKeyDetails['key'];

        // Create the signature
        $method = 'post';
        $path = '/activitypub/inbox';
        $host = 'test.example.com';
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $body = '{"type": "Like", "actor": "https://example.com/users/alice"}';
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));

        $signatureString = "(request-target): {$method} {$path}\nhost: {$host}\ndate: {$date}\ndigest: {$digest}";

        openssl_sign($signatureString, $signature, $privateKey, \OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signature);

        // Mock the actor response with public key
        Http::fake([
            'https://example.com/users/alice' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'publicKey' => [
                    'id' => 'https://example.com/users/alice#main-key',
                    'owner' => 'https://example.com/users/alice',
                    'publicKeyPem' => $publicKey,
                ],
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $signatureHeader = sprintf(
            'keyId="https://example.com/users/alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"',
            $signatureBase64,
        );

        $request = Request::create($path, 'POST', [], [], [], [
            'HTTP_SIGNATURE' => $signatureHeader,
            'HTTP_HOST' => $host,
            'HTTP_DATE' => $date,
            'HTTP_DIGEST' => $digest,
        ], $body);

        $result = $service->verifyRequest($request);

        expect($result['valid'])->toBeTrue();
        expect($result['keyId'])->toBe('https://example.com/users/alice#main-key');
    });

    it('rejects signature with wrong key', function (): void {
        // Generate two different key pairs
        $keyPair1 = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        $keyPair2 = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair1, $privateKey1);
        $publicKeyDetails2 = openssl_pkey_get_details($keyPair2);
        $publicKey2 = $publicKeyDetails2['key'];

        // Sign with key 1
        $method = 'post';
        $path = '/activitypub/inbox';
        $host = 'test.example.com';
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $body = '{"type": "Like"}';
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));

        $signatureString = "(request-target): {$method} {$path}\nhost: {$host}\ndate: {$date}\ndigest: {$digest}";

        openssl_sign($signatureString, $signature, $privateKey1, \OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signature);

        // But serve public key 2
        Http::fake([
            'https://example.com/users/alice' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://example.com/users/alice',
                'type' => 'Person',
                'publicKey' => [
                    'id' => 'https://example.com/users/alice#main-key',
                    'publicKeyPem' => $publicKey2,
                ],
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $urlValidator = app(UrlValidationService::class);
        $service = new HttpSignatureService($urlValidator);

        $signatureHeader = sprintf(
            'keyId="https://example.com/users/alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"',
            $signatureBase64,
        );

        $request = Request::create($path, 'POST', [], [], [], [
            'HTTP_SIGNATURE' => $signatureHeader,
            'HTTP_HOST' => $host,
            'HTTP_DATE' => $date,
            'HTTP_DIGEST' => $digest,
        ], $body);

        $result = $service->verifyRequest($request);

        expect($result['valid'])->toBeFalse();
        expect($result['error'])->toBe('Signature verification failed');
    });
});
