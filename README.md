# Villa Booking

Standalone Laravel villa-booking platform: searchable listings, amenity selection, guest-capacity/minimum-stay validation, seasonal nightly rates, reservation confirmation/reference, cancellation, verified-stay reviews, owner-scoped management, occupancy/revenue reporting.

## Setup (Windows)

```powershell
Copy-Item .env.example .env
php C:/Users/wondr/.config/herd-lite/bin/composer.phar install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

A trusted administrator may grant owner/admin access by setting `users.is_admin = 1`. Users can create and manage their own villas; admins can see/manage all villas. No accounts, properties, bookings, payments, or revenue are seeded. Amenity names are the only lookup fixtures. No payment is collected or represented as completed. Villa pictures are public; no guest identity documents are requested.

## Booking and reports

- Stay interval is `[check_in, check_out)`; check-out is exclusive. At least one night; minimum nights and guest capacity are enforced server-side.
- Total = sum of each booked night’s applicable rate. Seasonal periods use `[starts_on, ends_on)` and override the base nightly rate. Overlapping configured seasonal rates reject new bookings until corrected.
- Reservation allocation uses a DB transaction and locks the villa row before checking conflicts and inserting. Use PostgreSQL/MySQL for cross-process row locks in production; SQLite tests do not establish concurrency safety.
- Guests may cancel only their own future confirmed bookings. Owners’ dashboard, reports, seasonal-rate changes, and villa updates are scoped to their villas; admins have global scope.
- Revenue report is confirmed-booking value (not payment receipts). Reviews require a completed, past stay and belong to its guest.

## Verification

```powershell
php artisan migrate:fresh --seed --no-interaction
php artisan view:clear
php artisan view:cache
php artisan test --compact
npm run build
php C:/Users/wondr/.config/herd-lite/bin/composer.phar validate --no-check-publish
php C:/Users/wondr/.config/herd-lite/bin/composer.phar audit
npm audit --omit=dev
```

Keep `.env`, personal data, databases, dependencies, uploaded images, and build output out of Git.

## Screenshots

### Dashboard Overview
![Dashboard](docs/screenshots/01_dashboard.png)

### Login Overview
![Login](docs/screenshots/02_login.png)
