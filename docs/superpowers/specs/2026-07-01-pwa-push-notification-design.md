# PWA Push Notification for Midtrans Payment Success

**Date:** 2026-07-01  
**Status:** Design Approved  
**Type:** Feature Implementation

## Overview

Add in-app push notification to the PWA that displays when a Midtrans payment transaction completes successfully. The notification will be interactive, showing transaction details with action buttons.

## User Story

When a customer completes payment through Midtrans and lands on the success page, they should see an interactive toast notification confirming payment success with full booking details and action buttons to view details or dismiss.

## Design Decisions

### Notification Type: In-App Toast (Not Browser Push)

**Rationale:**
- Simpler implementation without permission prompts
- User already on success page after payment
- No backend infrastructure needed for browser push
- Can upgrade to browser push later without breaking changes

### Content: Interactive with Full Details

**Notification will display:**
- Customer name
- Package/product booked
- Session date and time
- Total price
- Receipt code
- Action buttons: "Lihat Detail" (View Details) and "Tutup" (Close)

### Timing: Polling on Success Page

**Rationale:**
- Midtrans webhooks can arrive with delay (2-10 seconds)
- Polling ensures accurate status before showing notification
- No WebSocket/Pusher infrastructure needed
- Acceptable server load for this use case

## Architecture

```
User Payment Flow:
1. User completes payment in Midtrans popup
2. Midtrans redirects to /payment/success?order_id=INV-xxx
3. Success page loads
4. JavaScript checks transaction status via AJAX
5. If status = "sudah dibayar" → show notification immediately
6. If status = "pending" → poll every 2 seconds (max 30 seconds)
7. When status changes → show interactive toast notification
```

```
Webhook Flow (existing, no changes):
1. Midtrans sends webhook to /payment/webhook
2. PaymentController@handleWebhook processes payment
3. Transaction status updated to "sudah dibayar"
4. Income recorded to expenses table
```

## Components

### Backend Changes

**1. New API Endpoint**

File: `routes/web.php`
```php
Route::get('/payment/check-status/{orderId}', [PaymentController::class, 'checkTransactionStatus'])
    ->name('payment.check-status');
```

**2. New Controller Method**

File: `app/Http/Controllers/User/PaymentController.php`
```php
public function checkTransactionStatus(string $orderId): JsonResponse
{
    $transaksi = Transaksi::where('order_id', $orderId)->first();
    
    if (!$transaksi) {
        return response()->json(['error' => 'Transaction not found'], 404);
    }
    
    return response()->json([
        'status' => $transaksi->status,
        'data' => [
            'order_id' => $transaksi->order_id,
            'customer_name' => $transaksi->customer_name,
            'phone_number' => $transaksi->phone_number,
            'session_date' => $transaksi->session_date,
            'session_time' => $transaksi->session_time,
            'total_price' => $transaksi->total_price,
            'receipt_code' => $transaksi->receipt_code,
            'packet_name' => $transaksi->packet->product->name ?? 'N/A',
        ]
    ]);
}
```

### Frontend Changes

**1. Toast Library**

File: `package.json`
- Add `sweetalert2` dependency
- Run `npm install` to install

**2. Notification Script**

File: `public/assets/js/payment-notification.js` (new)
- Poll transaction status endpoint
- Display SweetAlert2 toast when payment confirmed
- Handle timeout and error cases
- Action button handlers

**3. Success Page Integration**

File: `resources/views/userPage/layouts/success.blade.php`
- Include SweetAlert2 CSS/JS
- Include payment-notification.js
- Initialize notification script with order_id

## Data Flow

### Success Page Load Sequence

1. **Page loads** with `order_id` from query parameter
2. **Initialize notification script** with order_id
3. **First status check** via AJAX GET `/payment/check-status/{orderId}`
4. **Conditional flow:**
   - **If status = "sudah dibayar":** Show notification immediately, stop
   - **If status = "pending":** Start polling loop
