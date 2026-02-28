# Upgrade Plan API Documentation

Allows authenticated FREE users to upgrade to PREMIUM account via Stripe Checkout.

## Endpoint

```
POST /api/user/upgrade-plan
```

**Authentication**: Required (Sanctum Bearer Token)

## Request Body

### For Private Billing
```json
{
    "billing_type": "private",
    "full_name": "John Doe",
    "country": "DE",
    "address": "123 Main Street",
    "postal_code": "10115",
    "city": "Berlin"
}
```

### For Business Billing
```json
{
    "billing_type": "business",
    "company_name": "Acme Corp",
    "vat_number": "DE123456789",
    "country": "DE",
    "address": "456 Business Ave",
    "postal_code": "10115",
    "city": "Berlin"
}
```

## Validation Rules

| Field | Type | Rules |
|-------|------|-------|
| billing_type | string | required, in:private,business |
| full_name | string | required if billing_type=private, max:255 |
| company_name | string | required if billing_type=business, max:255 |
| vat_number | string | required if billing_type=business, EU format (e.g. DE123456789) |
| country | string | required, max:255 |
| address | string | required, max:255 |
| postal_code | string | required, max:20 |
| city | string | required, max:255 |

## Response Examples

### Success - Checkout URL Generated
**Status: 200 OK**
```json
{
    "success": true,
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "session_id": "cs_test_..."
}
```

### Error - Already Premium
**Status: 400 Bad Request**
```json
{
    "success": false,
    "message": "You are already on the premium plan."
}
```

### Error - Email Not Verified
**Status: 403 Forbidden**
```json
{
    "success": false,
    "message": "Please verify your email before upgrading."
}
```

### Error - Account Not Active
**Status: 403 Forbidden**
```json
{
    "success": false,
    "message": "Your account must be active to upgrade."
}
```

### Error - Invalid VAT Number
**Status: 422 Unprocessable Entity**
```json
{
    "success": false,
    "message": "Invalid VAT number format.",
    "errors": {
        "vat_number": ["The VAT number format is invalid. Expected EU format (e.g. DE123456789)."]
    }
}
```

### Error - Stripe API Failed
**Status: 500 Internal Server Error**
```json
{
    "success": false,
    "message": "Payment setup failed. Please try again."
}
```

### Error - Not Authenticated
**Status: 401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

## Complete Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        UPGRADE FLOW                              │
└─────────────────────────────────────────────────────────────────┘

1. Frontend (logged in FREE user)
   │
   ▼
2. POST /api/user/upgrade-plan
   {
     "billing_type": "private",
     "full_name": "John Doe",
     "country": "DE",
     ...
   }
   │
   ▼
3. Backend validates:
   ✓ User authenticated
   ✓ User is FREE account
   ✓ Email is verified
   ✓ Account status is ACTIVE
   ✓ Billing fields are valid
   │
   ▼
4. Backend creates/updates Stripe Customer
   │
   ▼
5. Backend creates Stripe Checkout Session
   - metadata.type = "upgrade"
   - metadata.user_id = <user_id>
   │
   ▼
6. Returns checkout_url to frontend
   │
   ▼
7. Frontend redirects user to Stripe Checkout
   │
   ▼
8. User completes payment on Stripe
   │
   ▼
9. Stripe sends webhook: checkout.session.completed
   │
   ▼
10. Backend webhook handler:
    - Checks metadata.type === "upgrade"
    - Updates user:
      - account_type = 'premium'
      - premium_started_at = now()
      - status = 'active'
   │
   ▼
11. Stripe redirects to: /payment/success?session_id=...
   │
   ▼
12. Frontend calls GET /api/auth/me
   │
   ▼
13. User sees premium UI
```

## Webhook Handling

The existing webhook at `POST /api/webhook/stripe` handles the upgrade:

```php
case 'checkout.session.completed':
    $session = $event->data->object;
    $user = User::where('stripe_customer_id', $session->customer)->first();

    if ($user) {
        $isUpgrade = isset($session->metadata->type) && $session->metadata->type === 'upgrade';

        if ($isUpgrade || $user->account_type === User::ACCOUNT_FREE) {
            $user->update([
                'account_type' => User::ACCOUNT_PREMIUM,
                'premium_started_at' => now(),
                'status' => User::STATUS_ACTIVE,
            ]);
        }
    }
    break;
```

## Database Changes

### Migration
Run: `php artisan migrate`

Adds `premium_started_at` column to users table:
```php
$table->timestamp('premium_started_at')->nullable();
```

## Security Features

1. **Authentication Required**: Only logged-in users can upgrade
2. **Email Verification Required**: Must verify email first
3. **Account Status Check**: Account must be active
4. **Duplicate Prevention**: Already premium users cannot upgrade again
5. **Stripe Signature Verification**: Webhook validates Stripe signature
6. **Input Sanitization**: All billing fields are validated
7. **VAT Validation**: EU VAT format is validated for business accounts

## Testing

### 1. Test Upgrade Request (with curl)
```bash
curl -X POST http://localhost:8001/api/user/upgrade-plan \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "billing_type": "private",
    "full_name": "Test User",
    "country": "DE",
    "address": "123 Test Street",
    "postal_code": "10115",
    "city": "Berlin"
  }'
```

### 2. Stripe Test Cards
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`

### 3. Test Webhook (Stripe CLI)
```bash
stripe listen --forward-to localhost:8001/api/webhook/stripe
```

## Files Modified/Created

1. **Created**: `app/Http/Controllers/UpgradePlanController.php`
2. **Created**: `database/migrations/2025_02_28_000001_add_premium_started_at_to_users_table.php`
3. **Modified**: `app/Models/User.php` - Added `premium_started_at` to fillable
4. **Modified**: `app/Http/Controllers/StripeController.php` - Updated webhook handler
5. **Modified**: `routes/api.php` - Added upgrade route
