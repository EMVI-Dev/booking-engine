# Vendor Management & Automated Dispatch Specification

## Overview

In tour and travel operations, independent operators and freelance guides frequently curate tours and resell activities provided by 3rd-party suppliers (e.g. ATV parks, boat charters, dive centers, equipment rental shops).

The **Vendor Management & Automated Dispatch** system allows operators to:
1. Maintain their own directory of 3rd-party suppliers.
2. Optionally attach activities (`Product`) to vendors.
3. Automatically notify suppliers via email when bookings are paid and confirmed.
4. Provide vendors with a secure, tokenized **Vendor Dispatch Sheet** that displays operational details (Booker info, guest manifest, schedule) while keeping customer retail checkout pricing hidden.

---

## 1. Domain Model & Database Schema

### `vendors` Table
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `ulid` (PK) | Unique vendor identifier |
| `operator_id` | `ulid` (FK $\rightarrow$ `operators.id`) | Tenant operator ownership (Cascade on delete) |
| `name` | `string` | Vendor / Supplier business name (e.g. *Bali ATV Adventures*) |
| `contact_person` | `string` (nullable) | Primary contact person name (e.g. *Wayan*) |
| `reservation_email` | `string` | Dedicated inbox where booking dispatch notifications are delivered |
| `phone` | `string` (nullable) | Direct phone / WhatsApp number |
| `payout_details` | `json` (nullable) | Future-ready payout account details (`bank_name`, `account_number`, `account_holder`) |
| `metadata` | `json` (nullable) | Extensible metadata column for future settings or tags |
| `is_active` | `boolean` (default `true`) | Active status toggle |
| `created_at` / `updated_at` | `timestamps` | Standard Eloquent timestamps |

### `products` Table Modification
* `vendor_id` (`ulid`, nullable, FK $\rightarrow$ `vendors.id`, null on delete): When null, the activity is in-house (owned/operated by the guide). When set, the activity is outsourced to the assigned vendor.

---

## 2. Operator Workflow & UX

### Vendor Management Portal (`/vendors`)
* Livewire SFC at [`resources/views/pages/vendors/⚡index.blade.php`](file:///Users/mastervarol/Herd/booking/resources/views/pages/vendors/⚡index.blade.php).
* Search, filter by active status, view linked activities count.
* Create, edit, delete, and toggle active status.
* Manage optional payout account details.
* **Test Email Dispatch**: Operators can trigger a test dispatch copy (with `[TEST]` subject and test banner) directly from table row actions or the edit modal to verify vendor inbox deliverability.

### Inline Vendor Creation during Activity Setup
* Inside [`resources/views/pages/products/⚡create.blade.php`](file:///Users/mastervarol/Herd/booking/resources/views/pages/products/⚡create.blade.php) and [`⚡edit.blade.php`](file:///Users/mastervarol/Herd/booking/resources/views/pages/products/⚡edit.blade.php):
  * **Activity Vendor / Supplier (Optional)** dropdown selector.
  * **"+ Add New Vendor"** button opens a quick modal allowing the operator to register and auto-select a new vendor immediately without losing their in-progress form inputs.

---

## 3. Automated Dispatch Lifecycle

Handled by [`App\Services\VendorDispatchService`](file:///Users/mastervarol/Herd/booking/app/Services/VendorDispatchService.php).

```
Booking Paid & Confirmed
           │
           ▼
Resolve Reservation Bookable
 ├── Single Product:
 │     └── If product->vendor_id exists:
 │           └── Send VendorBookingNotificationMail to vendor->reservation_email
 │
 └── Package (Multi-Activity Bundle):
       └── Iterate package products and group by vendor_id
       └── Send tailored VendorBookingNotificationMail to each unique vendor
           (containing only that vendor's specific assigned activities)
```

### Notification Triggers
1. **Automated DOKU Payment:** When webhook/sync verifies payment in [`DokuPaymentService`](file:///Users/mastervarol/Herd/booking/app/Services/DokuPaymentService.php).
2. **Manual Confirmation:** When an operator marks a booking confirmed in [`pages/reservations/⚡index.blade.php`](file:///Users/mastervarol/Herd/booking/resources/views/pages/reservations/⚡index.blade.php).
3. **Booking Cancellation:** When a paid booking is cancelled in [`GuestCancellationService`](file:///Users/mastervarol/Herd/booking/app/Services/GuestCancellationService.php), dispatches [`VendorBookingCancelledMail`](file:///Users/mastervarol/Herd/booking/app/Mail/VendorBookingCancelledMail.php).

---

## 4. Email & Dispatch Views

### Email Templates
* **Origin:** Platform SMTP notification address.
* **Header / Branding:** Operator's brand color, logo, and business name.
* **Booker Card:** Clear identification of the operator (Booker Name, Email, Phone/WhatsApp).
* **Guest Manifest:** Lead guest name, contact, party size, and special requests.
* **Call to Action:** **"View Full Booking Details →"** button linking to the tokenized dispatch sheet.

### Token-Secured Vendor Dispatch View (`/find-booking/{reservation}/vendor`)
* **URL:** `GET /find-booking/{reservation->public_token}/vendor?token={public_token}`
* **Security:** Secured by matching `token === reservation.public_token` (no vendor login required; 403 on missing or invalid token).
* **Display:**
  * Booker / Operator Card (with 1-tap WhatsApp and Call buttons).
  * Guest Manifest & Special Requests.
  * Activity details & Schedule.
  * Printable run-sheet view (`window.print()`).
  * **Customer retail price is completely hidden.**