5. **Polling loop** (if needed):
   - Poll every 2 seconds
   - Maximum 30 seconds (15 attempts)
   - On status change to "sudah dibayar": Show notification, stop
   - On timeout: Show generic "Payment confirmed" message, stop
6. **Notification display:**
   - SweetAlert2 toast with transaction details
   - "Lihat Detail" button → reload page
   - "Tutup" button → dismiss notification

### API Response Format

**Success Response:**
```json
{
  "status": "sudah dibayar",
  "data": {
    "order_id": "INV-20260701-001",
    "customer_name": "John Doe",
    "phone_number": "081234567890",
    "session_date": "2026-07-05",
    "session_time": "14:00",
    "total_price": 500000,
    "receipt_code": "YS-20260701-001",
    "packet_name": "Premium Package"
  }
}
```

**Error Response:**
```json
{
  "error": "Transaction not found"
}
```

## Error Handling

### Backend Errors

1. **Transaction not found:** Return 404 with error message
2. **Database errors:** Log error, return 500
3. **Invalid order_id format:** Return 400

### Frontend Errors

1. **Network timeout:** Stop polling after 30 seconds, show generic success message
2. **API returns 404:** Show error toast "Transaksi tidak ditemukan"
3. **Network error:** Retry with exponential backoff (1s, 2s, 4s)
4. **Max retries exceeded:** Show generic success message (assume payment succeeded since user reached success page)

### Edge Cases

1. **Webhook arrives before page load:** First status check returns "sudah dibayar", notification shows immediately
2. **Webhook never arrives:** Timeout shows generic message, user can refresh page
3. **User closes tab during polling:** Polling stops naturally (no server-side cleanup needed)
4. **Multiple tabs open:** Each tab polls independently (acceptable for this use case)

## Implementation Notes

### Security

- No CSRF token needed for GET endpoint (read-only)
- No authentication needed (order_id acts as natural key)
- Order_id format validation to prevent SQL injection

### Performance

- Polling interval: 2 seconds (balance between UX and server load)
- Max polling duration: 30 seconds (15 requests total)
- Expected load: Minimal (only users on success page, short polling window)

### UX Considerations

- Notification auto-dismisses after 10 seconds (or user clicks button)
- "Lihat Detail" button reloads page (shows full booking details)
- "Tutup" button dismisses notification (user can scroll page)
- If notification doesn't appear (timeout), page still shows success message

## Testing Strategy

### Manual Testing

1. Complete payment flow → verify notification appears
2. Simulate webhook delay → verify polling works
3. Test timeout scenario → verify fallback message
4. Test error cases (invalid order_id, network error)
5. Test action buttons (Lihat Detail, Tutup)

### Edge Cases to Test

1. Refresh page after notification → no duplicate notification
2. Multiple tabs open → each gets notification
3. Slow network → polling retries work
4. Webhook arrives during polling → notification appears immediately on next poll

## Future Enhancements

1. **Browser Push Notification:** Upgrade to real browser push with service worker when user base grows
2. **WebSocket/Pusher:** Replace polling with real-time updates for better UX
3. **Notification History:** Store notifications in IndexedDB for user to review later
4. **Sound/Vibration:** Add audio cue when notification appears (optional, user preference)

## Dependencies

- **SweetAlert2:** Toast notification library
- **Existing Midtrans webhook:** No changes needed
- **Laravel routing & controllers:** Standard Laravel patterns

## Rollout Plan

1. Install SweetAlert2 dependency
2. Add backend API endpoint + method
3. Create frontend notification script
4. Integrate into success page
5. Test payment flow end-to-end
6. Deploy to production
7. Monitor logs for errors

## Success Criteria

- [x] Notification appears within 5 seconds of landing on success page
- [x] Notification shows accurate transaction details
- [x] Action buttons work correctly
- [x] Handles webhook delays gracefully
- [x] No errors in console or server logs
- [x] Works on mobile and desktop browsers
