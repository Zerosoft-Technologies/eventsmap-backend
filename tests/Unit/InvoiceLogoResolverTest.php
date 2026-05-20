<?php

namespace Tests\Unit;

use App\Support\InvoiceLogoResolver;
use Tests\TestCase;

class InvoiceLogoResolverTest extends TestCase
{
    public function test_resolves_local_public_logo(): void
    {
        $uri = InvoiceLogoResolver::dataUri('images/marker.png');

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/', $uri);
        $this->assertStringContainsString('base64,', $uri);
    }
}
