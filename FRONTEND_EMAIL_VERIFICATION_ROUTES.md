# Frontend Routes for Email Verification

After implementing email verification redirects, your frontend should handle these routes:

## Base Frontend URL
Configure in your `.env`:
```env
FRONTEND_URL=http://localhost:5173
```

## Required Frontend Routes

### 1. Email Verification Success
**Route**: `/auth/email-verified`
**Query Parameters**: `?email=user@example.com`

**Example URL**: `http://localhost:5173/auth/email-verified?email=user%40example.com`

**Frontend Implementation**:
```javascript
// Example in React/Vue
const EmailVerified = () => {
  const { email } = useQueryParams();
  
  return (
    <div className="verification-success">
      <h2>Email Verified Successfully!</h2>
      <p>Your email {email} has been verified.</p>
      <p>You can now log in to your account.</p>
      <Link to="/login">Go to Login</Link>
    </div>
  );
};
```

### 2. Already Verified
**Route**: `/auth/already-verified`

**Frontend Implementation**:
```javascript
const AlreadyVerified = () => {
  return (
    <div className="already-verified">
      <h2>Email Already Verified</h2>
      <p>Your email address has already been verified.</p>
      <p>You can proceed to log in.</p>
      <Link to="/login">Go to Login</Link>
    </div>
  );
};
```

### 3. Verification Failed
**Route**: `/auth/verification-failed`
**Query Parameters**: `?reason=invalid_link`

**Frontend Implementation**:
```javascript
const VerificationFailed = () => {
  const { reason } = useQueryParams();
  
  const getErrorMessage = (reason) => {
    switch(reason) {
      case 'invalid_link':
        return 'The verification link is invalid or has expired.';
      default:
        return 'Email verification failed. Please try again.';
    }
  };
  
  return (
    <div className="verification-failed">
      <h2>Verification Failed</h2>
      <p>{getErrorMessage(reason)}</p>
      <Link to="/auth/resend">Resend Verification Email</Link>
    </div>
  );
};
```

## Complete Flow

1. **User registers** → Receives verification email
2. **User clicks verification link** → `GET /api/auth/email/verify/{id}/{hash}`
3. **Backend verifies** → Redirects to frontend:
   - Success: `/auth/email-verified?email=user@example.com`
   - Already verified: `/auth/already-verified`
   - Failed: `/auth/verification-failed?reason=invalid_link`
4. **Frontend handles** → Shows appropriate message

## Optional: Auto-login After Verification

If you want to auto-login the user after verification:

### Backend Update
Add token generation in `verifyEmail` method:

```php
// After successful verification
$user->markEmailAsVerified();
event(new Verified($user));

// Generate token for auto-login
$token = $user->createToken('auth-token')->plainTextToken;

// Redirect with token
$frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
return redirect($frontendUrl . '/auth/email-verified?' . http_build_query([
    'email' => $user->email,
    'token' => $token,
    'auto_login' => 'true'
]));
```

### Frontend Implementation
```javascript
const EmailVerified = () => {
  const { email, token, auto_login } = useQueryParams();
  
  useEffect(() => {
    if (auto_login === 'true' && token) {
      // Store token and redirect to dashboard
      localStorage.setItem('auth_token', token);
      navigate('/dashboard');
    }
  }, [auto_login, token]);
  
  // ... rest of component
};
```

## Security Notes

1. **Token Expiry**: Verification links expire based on `auth.passwords.users.expire` config
2. **One-time Use**: Verification links can only be used once
3. **Rate Limiting**: Consider rate limiting resend verification endpoint
4. **HTTPS**: Always use HTTPS in production to prevent token interception

## Testing

1. Register a new user
2. Click the verification link in email
3. Verify you're redirected to the correct frontend page
4. Test all scenarios: success, already verified, invalid link
