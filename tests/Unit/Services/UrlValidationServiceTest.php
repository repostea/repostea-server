<?php

declare(strict_types=1);

use App\Services\UrlValidationService;

beforeEach(function (): void {
    $this->validator = new UrlValidationService();
});

describe('UrlValidationService', function (): void {
    it('allows valid HTTPS URLs', function (): void {
        // google.com is guaranteed to resolve to a public IP
        expect(fn () => $this->validator->validate('https://google.com'))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('blocks HTTP URLs', function (): void {
        expect(fn () => $this->validator->validate('http://example.com'))
            ->toThrow(InvalidArgumentException::class, 'Only HTTPS URLs allowed');
    });

    it('blocks direct IP addresses', function (): void {
        expect(fn () => $this->validator->validate('https://192.168.1.1'))
            ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');

        expect(fn () => $this->validator->validate('https://10.0.0.1'))
            ->toThrow(InvalidArgumentException::class, 'Direct IP addresses not allowed');
    });

    it('blocks non-443 ports', function (): void {
        expect(fn () => $this->validator->validate('https://example.com:8080'))
            ->toThrow(InvalidArgumentException::class, 'Only port 443 allowed');

        expect(fn () => $this->validator->validate('https://example.com:80'))
            ->toThrow(InvalidArgumentException::class, 'Only port 443 allowed');
    });

    it('blocks URLs without dots', function (): void {
        expect(fn () => $this->validator->validate('https://localhost'))
            ->toThrow(InvalidArgumentException::class, 'Invalid domain');
    });

    it('blocks credentials in URL', function (): void {
        expect(fn () => $this->validator->validate('https://user:pass@example.com'))
            ->toThrow(InvalidArgumentException::class, 'Credentials in URL not allowed');
    });

    it('blocks empty URLs', function (): void {
        expect(fn () => $this->validator->validate(''))
            ->toThrow(InvalidArgumentException::class, 'URL cannot be empty');
    });

    it('blocks cloud metadata hosts', function (): void {
        expect(fn () => $this->validator->validate('https://metadata.google.internal'))
            ->toThrow(InvalidArgumentException::class, 'Host not allowed');
    });
});
