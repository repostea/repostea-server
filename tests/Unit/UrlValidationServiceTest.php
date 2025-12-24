<?php

declare(strict_types=1);

use App\Services\UrlValidationService;

describe('UrlValidationService', function (): void {
    beforeEach(function (): void {
        $this->service = new UrlValidationService();
    });

    describe('validate URL', function (): void {
        it('accepts valid HTTPS URL', function (): void {
            // This test requires a real domain that resolves to a public IP
            // Using example.com which is reserved for documentation and resolves to 93.184.215.14
            expect(fn () => $this->service->validate('https://example.com/activitypub/actor'))
                ->not->toThrow(InvalidArgumentException::class);
        });

        it('rejects empty URL', function (): void {
            expect(fn () => $this->service->validate(''))
                ->toThrow(InvalidArgumentException::class, 'URL cannot be empty');

            expect(fn () => $this->service->validate('   '))
                ->toThrow(InvalidArgumentException::class, 'URL cannot be empty');
        });

        it('rejects invalid URL format', function (): void {
            expect(fn () => $this->service->validate('not-a-url'))
                ->toThrow(InvalidArgumentException::class, 'Invalid URL format');

            expect(fn () => $this->service->validate('://missing-scheme.com'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('rejects HTTP URLs', function (): void {
            expect(fn () => $this->service->validate('http://example.com/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Only HTTPS URLs allowed');
        });

        it('rejects non-443 ports', function (): void {
            expect(fn () => $this->service->validate('https://example.com:8080/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Only port 443 allowed');

            expect(fn () => $this->service->validate('https://example.com:80/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Only port 443 allowed');
        });

        it('rejects URLs with credentials', function (): void {
            expect(fn () => $this->service->validate('https://user:pass@example.com/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Credentials in URL not allowed');

            expect(fn () => $this->service->validate('https://user@example.com/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Credentials in URL not allowed');
        });

        it('rejects direct IPv4 addresses', function (): void {
            expect(fn () => $this->service->validate('https://192.168.1.1/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');

            expect(fn () => $this->service->validate('https://10.0.0.1/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');

            expect(fn () => $this->service->validate('https://127.0.0.1/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');
        });

        it('rejects direct IPv6 addresses', function (): void {
            // IPv6 addresses with brackets are rejected as invalid domain (no dot)
            // This is still secure - they can't be used for SSRF
            expect(fn () => $this->service->validate('https://[::1]/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');

            expect(fn () => $this->service->validate('https://[fe80::1]/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');
        });

        it('rejects localhost', function (): void {
            // localhost has no dot, so it fails the domain validation
            expect(fn () => $this->service->validate('https://localhost/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');
        });

        it('rejects domains without dots', function (): void {
            expect(fn () => $this->service->validate('https://intranet/inbox'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');

            expect(fn () => $this->service->validate('https://internal/api'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');
        });

        it('rejects cloud metadata endpoints', function (): void {
            expect(fn () => $this->service->validate('https://metadata.google.internal/computeMetadata/v1/'))
                ->toThrow(InvalidArgumentException::class, 'Host not allowed');

            expect(fn () => $this->service->validate('https://metadata.goog/computeMetadata/v1/'))
                ->toThrow(InvalidArgumentException::class, 'Host not allowed');
        });
    });

    describe('validate instance', function (): void {
        it('accepts valid domain', function (): void {
            // example.com resolves to a public IP
            expect(fn () => $this->service->validateInstance('example.com'))
                ->not->toThrow(InvalidArgumentException::class);
        });

        it('strips protocol prefix', function (): void {
            expect(fn () => $this->service->validateInstance('https://example.com'))
                ->not->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->service->validateInstance('http://example.com'))
                ->not->toThrow(InvalidArgumentException::class);
        });

        it('strips trailing slash', function (): void {
            expect(fn () => $this->service->validateInstance('example.com/'))
                ->not->toThrow(InvalidArgumentException::class);
        });

        it('rejects empty instance', function (): void {
            expect(fn () => $this->service->validateInstance(''))
                ->toThrow(InvalidArgumentException::class, 'Instance cannot be empty');

            expect(fn () => $this->service->validateInstance('   '))
                ->toThrow(InvalidArgumentException::class, 'Instance cannot be empty');
        });

        it('rejects direct IP addresses', function (): void {
            expect(fn () => $this->service->validateInstance('192.168.1.1'))
                ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');
        });

        it('rejects localhost', function (): void {
            expect(fn () => $this->service->validateInstance('localhost'))
                ->toThrow(InvalidArgumentException::class, 'Invalid domain name');
        });

        it('rejects cloud metadata endpoints', function (): void {
            expect(fn () => $this->service->validateInstance('metadata.google.internal'))
                ->toThrow(InvalidArgumentException::class, 'Host not allowed');
        });
    });

    describe('SSRF protection - blocked IP ranges', function (): void {
        // These tests verify domains that resolve to private IPs are blocked
        // Note: These tests depend on DNS resolution, so we test the IP matching logic directly

        it('blocks loopback range (127.0.0.0/8)', function (): void {
            // localhost.localdomain usually resolves to 127.0.0.1
            // but we already test localhost rejection by "no dot" rule
            // This documents the expected behavior
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '127.0.0.1'))->toBeTrue();
            expect($method->invoke($service, '127.0.0.254'))->toBeTrue();
            expect($method->invoke($service, '127.255.255.255'))->toBeTrue();
        });

        it('blocks private range 10.0.0.0/8', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '10.0.0.1'))->toBeTrue();
            expect($method->invoke($service, '10.255.255.255'))->toBeTrue();
        });

        it('blocks private range 172.16.0.0/12', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '172.16.0.1'))->toBeTrue();
            expect($method->invoke($service, '172.31.255.255'))->toBeTrue();
            // 172.32.x.x is NOT in range
            expect($method->invoke($service, '172.32.0.1'))->toBeFalse();
        });

        it('blocks private range 192.168.0.0/16', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '192.168.0.1'))->toBeTrue();
            expect($method->invoke($service, '192.168.255.255'))->toBeTrue();
        });

        it('blocks link-local range 169.254.0.0/16', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '169.254.0.1'))->toBeTrue();
            expect($method->invoke($service, '169.254.169.254'))->toBeTrue(); // AWS metadata
        });

        it('blocks carrier-grade NAT 100.64.0.0/10', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '100.64.0.1'))->toBeTrue();
            expect($method->invoke($service, '100.127.255.255'))->toBeTrue();
        });

        it('blocks multicast range 224.0.0.0/4', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            expect($method->invoke($service, '224.0.0.1'))->toBeTrue();
            expect($method->invoke($service, '239.255.255.255'))->toBeTrue();
        });

        it('allows public IP addresses', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('isBlockedIp');
            $method->setAccessible(true);

            // Google DNS
            expect($method->invoke($service, '8.8.8.8'))->toBeFalse();
            // Cloudflare DNS
            expect($method->invoke($service, '1.1.1.1'))->toBeFalse();
            // example.com IP
            expect($method->invoke($service, '93.184.215.14'))->toBeFalse();
        });
    });

    describe('CIDR matching', function (): void {
        it('correctly matches CIDR ranges', function (): void {
            $service = new UrlValidationService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('ipMatchesCidr');
            $method->setAccessible(true);

            // 192.168.0.0/16
            expect($method->invoke($service, '192.168.1.1', '192.168.0.0/16'))->toBeTrue();
            expect($method->invoke($service, '192.169.1.1', '192.168.0.0/16'))->toBeFalse();

            // 10.0.0.0/8
            expect($method->invoke($service, '10.0.0.1', '10.0.0.0/8'))->toBeTrue();
            expect($method->invoke($service, '10.255.255.255', '10.0.0.0/8'))->toBeTrue();
            expect($method->invoke($service, '11.0.0.1', '10.0.0.0/8'))->toBeFalse();

            // 127.0.0.0/8
            expect($method->invoke($service, '127.0.0.1', '127.0.0.0/8'))->toBeTrue();
            expect($method->invoke($service, '128.0.0.1', '127.0.0.0/8'))->toBeFalse();
        });
    });
});
