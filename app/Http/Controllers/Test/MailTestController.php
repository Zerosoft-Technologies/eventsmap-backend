<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailTestController extends Controller
{
    /**
     * Test email configuration
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email from Events Map backend.', function ($message) use ($request) {
                $message->to($request->to)
                    ->subject('Test Email - The Events Map');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to send email: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }
}
