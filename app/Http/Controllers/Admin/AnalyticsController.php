<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/admin/analytics/dashboard
     *
     * Get dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        // Event statistics
        $totalEvents = Event::count();
        $draftEvents = Event::where('is_published', false)
            ->where('is_cancelled', false)
            ->where('is_archived', false)
            ->count();
        $publishedEvents = Event::where('is_published', true)
            ->where('is_featured', false)
            ->where('is_cancelled', false)
            ->where('is_archived', false)
            ->count();
        $featuredEvents = Event::where('is_featured', true)
            ->where('is_cancelled', false)
            ->where('is_archived', false)
            ->count();
        $cancelledEvents = Event::where('is_cancelled', true)
            ->where('is_archived', false)
            ->count();
        $archivedEvents = Event::where('is_archived', true)->count();

        // Upcoming events (published, not cancelled, start_datetime in future)
        $upcomingEvents = Event::where('is_published', true)
            ->where('is_cancelled', false)
            ->where('start_datetime', '>', now())
            ->count();

        // Live now events
        $liveNowEvents = Event::where('is_published', true)
            ->where('is_cancelled', false)
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now())
            ->count();

        // Talent statistics
        $activeTalents = Talent::where('is_active', true)->count();
        $totalTalents = Talent::count();

        // Category statistics
        $activeCategories = Category::where('is_active', true)->count();
        $totalCategories = Category::count();

        // Admin user count
        $adminUsers = User::admins()->count();

        // Total views
        $totalViews = Event::sum('view_count');

        // Events created this month
        $eventsThisMonth = Event::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Events created last month
        $eventsLastMonth = Event::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'events' => [
                    'total' => $totalEvents,
                    'by_status' => [
                        'draft' => $draftEvents,
                        'published' => $publishedEvents,
                        'featured' => $featuredEvents,
                        'cancelled' => $cancelledEvents,
                        'archived' => $archivedEvents,
                    ],
                ],
                'upcoming_events' => $upcomingEvents,
                'live_now_events' => $liveNowEvents,
                'talents_active' => $activeTalents,
                'talents_total' => $totalTalents,
                'categories_active' => $activeCategories,
                'categories_total' => $totalCategories,
                'admin_users' => $adminUsers,
                'total_views' => $totalViews,
                'events_this_month' => $eventsThisMonth,
                'events_last_month' => $eventsLastMonth,
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/events
     *
     * Get event analytics.
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
        ]);

        $period = $request->input('period', '30d');
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };

        // Events created over time
        $eventsCreated = Event::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top viewed events
        $topViewed = Event::where('is_published', true)
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'slug', 'view_count']);

        // Events by category
        $eventsByCategory = Event::join('categories', 'events.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, COUNT(events.id) as count')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('count', 'desc')
            ->get();

        // Events by city
        $eventsByCity = Event::selectRaw('city, COUNT(*) as count')
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'events_created' => $eventsCreated,
                'top_viewed' => $topViewed,
                'by_category' => $eventsByCategory,
                'by_city' => $eventsByCity,
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/categories
     *
     * Get category statistics.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::withCount(['subcategories'])
            ->get()
            ->map(function ($category) {
                $eventsCount = Event::where('category_id', $category->id)->count();
                $publishedEventsCount = Event::where('category_id', $category->id)
                    ->where('is_published', true)
                    ->count();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'is_active' => $category->is_active,
                    'subcategories_count' => $category->subcategories_count,
                    'events_count' => $eventsCount,
                    'published_events_count' => $publishedEventsCount,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
