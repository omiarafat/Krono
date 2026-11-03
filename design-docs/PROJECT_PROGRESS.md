# PROJECT_PROGRESS.md — what is done, what is next

> **Snapshot date:** 2026-11-02 · **Read `AI_RULES.md` first.** Before starting work: inspect the real repository, **verify** every "Done (reported)" line below, update the status, then continue from **Next up** (section 5). Do not redo what is *Done (verified)*.

**Status legend**
`[x] DONE-VERIFIED` checked with evidence · `[x] DONE-REPORTED` finished according to an earlier session, **not yet verified** · `[~] PARTIAL` · `[ ] TODO` · `[!] DECISION NEEDED` · `[-] BROKEN`


## 0. Reconcile update — 2026-11-02 (website code state, verified on a test server; MySQL not available there, SQLite shim used)
Owner decisions since this file was written (they win over older text below):
- **Repositories:** the desktop app now lives in its **own repo (`krono-desktop`)**; the website repo is **flat** (`app/`, `public/`, `database/`, `resources/` at the repo root; no `website/` folder). The app talks to the website only through the API (`/api/...`) and the `krono://auth?code=` link.
- **Database:** ONE file `database/schema.sql` (all `CREATE TABLE IF NOT EXISTS`, never drops/changes data) run by `php database/migrate.php`. New table = append to that file. (Replaces "numbered migration files".)
- **Installer:** not built yet (owner will finish more app design first). Hosting = GitHub Releases `Krono-Setup.exe`; the Dashboard button reads `APP_DOWNLOAD_URL`.
- Idle timing currently kept at 3 min warning / 5 min stop (owner's choice).

Done (verified 2026-11-02, test server, Light + Dark):
- [x] DONE-VERIFIED Register -> Verify -> Login on website and desktop app against ONE `users` table (unique email); token API + one-time `krono://` / web hand-off codes (auto-login both ways).
- [x] DONE-VERIFIED Stage 2 app shell: sticky topbar, collapsible sidebar (icon mode + tooltips), working links (other pages show a "Coming soon" shell), theme from `localStorage`.
- [x] DONE-VERIFIED Stage 2 Dashboard: 4 stat cards, weekly chart (This/Last week), project progress, recent time logs, empty states, "Download & Install App / Open Krono Desktop" button. Data from DB (own projects, own saved time, workspace timezone Asia/Dhaka).
- [x] DONE-VERIFIED Tables exist in `schema.sql`: users, email_verification_codes, login_attempts, api_tokens, login_handoffs, projects, project_members, tasks, task_time_logs, attendance_sessions, attendance_breaks.
- [~] PARTIAL Dashboard vs `01-Dashboard/Dashboard.md`: still missing — "Open tasks" should count tasks **assigned to the user** (currently all tasks of the user's projects); rows linking to Project/Task Details; "Create your first project" link in the empty state; loading skeletons; manager scope (needs Teams stage).
- [x] FIXED (2026-11-03) Tailwind rule "no square-bracket arbitrary values" (`docs/TAILWIND_V4_CSS_RULES.md` section 5): the shell/dashboard views written so far still contain `text-[11px]`, `rounded-[17px]` etc. -> add the tokens (`text-tiny`, `rounded-card`, `w-sidebar` ...) and replace.
- [x] DONE (2026-11-03, tested on SQLite shim; MySQL migration not yet run by owner) Workspaces + roles tables, personal workspace on register, `Workspace` helper, Dashboard scoped to workspace, sidebar renamed Teams, Break display rule. (was: DECISION NEEDED) The package assumes **workspaces and roles** (Owner/Admin/Manager/Member/Viewer, direct managers). The database has no `workspaces` / `workspace_members` yet. Proposal: add them to `schema.sql` before Projects and put `workspace_id` on projects.
- [ ] TODO Remove `storage/logs/*.log` from the GitHub repo and add `storage/logs/*` to `.gitignore`.

## 1. Design & planning — complete (verified when this package was assembled)
- [x] DONE-VERIFIED All website pages designed in **Light + Dark** (Design A, 15 pages) — see `docs/PAGE_INVENTORY.md`.
- [x] DONE-VERIFIED Approved & locked: Project Details, Task Details, Attendance Details.
- [x] DONE-VERIFIED Final versions chosen (add-on wins over Design A): **Reports + PDF builder** (4 layouts), **Teams**, **Workspace Settings (5 tabs)**, Popup & Message system (6 boards, copied into each page's `popups/`).
- [x] DONE-VERIFIED One folder per page with `<Page>.md` (design + dynamic plan + popups/messages + acceptance checklist), `mockups/`, `source/`, `popups/`.
- [x] DONE-VERIFIED Master docs: locked decisions, master design/dynamic, design tokens, page inventory, desktop-app relationship spec, Tailwind v4 rules, AI rules.
- [~] PARTIAL Designs created in the reorganisation, **awaiting owner approval**: Password Recovery (5 screens), Error & Empty States (5 screens), Calendar task-preview popover.
- [!] DECISION NEEDED Open questions in `docs/DECISIONS_AND_CHANGES.md` §4: sidebar label *Teams* vs *Team & Roles* (mockups still differ), Roles tab inside Teams vs Workspace Settings, source of *Work Location*, break-minute rounding policy, Calendar Month/Day + Task Board views (no mockups), password-reset link vs code, delete-vs-archive and project-completion rules.
- [ ] TODO (design, later) Desktop app screens — intentionally **not** designed; deferred until the website is finished and approved.

## 2. Website code — reported by the 2026-10-27 session (`Krono-project-v2.zip`) — verify all
Repository: `E:\Makehub\Krono` (`website/` PHP, `app/` desktop app).
- [x] DONE-REPORTED Repository cleanup: empty root `package-lock.json` removed, nested `New folder/` and old zip removed, `*.zip` added to `.gitignore`, root `README.md` written, `FINAL_DECISIONS.md` present, `website/.env.example` restored.
- [x] DONE-REPORTED Tailwind v4 on the website via the **Tailwind CLI** (PHP cannot run the Vite dev server): source in `website/resources/css/` (`site.css`, `components.css`, `fonts.css`), built file `public/assets/css/site.css` committed so hosting needs no Node. **Preflight disabled**; the approved base CSS sits in the `components` layer; tokens reported: `bg-page`, `bg-surface`, `text-ink`, `text-muted`, `border-line`, `bg-brand`, `bg-soft`, `text-danger`, `shadow-card`.
- [x] DONE-REPORTED Inter font bundled locally (5 weights in `public/assets/fonts/`, `@font-face`), no Google Fonts.
- [x] DONE-REPORTED Web views converted to Tailwind classes: layout, Login, Register, Verify email, temporary Dashboard, error page.
- [x] DONE-REPORTED Migration `database/migrate.php` + `001_core_auth.sql` (tables `users`, `email_verification_codes`, `login_attempts`, `schema_migrations`).
- [~] PARTIAL `package.json` scripts: `css` and `css:watch` exist; a `dev` script (same as watch) was recommended — **not confirmed applied**.
- [ ] TODO Confirm the migration was actually run on the local `krono` database (an error `Table 'krono.login_attempts' doesn't exist` was seen before it ran).
- [ ] TODO Confirm **Register → Verify → Login** works end to end (owner's test result was not reported); check Light + Dark of Login/Register/Verify against the mockups (owner was asked to compare).
- [ ] TODO Audit existing CSS/views against `docs/TAILWIND_V4_CSS_RULES.md` (no `[]` arbitrary values, tokens extended per the rules, components named per the mockup class map).

## 3. Desktop app code — reported by the same session — verify
- [x] DONE-REPORTED App structure with Tailwind v4 via `@tailwindcss/vite`, new `src/index.css` imported by `main.jsx`; build verified by that session (the app itself was not run there).
- [x] DONE-REPORTED Inter via `@fontsource/inter` (weights incl. 800); `build/icon.png` added to packaged files; unused `ui.css`/`theme.css` removed.
- [x] DONE-REPORTED Idle timing fixed: `IDLE_STOP_S` 300 (was 120, below `IDLE_WARNING_S` 180); owner's own edits (`TOAST_SECONDS: 10`, larger `app.css`) kept. **Final values to be set later: stop 600 / warning 720 (confirm).**
- [ ] TODO App pages (`pages.jsx`, `App.jsx`) still use the mockup's CSS classes → convert to Tailwind v4 + components, page by page (**after** the website is complete, unless the owner says otherwise).
- [ ] TODO Run and verify the app on Windows (PHP/Electron were not runnable in the earlier session).

## 4. Backend API — not started
- [ ] TODO `/api/v1/ping`, token login (`POST /api/v1/login`, hashed tokens with expiry/revocation), `/api/v1/me`, `/api/v1/time` (server time + workspace timezone; app polls ~15 s), project/task pickers, attendance + timer + time-log endpoints with idempotency keys. Server owns all time and rules (one active session, one timer, no Sign Out during a break, work description required on Stop). Test the hosting early: some free hosts block app-originated calls.

## 5. Ordered plan — **Next up** (first unfinished stage first; one stage at a time; owner approves each)
> The order below follows the locked rule *website complete → desktop app last*. An earlier session proposed *Dashboard → first API part → app*; the owner has **not** confirmed it. Ask before reordering.

**Stage 0 — Reconcile (always first):** verify sections 2–3, update statuses, run the migration + auth test, report to the owner.

**Stage 1 — Foundation & Auth (finish).** Docs: `00-Auth/*/…md`, `docs/TAILWIND_V4_CSS_RULES.md`.
- [ ] Tokens + components set up per Tailwind rules (shell, button, card, field, badge, toast, modal basics).
- [ ] Login, Register, Email Verification complete incl. rate limits, resend cool-down, one-time expiring hashed codes, dev-only fixed code flag, logout, session rotation, CSRF.
- [ ] Password Recovery built (after the owner approves the new design).
- [ ] Basic roles/permission helper (Owner/Admin/Manager/Member/Viewer) usable by every later page.

**Stage 2 — App shell + Dashboard.** Docs: `01-Dashboard/Dashboard.md`. Sidebar/topbar, theme switch (Light/Dark/System), dashboard cards, weekly chart, project progress, recent time logs (empty states until data exists).

**Stage 3 — Projects (+ Project Details).** `02-Projects/`. Tables, list + filters, create/edit/archive modals, details page, activity feed, permissions.

**Stage 4 — Tasks (+ Task Details).** `03-Tasks/`. Tasks list (+ board if approved), modals, status/priority rules, details page.

**Stage 5 — Time Tracking + Attendance (+ Details, Desktop Empty State).** `04-Time-Tracking/`, `05-Attendance/`. Read-only pages over saved logs/sessions; tables + write endpoints for the app's data; net-time calculation and rounding policy (needs owner decision).

**Stage 6 — Reports + PDF Report Builder.** `06-Reports/`. Live filters, summary/charts, 10-row table, Generate Report popup, 4 server-side PDF layouts, export permission + audit.

**Stage 7 — Calendar.** `07-Calendar/`. Week/Month/Day views, task preview popover.

**Stage 8 — Teams + Settings.** `08-Teams/`, `09-Settings/`. Invitations, roles, direct-manager scope, deactivation guards, Workspace Settings (5 tabs), Profile, Appearance & Notifications, Audit Log.

**Stage 9 — Website QA & approval.** Error & Empty States everywhere, accessibility, performance, security review, full Light/Dark/responsive pass; owner signs off.

**Stage 10 — API for the app + Windows app.** `11-Desktop-App-Spec/`, `docs/KRONO_DESKTOP_APP_SPEC.md`. API v1, convert app pages to Tailwind, attendance + timers, installer/updater decisions (Electron vs final packaging, autostart, offline) discussed first.

## 6. Instructions for the next AI session (copy this mindset)
1. Read `AI_RULES.md` → this file → locked decisions.
2. Run Stage 0. Report in Bengali: verified / broken / not verified.
3. Work the first unchecked item of the first unfinished stage; do not touch Done-verified work.
4. After each stage: tests, stage report in `docs/stage-reports/`, update this file, then wait for approval.

## 7. Change log
- 2026-11-02 — File created from the design-package reorganisation and the 2026-10-27 code session notes; all code items marked *reported*, none verified yet.
- 2026-11-02 — Reconcile update: website auth, shell and Dashboard verified; repo split (app separate, website flat); single `schema.sql`; open items listed in section 0.
- 2026-11-03 — Workspaces/roles added, Teams label, Break rule (2m 40s per break, rounded total), Dashboard "Open tasks" = assigned to me, Tailwind tokens (no brackets).
