# Email Verification Required for Login

This document outlines the implementation of mandatory email verification before login in the Events Map API.

## Overview

- Users must verify their email address before they can log in
- Registration sends a verification email but does NOT return an auth token
- Login attempts with unverified emails are rejected with a clear error message
- Uses Laravel's built-in `MustVerifyEmail` contract

## Implementation Details

### 1. User Model Configuration

The `User` model already implements `MustVerifyEmail`:

```php
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    // ...
}
```

### 2. Login Controller Logic

In `app/Http/Controllers/Auth/AuthController.php`, the login method now checks email verification:

```php
public function login(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    // Check email verification BEFORE any other checks
    if (!$user->hasVerifiedEmail()) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Your email is not verified. Please check your registered email and verify your account.',
            ],
        ], 403);
    }

    // ... other checks for suspended/disabled accounts

    // Create token only for verified users
    $user->tokens()->delete();
    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
        'success' => true,
        'data' => [
            'user' => $this->formatUser($user),
            'token' => $token,
        ],
    ]);
}
```

### 3. Registration Response

Registration no longer returns an immediate token:

```php
public function register(Request $request): JsonResponse
{
    // ... validation and user creation ...

    // Fire registered event (triggers email verification notification)
    event(new Registered($user));

    return response()->json([
        'success' => true,
        'message' => 'Registration successful. Please check your email to verify your account before logging in.',
        'data' => [
            'user' => $this->formatUser($user),
        ],
    ], 201);
}
```

### 4. Middleware Configuration

The `EnsureEmailIsVerified` middleware is registered in `bootstrap/app.php`:

```php
$middleware->alias([
    'email.verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
    // ... other middleware
]);
```

## API Response Examples

### Successful Registration

```http
POST /api/auth/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "profile_type": "event"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Registration successful. Please check your email to verify your account before logging in.",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": null,
            "profile_type": "event",
            "role": "user",
            "account_type": "free",
            "status": "active",
            "is_active": true,
            "created_at": "2025-02-28T10:30:00.000000Z",
            "updated_at": "2025-02-28T10:30:00.000000Z"
        }
    }
}
```

### Login Attempt with Unverified Email

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (403):**
```json
{
    "success": false,
    "error": {
        "code": "EMAIL_NOT_VERIFIED",
        "message": "Your email is not verified. Please check your registered email and verify your account."
    }
}
```

### Successful Login with Verified Email

After clicking the verification link in the email:

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": "2025-02-28T10:35:00.000000Z",
            "profile_type": "event",
            "role": "user",
            "account_type": "free",
            "status": "active",
            "is_active": true,
            "created_at": "2025-02-28T10:30:00.000000Z",
            "updated_at": "2025-02-28T10:35:00.000000Z"
        },
        "token": "1|abc123def456..."
    }
}
```

## Email Verification Flow

1. **Registration**: User registers → Verification email sent → No token returned
2. **Email Verification**: User clicks link → `email_verified_at` is set
3. **Login**: Verified user can log in → Token returned

## Protecting Additional Routes

To protect specific routes that require email verification, use the `email.verified` middleware:

```php
// In routes file
Route::middleware(['auth:sanctum', 'email.verified'])
    ->group(function () {
        Route::get('/user/profile', [ProfileController::class, 'show']);
        Route::post('/user/update', [ProfileController::class, 'update']);
    });
```

## Testing Email Verification

### Using Mailtrap (Development)

Update your `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@eventsmap.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Using Gmail SMTP (Production)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Important Notes

1. **Queue Configuration**: Ensure `QUEUE_CONNECTION=sync` in your `.env` or run `php artisan queue:work` if using database queues
2. **Email Configuration**: Verify your mail settings in `.env` are correct
3. **Frontend Integration**: The frontend should check for the `EMAIL_NOT_VERIFIED` error and display appropriate messaging
4. **Resend Verification**: Implement a resend verification endpoint if needed

## Security Considerations

- Email verification check happens BEFORE password validation to prevent enumeration attacks
- Unverified users cannot obtain any authentication tokens
- All sensitive routes should use the `email.verified` middleware for additional protection
