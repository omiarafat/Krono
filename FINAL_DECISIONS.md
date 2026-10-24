# Krono - Final Decisions (v1)

## Stack
- Website: plain PHP + Tailwind v4 (built locally, CSS uploaded), no React. PDF via mPDF/Dompdf.
- Desktop app: Electron + React + Vite + Tailwind v4, Inter bundled locally.
- Local dev: Laragon. Production later: PHP hosting (GitHub Pages is static only: no PHP/MySQL).
- Repos: main code private; separate small public repo for installer releases.
- Email verification: `MAIL_MODE=fixed` (code 123456, local/testing) or `smtp` (PHPMailer, random 6-digit, 10 min expiry, 5 attempts, resend after 60 s). Turn `fixed` off before opening to the public.

## Window and UI
- Top-bar notification bell removed. "Connected" badge: Online / Reconnecting / Offline.
- Custom title bar via `titleBarOverlay` (native buttons, Snap Layouts). Title: `Krono Desktop · {page}`; taskbar title shows timer state.
- Single instance lock; `krono://` protocol for "Open App". Close guard stays as in spec.

## Server is the source of truth
- All times from the server. Heartbeat every 15 s with `last_activity_at`. Offline = 3 failed pings.
- Net work time = Sign In to Sign Out minus breaks only. Offline and idle are recorded and reported, not subtracted (unless inside a break).

## Offline
- Running task timer stops at the moment offline begins; offline time is not added to tasks.
- Add Task, Start, Sign In/Out, Login, Logout, Register: error only.
- Resume while offline: error, click remembered. Auto-starts when back online if online within 30 min of the last Resume click (first 30 min from offline start also covered). Not auto-started if break running, signed out, app closed, PC slept, or another timer running.
- Stop while offline: description form opens, Save fails, draft kept; end time = moment offline began; saved when online.
- Break Start/End offline: drafts. Start = moment offline began; End = moment back online.
- Sync order: offline interval, break drafts, stop drafts, remembered Resume.

## Idle (mouse/keyboard, system-wide, no keys or screen captured)
- 10 min no activity: warning (modal + Windows toast + taskbar flash). The Krono window is brought to the front and is temporarily always-on-top ONLY during the warning, so Focus Assist / Do Not Disturb cannot hide it.
- 12 min: all running timers stop, set to the last activity moment; user resumes manually.
- No warning during a break or when no timer is running. Activity within 10-12 min cancels the warning.

## Day change and drafts
- "Day" = workspace timezone (`Asia/Dhaka`), server side.
- Paused/stopped tasks left over to the next day become drafts: tasks with time show only **Save** (opens description form); tasks with 00:00:00 are removed automatically. No Start/Resume/Remove.
- Add Task blocked until drafts are saved. Timers running with activity across midnight keep running.
- Yesterday's open Sign In is closed automatically by the server at the last activity time (also closes an open break); marked "auto-closed". Done on next request; optional cron later.

## Design additions to mock up before coding
1. Idle warning modal  2. "You were idle X to Y" message  3. Draft Save button + "Resumes when online" label
4. Attendance "Offline time" row  5. Website "Offline Summary"  6. "Yesterday's session auto-closed" message  7. Privacy notice (only checks that mouse/keyboard was used)
