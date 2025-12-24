<?php

declare(strict_types=1);

namespace Tests\Unit;

use const FILTER_VALIDATE_URL;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_has_correct_cors_configuration(): void
    {
        // Get CORS configuration
        $corsConfig = config('cors');

        // Assert basic CORS structure
        $this->assertIsArray($corsConfig);
        $this->assertArrayHasKey('allowed_origins', $corsConfig);
        $this->assertArrayHasKey('allowed_methods', $corsConfig);
        $this->assertArrayHasKey('allowed_headers', $corsConfig);
        $this->assertArrayHasKey('supports_credentials', $corsConfig);

        // Assert that CLIENT_URL is used (not FRONTEND_URL)
        $allowedOrigins = $corsConfig['allowed_origins'];
        $this->assertIsArray($allowedOrigins);

        // In test environment, CLIENT_URL should be set or default correctly
        $expectedClientUrl = env('CLIENT_URL', 'http://localhost:3000');
        $this->assertContains($expectedClientUrl, $allowedOrigins);

        // Ensure credentials are supported
        $this->assertTrue($corsConfig['supports_credentials']);

        // Ensure all methods are allowed
        $this->assertEquals(['*'], $corsConfig['allowed_methods']);

        // Ensure all headers are allowed
        $this->assertEquals(['*'], $corsConfig['allowed_headers']);
    }

    #[Test]
    public function it_uses_client_url_environment_variable(): void
    {
        // Test that the configuration correctly reads CLIENT_URL
        $clientUrl = env('CLIENT_URL');

        // CLIENT_URL may not be set in test environment, which is acceptable
        // Just verify that if it IS set, it's valid
        if (! $clientUrl) {
            $this->markTestSkipped('CLIENT_URL not set in test environment');

            return;
        }

        $this->assertStringStartsWith('http', $clientUrl);
        $this->assertStringNotContainsString('FRONTEND_URL', $clientUrl);

        // Verify it's in CORS config
        $corsConfig = config('cors.allowed_origins');
        $this->assertContains($clientUrl, $corsConfig);
    }

    #[Test]
    public function it_has_required_cors_paths(): void
    {
        $corsConfig = config('cors');

        $this->assertArrayHasKey('paths', $corsConfig);
        $paths = $corsConfig['paths'];

        // Should include API routes
        $this->assertContains('api/*', $paths);

        // Should include Sanctum CSRF cookie route
        $this->assertContains('sanctum/csrf-cookie', $paths);
    }

    #[Test]
    public function it_prevents_inconsistent_url_naming(): void
    {
        // This test ensures we don't accidentally mix CLIENT_URL and FRONTEND_URL

        $corsConfig = config('cors.allowed_origins');

        // Check that none of the origins contain placeholder text
        foreach ($corsConfig as $origin) {
            $this->assertStringNotContainsString('FRONTEND_URL', $origin);
            $this->assertStringNotContainsString('BACKEND_URL', $origin);
            $this->assertStringNotContainsString('${', $origin); // No unresolved placeholders
        }
    }

    #[Test]
    public function it_has_valid_url_formats_in_cors_origins(): void
    {
        $corsConfig = config('cors.allowed_origins');

        foreach ($corsConfig as $origin) {
            // Each origin should be a valid URL or wildcard
            if ($origin !== '*') {
                $this->assertTrue(
                    filter_var($origin, FILTER_VALIDATE_URL) !== false,
                    "Invalid URL format in CORS origins: {$origin}",
                );
            }
        }
    }
}
