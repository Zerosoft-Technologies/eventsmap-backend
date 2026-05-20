# Subscription API — Frontend integration

## Endpoint

```
GET /api/subscription
```

**Authentication:** Required (`Authorization: Bearer {sanctum_token}`)

Returns the authenticated user's account tier, billing details, current Stripe subscription row (if any), and UI action flags.

---

## Example response (premium, active)

```json
{
  "success": true,
  "message": "Subscription fetched successfully",
  "data": {
    "account": {
      "account_type": "premium",
      "status": "active",
      "premium_started_at": "2026-05-20T10:00:00+00:00",
      "is_premium": true,
      "is_premium_active": true,
      "requires_payment": false,
      "email_verified": true
    },
    "billing": {
      "billing_type": "private",
      "full_name": "Jane Doe",
      "company_name": null,
      "vat_number": null,
      "vat_validated": false,
      "address": "Rue Example 1",
      "postal_code": "1000",
      "city": "Brussels",
      "country": "BE"
    },
    "subscription": {
      "id": 1,
      "plan_name": "Premium Account",
      "billing_interval": "monthly",
      "billing_interval_label": "Monthly",
      "billing_mode": "subscription",
      "subscription_status": "active",
      "payment_status": "paid",
      "amount": 1999,
      "amount_formatted": "€19.99",
      "currency": "EUR",
      "quantity": 1,
      "current_period_start": "2026-05-20T10:00:00+00:00",
      "current_period_end": "2026-06-20T10:00:00+00:00",
      "payment_method": {
        "brand": "visa",
        "last4": "4242",
        "label": "Visa •••• 4242"
      },
      "is_recurring": true
    },
    "payment_method": {
      "brand": "visa",
      "last4": "4242",
      "exp_month": 12,
      "exp_year": 2028,
      "label": "Visa •••• 4242"
    },
    "actions": {
      "can_upgrade": false,
      "can_retry_payment": false,
      "can_view_invoices": true
    },
    "stripe": {
      "customer_id": "cus_xxx",
      "subscription_id": "sub_xxx"
    }
  }
}
```

## Example response (free user)

```json
{
  "success": true,
  "message": "Subscription fetched successfully",
  "data": {
    "account": {
      "account_type": "free",
      "status": "active",
      "is_premium": false,
      "is_premium_active": false,
      "requires_payment": false
    },
    "billing": { "...": "..." },
    "subscription": null,
    "payment_method": null,
    "actions": {
      "can_upgrade": true,
      "can_retry_payment": false,
      "can_view_invoices": false
    },
    "stripe": {
      "customer_id": null,
      "subscription_id": null
    }
  }
}
```

## UI mapping

| UI state | Use fields |
|----------|------------|
| Show "Upgrade to Premium" | `actions.can_upgrade === true` → `POST /api/user/upgrade-plan` |
| Show "Complete payment" | `actions.can_retry_payment === true` → `POST /api/payment/retry` |
| Premium badge active | `account.is_premium_active` |
| Plan name / price | `subscription.plan_name`, `subscription.amount_formatted` |
| Renewal date | `subscription.current_period_end` (recurring only) |
| Billing address block | `data.billing` |
| Invoices link | `actions.can_view_invoices` → `GET /api/invoices` |

## TypeScript fetch example

```ts
const API_URL = import.meta.env.VITE_API_URL;

export async function fetchSubscription(token: string) {
  const res = await fetch(`${API_URL}/api/subscription`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });

  if (!res.ok) {
    throw new Error((await res.json()).message ?? 'Failed to load subscription');
  }

  return res.json() as Promise<{
    success: boolean;
    data: SubscriptionOverview;
  }>;
}
```

## Related endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/user/upgrade-plan` | Start Stripe Checkout (free → premium) |
| POST | `/api/payment/verify` | Confirm checkout after redirect |
| POST | `/api/payment/retry` | New checkout when `pending_payment` |
| GET | `/api/invoices` | List PDF invoices |

## Errors

| Status | Meaning |
|--------|---------|
| 401 | Missing or invalid token |
| 500 | Server error (rare) |
