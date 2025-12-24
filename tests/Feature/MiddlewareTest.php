<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function setlocale_middleware_changes_app_locale(): void
    {
        $this->app->setLocale('en');
        $this->assertEquals('en', $this->app->getLocale());

        $response = $this->get('/es/about');

        $response->assertStatus(200);
        $this->assertEquals('es', $this->app->getLocale());
    }

    #[Test]
    public function setlocale_middleware_fallbacks_to_default_locale_when_invalid(): void
    {
        $this->app->setLocale('en');
        config(['app.fallback_locale' => 'en']);

        $response = $this->get('/xx/about');

        $response->assertStatus(302);
        $this->assertEquals('en', $this->app->getLocale());
    }

    #[Test]
    public function root_route_redirects_to_locale_prefixed_route(): void
    {
        $this->app->setLocale('es');

        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/es');
    }
}
