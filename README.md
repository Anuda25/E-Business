# TourGuide E-Business Platform

A complete, production-style PHP/MySQL travel booking platform built for XAMPP.

## 🚀 Quick Start

1. **Place** this folder in `xampp/htdocs/` so the path is:
   ```
   xampp/htdocs/New/
   ```

2. **Start** XAMPP Apache and MySQL services.

3. **Configure local settings:** copy `config.local.php.example` to `config.local.php` and enter your database and PayHere sandbox credentials. The local file is ignored by Git.

4. **Open setup** in your browser:
   ```
   http://localhost/New/setup.php
   ```
   This automatically repairs database tables, migrates roles, creates the default Admin and Guest accounts, and seeds sample listings across all accommodation, bike, equipment, and guide categories. Existing seeded listings are not duplicated when setup is rerun.

5. **Visit the website:**
   ```
   http://localhost/New/
   ```

The application does not impose a booking payment amount cap. PayHere may still enforce limits based on your merchant account, currency, and transaction settings.
For PayHere, set `PAYHERE_NOTIFY_URL` in `config.local.php` to a publicly reachable HTTPS URL such as `https://YOUR_PUBLIC_DOMAIN/New/api.php?action=payhere_notify`. The URL must point to the notification handler, and the domain must be enabled for the merchant account. For local testing, run `C:\xampp\php\php.exe -S 127.0.0.1:8181 -t C:\xampp\htdocs\New C:\xampp\htdocs\New\payhere_tunnel_router.php`, then run `cloudflared tunnel --url http://127.0.0.1:8181` and set the generated URL with `/?action=payhere_notify` as `PAYHERE_NOTIFY_URL`. The tunnel router exposes only the PayHere callback, not the rest of the local site. Quick tunnel URLs are temporary and change when restarted. Use matching sandbox or live merchant credentials and environment. Payment status is confirmed only after the signed server notification is received.
Item ratings and review counts are calculated from submitted guest reviews; unrated items show as new until their first review.

---

## 🔑 Default Accounts

| Role | Username | Password | Email |
|------|----------|----------|-------|
| **Admin** | `admin` | `admin123` | `admin@example.test` |
| **Guest** | `traveler` | `password123` | `traveler@example.test` |

These are demo credentials. Change them before deploying the application publicly.

---

## 📁 Project Structure

```
New/
├── index.html          — Main SPA frontend (Cyan & Dark Navy Theme)
├── api.php             — REST-style PHP API backend with strict role authorization
├── config.php          — Database & application configuration
├── config.local.php.example — Local-only database and payment settings template
├── db.php              — MySQL database connection provider
├── setup.php           — Automated table repair, migration, and catalog seeder
├── app.js              — Frontend application logic & profile panels
├── style.css           — Premium dark-themed stylesheet
├── image/              — Stock and uploaded image assets
└── README.md           — Documentation
```

---

## 👥 New Account Rules & Architecture

### 👤 Guest
- **Account Creation:** Can self-register a Guest account with Full Name, Username, Email, Bank Details, Phone, and Password.
- **Authentication:** Can log in and log out at any time.
- **Catalog Browsing:** Can browse and filter all available items (Accommodations, Bikes, Equipment, Tour Guides).
- **Reservations:** Can book accommodations, bikes, equipment, and hire tour guides. Accommodation, bike, and equipment listings can have multiple units; guests choose a quantity before selecting dates, and bookings reserve only that quantity.
- **Availability:** Accommodation, bike, and equipment calendars show remaining units for each date and allow overlapping bookings while enough inventory remains. Admins set the inventory when creating or editing listings.
- **Strict Boundaries:**
  - Cannot add items.
  - Cannot remove items.
  - Cannot create Admin accounts.

### 👑 Admin
- **Authentication:** Can log in using Admin credentials (default: `admin` / `admin123`).
- **Item Creation:** Can insert new Accommodations, Bikes, Equipment, and Tour Guides with optional image upload.
- **Ownership Tracking:** Every new listing is stored with that Admin's username as `created_by`.
- **Item Deletion:** Can **remove only items created by that Admin**. Deleting items owned by another Admin is strictly forbidden with HTTP 403 authorization enforcement.
- **Admin Provisioning:** Can create additional Admin accounts using the same fields as Guest registration:
  - Full Name
  - Username
  - Email & Bank Details
  - Phone
  - Password & Confirm Password
- **Role Isolation:** Guest accounts cannot create Admin accounts.
- **Received Bookings:** Can view all customer reservations received for listings created by that Admin.

---

## 📦 Catalog Sub-Items (Guaranteed 2+ items each)

| Category | Sub-Item Type | Seed Count |
|----------|---------------|------------|
| **Accommodation** | `Villa` | 4 items |
| **Accommodation** | `Apartment` | 4 items |
| **Accommodation** | `Room` | 8 items |
| **Bikes** | `Mountain Bike` | 6 items |
| **Bikes** | `Road Bike` | 6 items |
| **Bikes** | `City Bike` | 6 items |
| **Bikes** | `Electric Bike` | 2 items |
| **Equipment** | `Surfboard` | 4 items |
| **Equipment** | `Skateboard` | 3 items |
| **Equipment** | `Boat` | 4 items |
| **Equipment** | `Kayak` | 2 items |
| **Equipment** | `Snorkeling Gear` | 4 items |
| **Equipment** | `Camping Gear` | 3 items |
| **Tour Guides** | `Wildlife Safari & Nature` | 2 guides |
| **Tour Guides** | `Surfing & Ocean Safety` | 2 guides |
| **Tour Guides** | `Heritage & Ancient History` | 2 guides |
| **Tour Guides** | `Mountain & Trekking` | 2 guides |

---

## 🛠 Database Migration & Repair Fixes

The database initializer (`setup.php`) includes:
1. **Schema Repair:** Automatically adds missing columns (`bank_details`, `full_name`, `created_by`, `time_period`, bike and equipment `units`, `start_date`, `end_date`, etc.) and creates the `item_date_blocks` table for Admin-managed temporary booking blocks without data loss. The app also ensures inventory columns and the date-block table exist on its first database connection, so existing installs do not need a manual setup run for date blocking.
2. **Availability Calendars:** Manage My Items calendars show existing bookings and temporary blocks; Admins select available date ranges to block them and can remove a block from its calendar or block list.
3. **Role Migration:** Automatically detects older `Host` roles and migrates them to `Admin`.
4. **Admin & Guest Restoration:** Creates default `admin` and `traveler` accounts when missing and preserves existing credentials and profile details on subsequent runs.
5. **Ownership Preservation:** Ensures all listings have a valid `created_by` username.
6. **Universal Seeder:** Inserts missing defaults across all 4 main tables (`accommodations`, `bikes`, `equipment`, `guides`) without overwriting existing listings, guaranteeing at least 2 items per sub-type on a fresh setup.
