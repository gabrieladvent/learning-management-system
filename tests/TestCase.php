<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI tidak menjalankan `npm run build`, jadi public/build/manifest.json
        // tidak ada dan @vite akan file_get_contents() ke path yang tidak ada
        // (HTTP 500 di semua view). withoutVite() membuat directive @vite jadi
        // no-op sehingga view tetap ter-render tanpa butuh manifest.
        $this->withoutVite();
    }
}
