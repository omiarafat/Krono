# Krono — Locked Decisions Log

This file is the source of truth for approved business/UI rules. If any other document conflicts with it, follow this file and flag the discrepancy rather than silently choosing.

## Website vs Desktop App
- **Website = management and reporting center.** It owns account/workspace/project/task/member/settings management, saved attendance/time history, filters, reports and custom PDF generation.
- **Windows Desktop App (`.exe`) = the only live control point** for Attendance (Sign In, Sign Out, Start Break, End Break) and task timer control (Start, Pause, Resume, Stop, Remove).
- Website Attendance and Time Tracking pages remain. They are read-only for operational controls and show histories/details/filters/reports. When no records exist, show a useful empty state with Download for Windows and Open Krono Desktop CTAs.
- Same account, authentication and verification system for website and desktop app; shared API/backend/database, no second source of truth.
- No browser extension in the current scope.

## Brand and UI
- Green-family colors + neutral surfaces only. Blue and purple are removed from the brand/UI palette.
- Core colors: Mint `#34D399`, Emerald `#059669`, strong Emerald `#047857`, Forest `#166534`, Sage `#A7C4A0`, Pale Green `#ECFDF5`, Warm White `#F8FAF8`, Deep Forest `#0B1711`, Dark Surface `#14251B`, dark text `#F0FDF4`.
- Gradient usage must be rare, muted and low-opacity. It may be used sparingly in the logo/small accent. No default gradient-filled buttons or full-page gradient washes.
- Tailwind CSS v4 with named tokens; each page's `DESIGN.md` documents exact token/asset use.
- Light/Dark mode across website; login/register/verification visual language should match on website and desktop.

## Git, documentation and approval
- Use Git + Private GitHub + per-stage Markdown report for day-to-day backups. ZIP per stage is not required; create ZIPs only for requested final/release handoff.
- Main website page designs first (two alternatives, each per-page, 1440px, Light+Dark); user review and fix requests; then detail/subpages design with approval between stages.
- Once all website designs are approved, implement dynamic/backend features page/stage by page/stage. Run tests and fix bugs before next stage; wait for explicit approval.
- Approved designs/working features are locked. Do not modify without explicit user request.
- Windows `.exe` work starts only after website design and dynamic implementation are completed and approved.

## Authentication
- Same account can log in from website and desktop app.
- New account workflow: Register → email verification → Login.
- A fixed verification code is allowed only for local development if explicitly configured; never in production. Production verification must use expiring, one-time codes/tokens, attempt limits and resend throttling.

## Attendance
- Sign In is required before starting or resuming any task timer.
- One active attendance session per user enforced server-side/database-side.
- One active break per attendance session. Multiple sequential breaks are allowed and each has start, end, duration, numbered in Attendance Details.
- Starting a break automatically pauses a running task timer.
- Ending a break does not automatically resume a task; ask **Resume Previous Task** or **Keep Paused**.
- Sign Out while a break is active automatically ends the break at exactly the same server timestamp as Sign Out.
- If an unsaved task session exists at Sign Out, show Save & Sign Out / Discard & Sign Out / Cancel. Discard affects only the current unsaved session; saved logs remain. Cancel leaves user signed in and does not end break.
- Net work time = attendance span - sum of completed break durations; handle timezone, overnight dates and incomplete sessions explicitly.

## Task tracking
- Multiple incomplete project tasks may be added to the desktop tracking list. Project selection controls which tasks are available; task picker uses real database records and hides tasks whose task status is Completed.
- At most one task timer can run per user, enforced on the server (not just client-side).
- Start when no other timer runs: start selected task, provided user is signed in and not on break.
- Start/Resume another task while a different task is running opens a conflict dialog: **Pause**, **Stop**, **Close**.
  - Pause: pause the currently running task, then start/resume the selected task.
  - Stop: open work-description form for the currently running task; only after successful Save should it be removed from the tracking list and the selected task start/resume.
  - Close: cancel the switch and leave the current task running.
- Pause retains an unsaved session so it can be resumed later.
- Stop ends only that tracking session and saves a time log with user/task/project, start/end timestamps, duration and work description. It does not change the source task status to Completed.
- After Stop+successful save, remove that task from the desktop tracking list automatically; do not delete the actual project/task in the website/database.
- Remove of an unstarted item removes it immediately from the tracking list only. It never deletes a project/task from the website.
- Remove of a running/paused item with unsaved time requires Save & Remove / Discard & Remove / Cancel. No silent loss. Saving/removing only removes the tracking queue item; source project/task stays intact.
- Save failures must keep the pending session and must not auto-start the next task.

## Desktop window/minimize/exit
- Ordinary Windows application window like user's reference image; no floating mini timer and no always-on-top overlay.
- Sidebar: Dashboard, Attendance, Time Tracking, View Website. Show signed-in email/account and Logout.
- Minimize (`-`): minimize window but keep process/session alive; any authorized timer remains correctly running. Elapsed time is derived from authoritative timestamps/server state, not dependent on a UI interval staying active.
- Close (`X`) is guarded: if a task session has unsaved/unsubmitted time, do not exit until it is submitted/saved (offer a path to complete save and stay in app). If signed in or a break is active, do not exit until Sign Out is completed. If break is active during Sign Out, close it at the Sign Out timestamp. If any operation fails, remain open and preserve pending state.
- Once signed out and no unsaved tracking session remains, normal exit is allowed. Logout and Sign Out are distinct: logging out must not silently discard an active attendance or unsaved time session.

## Reports
- Custom PDF report uses user-selected filter data, branded header/footer, tables, summary and page numbers. PDF source/template and control belong to the website. Export must match filters and permissions.

## Still deferred for later desktop discussion
- Electron vs Tauri choice, installer/update strategy, launch-at-startup default, offline behavior, app auto-update, shortcut settings, and final `.exe` visual mockups. These must be discussed before desktop implementation, after website phases.

## Break time display (decided 2026-11-03)
- Every single break is shown with minutes and seconds, e.g. `2m 40s` (all mockups' sample data must follow this).
- A TOTAL break time (sum of breaks) is calculated from seconds and then rounded to the nearest minute, e.g. `24m` or `1h 05m`.
- Applies on every page and in the desktop app. Net work time is always calculated from exact seconds.

## Sidebar label (decided 2026-11-03)
- The sidebar item is **Teams** (not "Team & Roles").

## Workspace and roles (decided 2026-11-03)
- Every account has a workspace; every project belongs to a workspace. One role per member: owner > admin > manager > member > viewer. Tables `workspaces`, `workspace_members` (with `manager_id` for direct reports).
