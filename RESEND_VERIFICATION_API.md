# Resend Email Verification API

The resend verification endpoint now works for both authenticated and non-authenticated users.

## Endpoint

```
POST /api/auth/email/resend
```

## Request Body

```json
{
    "email": "user@example.com"
}
```

- **email** (required): The email address to resend verification to
- For authenticated users, the email parameter is optional (will use their registered email)

## Response Examples

### 1. Email Sent Successfully

```json
{
    "success": true,
    "message": "If an account with this email exists, a verification link has been sent."
}
```

### 2. Email Already Verified

```json
{
    "success": true,
    "message": "This email is already verified. You can log in."
}
```

### 3. Validation Error (400)

```json
{
    "message": "The email field is required.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

## Use Cases

### 1. Non-Authenticated User
User didn't receive verification email during registration:

```bash
curl -X POST http://localhost:8000/api/auth/email/resend \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

### 2. Authenticated User
Logged-in user wants to resend verification:

```bash
curl -X POST http://localhost:8000/api/auth/email/resend \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc123..." \
  -d '{"email": "user@example.com"}'
```

Or without email (uses authenticated user's email):

```bash
curl -X POST http://localhost:8000/api/auth/email/resend \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc123..." \
  -d '{}'
```

## Security Features

1. **Email Enumeration Protection**: Always returns success message whether email exists or not
2. **Rate Limiting**: Consider implementing rate limiting in production
3. **Job Queue**: Uses queue job to prevent API timeout

## Implementation Details

The controller handles both scenarios:
- If user is authenticated → uses their email
- If not authenticated → finds user by provided email
- Always returns generic success message
- Uses `SendVerificationEmail` job for async delivery

## Rate Limiting (Optional)

To add rate limiting, update the route:

```php
// In routes/auth.php
Route::post('/email/resend', [AuthController::class, 'resendVerification'])
    ->middleware('throttle:3,1'); // 3 requests per minute
```

## Testing

1. Register a user but don't verify email
2. Call resend endpoint without authentication
3. Check email for new verification link
4. Try with already verified email
5. Try with non-existent email
