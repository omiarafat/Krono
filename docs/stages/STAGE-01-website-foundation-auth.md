# Stage 1 - Website foundation + Login / Register / Email Verification

**Scope:** project structure, CSS taken from approved Design A mockups, PDO + migrations, auth (register, verify, login, logout), security basics. Dashboard etc. NOT included.

**Files:** website/ (public/, app/, config/, database/, storage/), .gitignore, this report.

**Decisions:** mockup CSS reused for exact design (Tailwind v4 pipeline added later for new UI); verification code 123456 only when APP_ENV=local and MAIL_MODE=fixed; passwords hashed with password_hash; CSRF on all POST; login throttling (5 fails / 15 min per email, 20 per IP); code expiry 10 min, 5 tries, resend every 60 s; all times UTC.

**Tests:** php -l: NOT TESTED. Browser run: NOT TESTED. Migration run: NOT TESTED (no PHP/MySQL in the build environment).

**DB changes:** 001_core_auth.sql (users, email_verification_codes, login_attempts). Rollback: drop those three tables (local only).

**Known gaps:** SMTP mailer (PHPMailer) not implemented; Password Recovery page deferred; Inter font not bundled; Light/Dark follows system (Appearance setting comes later).

**Git:** not committed by the assistant. **Approval:** pending. **Next stage (proposal):** shared layout shell + Dashboard.
