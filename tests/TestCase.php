<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The database connections that should have transactions.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [null, 'media'];
}
