<?php

namespace Tests\Unit\Support;

use App\Support\V2ListingEventFilters;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V2ListingEventFiltersTest extends TestCase
{
    #[Test]
    public function it_resolves_date_aliases(): void
    {
        $request = Request::create('/api/v2/events', 'GET', [
            'from_date' => '2026-05-22',
            'to_date' => '2026-05-22',
        ]);

        $this->assertSame('2026-05-22', V2ListingEventFilters::dateFrom($request));
        $this->assertSame('2026-05-22', V2ListingEventFilters::dateTo($request));
        $this->assertTrue(V2ListingEventFilters::hasDateFilter($request));
    }

    #[Test]
    public function it_collects_selected_sessions_with_or_semantics_inputs(): void
    {
        $request = Request::create('/api/v2/events', 'GET', [
            'morning' => 'true',
            'afternoon' => 'true',
            'evening' => 'true',
        ]);

        $this->assertSame(
            ['morning', 'afternoon', 'evening'],
            V2ListingEventFilters::selectedSessions($request),
        );
        $this->assertTrue(V2ListingEventFilters::hasSessionFilter($request));
    }

    #[Test]
    public function it_parses_comma_separated_sessions_param(): void
    {
        $request = Request::create('/api/v2/events', 'GET', [
            'sessions' => 'morning,night',
        ]);

        $this->assertSame(['morning', 'night'], V2ListingEventFilters::selectedSessions($request));
    }
}
