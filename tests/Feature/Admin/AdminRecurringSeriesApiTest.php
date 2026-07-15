<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRecurringSeriesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_list_recurring_series_with_metadata(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        [$admin, $series] = $this->seedSeriesWithInstances();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/recurring-series')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.series.0.name', 'Weekly Jam')
            ->assertJsonPath('data.series.0.recurrence_pattern', 'Weekly · Wed')
            ->assertJsonPath('data.series.0.next_occurrence.event_date', '2026-06-10');
    }

    public function test_admin_approve_series_cascades_to_instances(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        [$admin, $series, $event] = $this->seedSeriesWithInstances(false);

        $event->update(['publish_status' => 'draft']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/recurring-series/{$series->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('instances_approved', 1)
            ->assertJsonPath('instances_published', 1);

        $this->assertTrue($series->fresh()->is_approved);
        $this->assertTrue($event->fresh()->is_approved);
        $this->assertSame('published', $event->fresh()->publish_status);
    }

    public function test_admin_reject_series_unapproves_instances(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        [$admin, $series, $event] = $this->seedSeriesWithInstances(true);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/recurring-series/{$series->id}/reject")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('instances_unapproved', 1);

        $this->assertFalse($series->fresh()->is_approved);
        $this->assertFalse($event->fresh()->is_approved);
    }

    public function test_admin_suspend_series_suspends_future_occurrences(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        [$admin, $series, $event] = $this->seedSeriesWithInstances(true);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/recurring-series/{$series->id}/suspend", ['reason' => 'Policy review'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('suspended', 1);

        $this->assertSame(EventV2::STATUS_SUSPENDED, $event->fresh()->status);
    }

    public function test_admin_show_includes_occurrences_and_invitation_summary(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        [$admin, $series] = $this->seedSeriesWithInstances();

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/recurring-series/{$series->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Weekly Jam')
            ->assertJsonCount(1, 'data.occurrences')
            ->assertJsonPath('data.invitation_summary.total', 0);
    }

    /**
     * @return array{0: User, 1: RecurringSeries, 2?: EventV2}
     */
    private function seedSeriesWithInstances(bool $approved = true): array
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $owner = User::factory()->create();
        $category = Category::create(['name' => 'Music', 'slug' => 'music-'.uniqid()]);

        $series = RecurringSeries::create([
            'organizer_id' => $owner->id,
            'recurrence_type' => 'weekly',
            'recurrence_rules' => [
                'weekdays' => [3],
                'event_template' => [
                    'title' => 'Weekly Jam',
                    'category_id' => $category->id,
                    'start_time' => '18:00:00',
                    'end_time' => '21:00:00',
                ],
            ],
            'timezone' => 'UTC',
            'start_date' => '2026-06-01',
            'end_date' => null,
            'created_by' => $owner->id,
            'is_approved' => $approved,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'series_id' => $series->id,
            'is_approved' => $approved,
            'event_date' => '2026-06-10',
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'start_datetime' => '2026-06-10 18:00:00',
            'end_datetime' => '2026-06-10 21:00:00',
        ]);

        return [$admin, $series, $event];
    }
}
