# Krono website (plain PHP + MySQL)

## Run locally (Laragon)
1. Put this `website` folder at `C:\laragon\www\krono\website` (or any folder under `www`).
2. Copy `.env.example` to `.env`, then edit: `APP_URL` (must match your folder, e.g. `http://localhost/krono/website/public`) and `APP_KEY` (any long random text).
3. Start Laragon (Apache + MySQL). In its terminal: `cd website` then `php database/migrate.php` (creates the `krono` database and tables).
4. Open `APP_URL` in the browser. Register, then verify with code `123456` (local only).

## Rules
- Never commit `.env`. `MAIL_MODE=fixed` works only when `APP_ENV=local`.
- Add new database changes as new files in `database/migrations/` (never edit applied ones).
