<?php

use Illuminate\Support\Facades\Route;
use App\Models\EventOrganizer;
use App\Http\Controllers\Test\MailTestController;

/*
|--------------------------------------------------------------------------
| Test Routes
|--------------------------------------------------------------------------
*/

Route::get('/test-model', function () {
    $model = new EventOrganizer();
    
    // Test if we can create a simple query
    $count = EventOrganizer::count();
    
    return response()->json([
        'table_name' => $model->getTable(),
        'connection' => $model->getConnectionName(),
        'count' => $count,
        'first_record' => EventOrganizer::first(),
    ]);
});

Route::get('/test-simple-query', function () {
    $events = EventOrganizer::query()
        ->select('id', 'title', 'category', 'city', 'created_at')
        ->limit(3)
        ->get();
    
    return response()->json([
        'success' => true,
        'count' => $events->count(),
        'data' => $events,
    ]);
});

Route::post('/test-email', [MailTestController::class, 'testEmail']);
