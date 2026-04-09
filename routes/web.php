<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Test mail route for diagnosing mail configuration.
 * Remove this route in production or protect it with authentication.
 *
 * Usage: GET /test-mail?to=your@email.com
 */
Route::get('/test-mail', function () {
    $to = request('to', config('mail.from.address'));

    try {
        Mail::raw('This is a test email from Events Map to verify mail configuration.', function ($message) use ($to) {
            $message->to($to)
                ->subject('The Events Map - Test Email');
        });

        return response()->json([
            'success' => true,
            'message' => "Test email sent to {$to}. Check your inbox (and spam folder).",
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
        ], 500);
    }
});

// Serve images from storage
Route::get('/storage/{path}', function ($path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    
    $file = Storage::disk('public')->get($path);
    $mimeType = Storage::disk('public')->mimeType($path);
    
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Cache-Control', 'public, max-age=31536000');
})->where('path', '.*');
