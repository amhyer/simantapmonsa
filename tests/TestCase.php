<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Suite tidak boleh bergantung pada hasil `npm run build`.
        // Tanpa ini, clone segar gagal karena public/build/manifest.json tidak ada.
        $this->withoutVite();
    }
}
