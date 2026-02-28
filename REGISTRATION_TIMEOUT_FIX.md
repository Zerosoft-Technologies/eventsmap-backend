# Registration API Timeout Fix

## Problem
The registration API was timing out but users were being created successfully. This was caused by synchronous email sending taking too long.

## Solution Implemented

### 1. Created Email Queue Job
Created `app/Jobs/SendVerificationEmail.php` with:
- 3 retry attempts with exponential backoff (5, 10, 20 seconds)
- 30-second timeout per attempt
- Proper error logging

### 2. Updated Registration Controller
Modified `app/Http/Controllers/Auth/AuthController.php`:
- Removed direct `sendEmailVerificationNotification()` call
- Added `SendVerificationEmail::dispatch($user)` instead
- Email now sends asynchronously without blocking API response

### 3. Configuration
Your `.env` should have:
```env
QUEUE_CONNECTION=sync
```

This ensures the job runs immediately after the response is sent, preventing timeout.

## Benefits
- ✅ Registration API responds instantly
- ✅ User creation is not affected by email issues
- ✅ Email retries automatically if it fails
- ✅ Better error handling and logging

## Testing
1. Register a new user - should get instant response
2. Check email for verification link
3. If email doesn't arrive, check logs: `storage/logs/laravel.log`

## Alternative Solutions

If you still experience timeouts, you can:

### Option 1: Increase PHP Timeout
Add to your `.env`:
```env
# Increase execution time to 60 seconds
PHP_EXECUTION_TIMEOUT=60
```

### Option 2: Use Database Queue (Production)
1. Update `.env`:
   ```env
   QUEUE_CONNECTION=database
   ```
2. Run queue worker:
   ```bash
   php artisan queue:work --daemon
   ```

### Option 3: Disable Email Verification (Temporary)
For testing only, comment out the job dispatch:
```php
// SendVerificationEmail::dispatch($user);
```

## Monitoring Failed Jobs
Check failed jobs with:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan queue:retry all
```
