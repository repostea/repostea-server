<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HomeRedirectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_from_root_to_localized_home_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/' . app()->getLocale());
    }

    #[Test]
    public function it_returns_successful_response_on_localized_home_page(): void
    {
        $locale = app()->getLocale();
        $response = $this->get("/{$locale}");

        $response->assertStatus(200);
    }
}
