<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 20]; // Retry after 5, 10, then 20 seconds
    public $timeout = 30; // Maximum 30 seconds per attempt

    /**
     * Create a new job instance.
     */
    public function __construct(
        private $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->user->sendEmailVerificationNotification();
            Log::info('Verification email sent successfully to: ' . $this->user->email);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email to ' . $this->user->email . ': ' . $e->getMessage());
            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
