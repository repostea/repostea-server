<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Tests\TestCase;

final class StringHelperTest extends TestCase
{
    public function test_it_masks_email_in_default_mode(): void
    {
        $this->assertEquals('j***@e***.com', mask_email('john@example.com'));
        $this->assertEquals('a***@t***.co', mask_email('ab@test.co'));
        $this->assertEquals('t***@e***.co.uk', mask_email('test@example.co.uk'));
    }

    public function test_it_masks_email_in_admin_mode(): void
    {
        $this->assertEquals('****@example.com', mask_email('john@example.com', 'admin'));
        $this->assertEquals('****@test.co', mask_email('ab@test.co', 'admin'));
        $this->assertEquals('****@example.co.uk', mask_email('test@example.co.uk', 'admin'));
    }

    public function test_it_handles_invalid_emails(): void
    {
        $this->assertEquals('', mask_email(''));
        $this->assertEquals('notanemail', mask_email('notanemail'));
        $this->assertEquals('invalid', mask_email('invalid'));
    }

    public function test_it_handles_single_char_local_part(): void
    {
        $this->assertEquals('a***@e***.com', mask_email('a@example.com'));
    }

    public function test_it_handles_single_char_domain(): void
    {
        $this->assertEquals('j***@e***.c', mask_email('john@e.c'));
    }

    public function test_it_preserves_multiple_tlds(): void
    {
        $this->assertEquals('u***@e***.ac.uk', mask_email('user@example.ac.uk'));
        $this->assertEquals('t***@d***.gov.es', mask_email('test@domain.gov.es'));
    }

    public function test_it_handles_long_emails(): void
    {
        $this->assertEquals('v***@l***.example.com', mask_email('verylongemail@longdomain.example.com'));
    }
}
