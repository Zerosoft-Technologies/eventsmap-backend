<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    public function test_countries_endpoint_returns_iso3166_list(): void
    {
        $response = $this->getJson('/api/v1/countries');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $nl = collect($data)->firstWhere('code', 'NL');
        $this->assertNotNull($nl);
        $this->assertSame('Netherlands', $nl['name']);
    }

    public function test_talent_languages_endpoint_returns_pick_list(): void
    {
        $response = $this->getJson('/api/v1/talent-languages');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertContains('English', $data);
        $this->assertContains('Dutch', $data);
    }
}
