========================================================================
503073 - WEB PROGRAMMING & APPLICATIONS
FINAL PROJECT: TECHFIX - DEVICE REPAIR BOOKING & MANAGEMENT SYSTEM
========================================================================

1. GITHUB REPOSITORY & DEMO VIDEO
------------------------------------------------------------------------
- Repository URL: https://github.com/NgoTuanTuns/techfix.git
- Demo Video:     https://www.youtube.com/watch?v=YjNnkfQ-9wg

2. SYSTEM REQUIREMENTS
------------------------------------------------------------------------
- XAMPP (Apache + MySQL/MariaDB 10.4+ + PHP 8.0+)
- Web Browser (Chrome, Edge, Firefox, Safari)
- OpenSSL PHP extension enabled (default in XAMPP, used for SMTP TLS email)

3. INSTALLATION & RUNNING INSTRUCTIONS (LOCAL XAMPP)
------------------------------------------------------------------------
Step 1: Place the project source files in the XAMPP web root:
  - Option A (Direct root): Copy contents directly into `C:\xampp\htdocs\`
    and access via `http://localhost/` (or `http://localhost:<port>/`).
  - Option B (Subfolder): Copy into `C:\xampp\htdocs\techfix\`
    and access via `http://localhost/techfix/`.
  - Note: All internal URLs use relative paths (`../api/`, `../assets/`), so
    the application works on any port or folder level without code changes.
    Accessing the root folder automatically redirects to `public/index.html`
    via `index.php`.

Step 2: Start Apache and MySQL in the XAMPP Control Panel.

Step 3: Initialize the Database:
  1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
  2. Import `techfix_schema.sql` (this automatically creates the `techfix`
     database, all 11 tables, foreign keys, 15 sample services, and 8
     inventory parts).
  3. Import `seed_admin.sql` (this seeds pre-configured Admin, Technician,
     and Customer accounts for evaluation).

Step 4: Configure Environment Variables (Optional - for SMTP Email):
  - Database connection settings in `config/database.php` default to standard
    XAMPP credentials (`host=localhost`, `dbname=techfix`, `user=root`, `pass=""`).
  - For live SMTP email notifications (`includes/mailer.php`), copy
    `.env.example` to `.env` and fill in `TECHFIX_SMTP_USER` and
    `TECHFIX_SMTP_PASS` (16-character Gmail App Password).
  - Note: If SMTP is not configured locally, outgoing emails are also saved
    as HTML preview files inside `uploads/emails/` for inspection.

4. PRE-CONFIGURED TEST ACCOUNTS FOR EVALUATION
------------------------------------------------------------------------
All seeded accounts below use the password:  admin123

  [1] Role: Admin (Full access to Orders, Technicians, Inventory & Analytics)
      - Phone (Login ID): 0123456789
      - Password:         admin123
      - Admin Portal:     public/admin.html

  [2] Role: Technician (View & update assigned repair orders, inventory)
      - Phone (Login ID): 0911222333
      - Password:         admin123
      - Technician View:  public/admin.html

  [3] Role: Customer (Book repairs, approve quotes, track status, review)
      - Phone (Login ID): 0988777666
      - Password:         admin123
      - Customer Portal:  public/dashboard.html

5. KEY FEATURES SUMMARY
------------------------------------------------------------------------
- Account Management & Security:
  + Registration with valid email address, bcrypt password hashing, double
    password confirmation, and automatic login after sign-up (`api/register.php`).
  + Account activation via email verification link with a persistent notification
    banner on the dashboard and booking page until verified (account activation
    is required before booking a repair order), plus a resend option
    (`api/activate_account.php`, `api/resend_activation.php`, `public/dashboard.html`,
    `public/booking.html`, `api/create_order.php`).
  + Password recovery via time-limited secure email link (30-minute expiration),
    dedicated reset password screen with double confirmation (`api/forgot_password.php`,
    `public/reset-password.html`, `api/reset_password.php`).
  + Role-based access control (`customer`, `technician`, `admin`), session
    fixation protection, and automatic DB-session role synchronization
    (`includes/auth.php`, `api/me.php`).
  + Profile & Avatar management (upload custom avatar with MIME/size validation
    or display default initials avatar) and password change with double
    confirmation (`public/dashboard.html`, `api/update_profile.php`).
  + User Preferences: Light / Dark theme toggle persisted in `localStorage`.

- Repair Booking & Order Workflow:
  + 2-step repair booking with device category selector (Laptop, Phone, Tablet),
    automatic package pre-fill from Homepage Weekly Deals and Service Catalog
    (`?service=...`), and delivery method selection (In-store drop-off restricted
    to operating hours 07:30-21:00, or Doorstep pickup with automatic 20,000 VND
    delivery fee).
  + Linear, forward-only order status progression with custom confirmation
    modals before every state transition (`public/admin.html`, `api/update_order_status.php`).
  + Two-way Quote Approval workflow: Technician enters estimated cost (supports
    0 VND / Free inspection packages) -> Customer approves, requests re-quote,
    or cancels (`api/approve_quote.php`, `public/dashboard.html`).
  + Manual email notification trigger when an order reaches "Awaiting Payment"
    (`includes/mailer.php`, `api/update_order_status.php`).
  + Admin Analytics Dashboard, Spare Parts Inventory management, Technician
    workload assignment, live search & multi-criteria filtering.
  + Verified Customer Reviews with rating distribution bars, customer avatars,
    and pop-up review modal (`public/contact.html`, `api/get_reviews.php`,
    `api/submit_review.php`).

- Offline Capabilities (PWA):
  + Web App Manifest (`public/manifest.json`) and Service Worker (`public/sw.js`)
    for static asset caching and offline page access.
  + LocalStorage data synchronization in `public/dashboard.html` and
    `public/admin.html` so users can view cached repair orders while offline
    and automatically resync when connectivity is restored.
