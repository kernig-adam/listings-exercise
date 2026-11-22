# Process Log

This file shows how I worked through the exercise: what I looked at, what I decided, and why. It runs in time order. [NOTES.md](NOTES.md) is the short summary of the final decisions. This file is the working behind it.

> **How this file is written.** Claude Code writes each entry in my voice **while the work is happening**, as one of the steps of each task. It is not reconstructed afterwards from a session transcript or an export of the AI conversation. I read every entry, correct anything that's wrong, and sign it off with the **Reviewed** line at the end of the entry. An entry without a sign-off has not been reviewed yet. Reviewing this log is part of how I review the AI's work.

## How I work with AI on this

- **Rules first.** [CLAUDE.md](CLAUDE.md) sets the expectations for Claude Code before any code is written: follow the existing patterns, pass the CI checks, write tests first, no gold-plating.
- **Small tasks, each isolated.** Each task runs in its own git worktree on its own branch, then merges into the feature branch. This keeps each change small enough to review properly.
- **The model fits the task.** Opus for design and review, Sonnet for implementation, Haiku for mechanical work. Each task in the plan names its model.
- **AI work is labelled.** Branches created and pushed by Claude through automation are prefixed `claude/`, so the history shows which work was automated. Branches without the prefix are mine.
- **I review everything.** Each entry below notes what I checked and what I changed.

## Branches

| Branch | Owner | Purpose |
| --- | --- | --- |
| `main` | upstream | The exercise as given |
| `initial` | me | Setup: assessment, documentation, tooling |
| `feature/saved-search-alerts` | me | Integration branch for the feature; the PR comes from here |
| `claude/<task>` | Claude (automated) | One per task, merged into the feature branch |

---

## Log

### 1. Assessment: what's here and where

**Branch:** `initial` (from `main` @ `f5de7e9`)

Before planning anything, I read the brief and the codebase so the feature fits what's already there.

**Read:** [TASK.md](TASK.md), [README.md](README.md), every file under `app/`, `routes/`, `database/`, `tests/`, the CI workflow and the PHPStan config.

**What's used, and where:**

| Area | What's there |
| --- | --- |
| Stack | Laravel 13, PHP 8.4, SQLite, Inertia v3, Vue 3.5, Tailwind v4, Vite |
| Checks | PHPUnit 12, Pint, Larastan level 5 (empty baseline), `npm run build`; all four run in CI |
| Domain | `Branch` (name, region) has many `Listing`; `ListingStatus` and `PropertyType` are backed enums |
| HTTP | `ListingController` → `ListingIndexRequest` (validation) → `ListingResource` (props) → `Inertia::render` |
| Front end | `resources/js/pages/Listings/{Index,Show}.vue`; filters stay in the query string |
| Auth | Stubbed by `ActAsDemoUser`; every request is the seeded demo user |
| Tests | Feature tests with `RefreshDatabase`; factories have states (`live()`, `sold()`, `underOffer()`) |

**Observations that will shape the design** (open questions, not decided yet):

- `ListingController::index` already filters by `property_type`, `max_price`, `min_bedrooms` and `region`. These are the criteria the brief suggests. Saved-search matching should reuse this filtering, not copy it.
- `region` lives on `Branch`, not `Listing`. Matching on region means going through the branch.
- "Becomes live" is just a change to `status`. Nothing fires an event when it happens, so I need to decide how a listing going live triggers alerts.
- `listed_at` can be null, so I can't rely on it alone to mean "new".

**Quality baseline:** *pending.* The README says tests, Pint and PHPStan all pass on a fresh clone. I'll run all four checks before changing any code, so later failures are known to be ours.

**Reviewed:** *pending*

### 2. Expectations for the AI

**Branch:** `initial`

I wrote [CLAUDE.md](CLAUDE.md) so every session and every worktree starts from the same rules:

- A table of the existing patterns to copy (Form Requests, Resources, enums, scopes, factory states, test style).
- The quality gate: the same four checks CI runs. A task isn't done until they pass and the output has been shown.
- The worktree workflow and the `claude/` branch prefix.
- Model choice per task.
- Laravel Boost (MCP server and guidelines) planned, so the AI checks real docs and the real schema instead of guessing.
- This log, written live by the AI and reviewed by me.

**Reviewed:** *pending*

### 3. Task 1: commit the setup documents

**Branch:** `claude/initial` (from `initial`) · **Model:** Opus 5.5 (main session)

I committed the documents from entries 1–2 (`CLAUDE.md`, `PROCESS.md`, and `.gitignore` with `/.worktrees` added) and pushed them, so all later work builds on a recorded starting point. No application code changed.

- My personal output-style file (`.claude/output-styles/`) is left out on purpose. It's about how I like the AI to talk to me, not about the project.
- **Checks:** not run locally. This machine has no PHP or Composer yet (`php: command not found`). The change is docs-only, so nothing PHP can break. CI runs on the PR (Task 2).

**Reviewed:** *pending*

<!-- New entries go below, in order. Each one: branch, what was done and why, checks run and results, what changed after review, then "**Reviewed:** *pending*" for Adam to sign off. -->

