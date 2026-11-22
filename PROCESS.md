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

## Tooling I chose

Kept up to date as tools are added, so the setup can be reproduced and revisited later.

| Tool | Where | Why |
| --- | --- | --- |
| PHP 8.4 (via Chocolatey) + Composer (Windows installer, `.exe`) | Local | The native setup the README specifies, matching CI. Not my preference: I'd use Docker (see entry 4), but I'm keeping to the setup the codebase defines. |
| PCOV | Local + CI | Coverage driver. The same driver on both sides, so coverage numbers mean the same thing. |
| PHPUnit 12, Pint, Larastan (level 5) | Local + CI | Already in the repo. Unchanged. |
| Node + npm | Local + CI | Front-end build. CI uses Node 20; locally I have Node 24 (see the parity gap in entry 4). |
| Claude Code | Local | AI pair. Rules in [CLAUDE.md](CLAUDE.md). |
| Claude Code output style "KLANG" | Local | [.claude/output-styles/klang.md](.claude/output-styles/klang.md): how the AI talks to me (action first, plain English to ASD-STE100, always flag guesses and unrequested changes). Affects replies only, not the code. |
| Claude Code hooks (Node script) | Local | [.claude/hooks/quality-gate.mjs](.claude/hooks/quality-gate.mjs) blocks pushes and PRs until the CI checks pass locally. |
| git worktrees | Local | One isolated checkout per task. |
| GitHub CLI (`gh`) | Local | PRs against my own fork. |
| GitHub Actions | CI | Existing pipeline; I added a coverage threshold. |
| Docker Desktop | Local | Used once, for the baseline in entry 4, before I had PHP. Not part of the repo. |
| Laravel Boost | Local | Planned: MCP server and guidelines so the AI checks real docs and schema. |

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
| Checks | PHPUnit 12, Pint, Larastan level 5 (empty baseline), `npm run build`; all four run in CI (`ci.yml`). *Correction, found in entry 4: there are four more workflow files I missed here.* |
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

- My personal output-style file (`.claude/output-styles/`) was left out at first, because it's about how I like the AI to talk to me, not about the project. *Later added for visibility (after entry 5):* it shows how I tune the AI's replies for me (action first, short plain English to ASD-STE100, honesty rules), separately from the project rules in `CLAUDE.md`.
- **Checks:** not run locally. This machine has no PHP or Composer yet (`php: command not found`). The change is docs-only, so nothing PHP can break. CI runs on the PR (Task 2).

**Reviewed:** *pending*

### 4. Task 2: quality baseline and a local gate

**Branch:** `claude/quality-gate` (worktree, from `claude/initial`) · **Model:** Opus 5.5 (main session)

**Goal:** before any feature code, prove the starting point is green, set a coverage benchmark, and make it impossible to push work that fails the checks. No application code changes.

**Baseline on the untouched code.** I ran it in Docker, because this machine had no PHP yet:

| Check | Result |
| --- | --- |
| Pint | Pass (40 files) |
| PHPStan level 5 | No errors |
| PHPUnit | 16 passed, 117 assertions |
| Coverage (PCOV) | **93.2%** lines. Only `Enums/ListingStatus` and `Models/Branch` show 0%. |
| `npm run build` | Pass |

**What I added:**
- **Coverage threshold in CI:** `php artisan test --coverage --min=90`, measured with PCOV. 90% gives a small buffer under today's 93.2%, so new work has to arrive with tests. The threshold lives only in `ci.yml`.
- **Local quality gate:** a Claude Code `PreToolUse` hook. Before any `git push` or `gh pr create|merge|ready`, it runs the same checks as CI (Composer install, Pint, PHPStan, tests with coverage, `npm ci`, build) and blocks the command if any fail. I don't rely on the AI remembering to run checks, and CI minutes only go on work that already passes locally. The hook reads the threshold and PHP version from `ci.yml`, so CI stays the single source of truth.
- **Machine parity:** the hook also refuses to run unless local PHP is the same minor version as CI and PCOV is loaded. A local pass on a different PHP version or coverage driver doesn't prove the same thing as a CI pass.

**What I tried, and reversed.** My first approach was Laravel Sail. When I reviewed it against the brief ("work with the codebase, not against it"), it didn't fit:
- It added a dev dependency and `compose.yaml` that the repo doesn't use.
- `sail:install` silently rewrote `phpunit.xml`, replacing the in-memory SQLite test database with a MySQL-style `testing` database. That's the repo's test setup changing under me, which I caught in the diff and reverted.

So I removed Sail and kept the README's native PHP setup. On this Windows machine that means PHP 8.4 installed through Chocolatey and Composer through its Windows installer (`.exe`). The repo's diff is now only `.claude/`, `CLAUDE.md`, `PROCESS.md` and one CI line.

To be clear: **I would have taken the Docker approach.** I'm running native PHP only because the brief asks me to work with the codebase as it's defined, and the README defines a native setup.

**How I'd do it in a real project: Sail through Docker.** I'd propose this to the team rather than add it alone in an exercise:
- **The same platform on every machine.** Native PHP on Windows means Chocolatey or manual installs, separate Composer, and hand-installed extensions like PCOV. Each one is a maintenance point and a way for machines to drift.
- **Parity built in.** A container pins the PHP version, extensions and Node version for everyone, locally and in CI. Today's native setup already has a gap: CI runs Node 20 and I have Node 24. The hook can check PHP and PCOV, but a container removes the whole class of problem.
- **The path to deployment.** The same image can be pushed to AWS ECR and run on Lambda (container images) or ECS, so dev and prod run the same thing.

**What I'd add next: CVE / vulnerability checks.** Noted, not built, to stay inside the time-box. A remote code execution flaw was recently reported in the PHP version line used here. That's a reminder that a green test suite says nothing about the runtime or the dependencies being safe. I'd add:
- **`composer audit`** and **`npm audit --audit-level=high`** as gate steps, both locally (hook) and in CI. Both are built into tools the repo already uses, so no new dependencies.
- **A PHP runtime check.** Fail when the local or CI PHP patch version has a known security advisory. Pin CI to a patched version instead of a floating `8.4`.
- **Dependabot (or Renovate) security updates** on the fork, so advisories turn into PRs instead of waiting for someone to notice.
- In the Docker approach I'd prefer, an **image scan** (for example Trivy or ECR's built-in scanning) before deploy.

**Local setup.** Chocolatey first installed PHP 8.6, which doesn't match CI (8.4), and no Windows PCOV build exists for 8.6. For parity I downgraded to PHP 8.4.28 and installed the official PCOV 1.0.12 DLL for 8.4 (NTS, x64). I enabled `pdo_sqlite`, `sqlite3`, `fileinfo`, `zip`, `mbstring`, `openssl`, `curl` and `pcov` in `php.ini`, and kept a backup.

**Checks (native PHP 8.4.28 + PCOV):**
- The gate passed end to end in about 25 seconds: Composer install, Pint, PHPStan, 16 tests, **93.2% coverage** (identical to the Docker baseline, so parity holds), `npm ci`, build.
- Threshold proven: `--min=99` fails with "Code coverage below expected".

**Hook bugs found while testing, and fixed:**
- **It failed open on a crash.** Claude Code treats any exit code other than 2 as "allow", so an exception in the hook would have let a push through. It now traps every crash and exits 2. I tested this with a bad directory and bad input.
- The Composer step was missing the word `composer`.
- `vendor/bin/pint` doesn't run under Windows `cmd`. Changed to `php vendor/bin/pint`, which works on every OS.

**Known gaps:**
- PHP 8.6 is still first on the machine PATH, so a plain `php` resolves to 8.6 until the PATH is fixed. The hook catches this and blocks.
- CI uses Node 20, local is Node 24.

**CI result** ([draft PR #1](https://github.com/kernig-adam/listings-exercise/pull/1), opened only to run CI): all green. The `ci.yml` job measured **93.2%** with PCOV 1.0.12, the same driver and the same number as local, so the 90% default holds.

**Something my assessment missed.** In entry 1 I listed only `ci.yml`, but the PR also ran checks from four other workflows. They look like leftovers from the Laravel skeleton:
- `tests.yml` runs the suite again on PHP 8.4 and 8.5 on **every push to any branch**, on every PR, and on a schedule. That duplicates `ci.yml` and roughly doubles CI usage.
- `issues.yml`, `pull-requests.yml` and `update-changelog.yml` call shared workflows from the `laravel/.github` org. They're meant for the `laravel/laravel` repo, not this app.

I haven't changed them. The brief says to flag a pattern I think is wrong rather than quietly work around it. My recommendation: delete all four and keep `ci.yml` as the single pipeline, adding a PHP version matrix there if we want 8.5 too. That would be its own small task once agreed.

**Reviewed:** *pending*

### 5. Task 3: one PR process, with human attestation

**Branch:** `claude/pr-process` (worktree, from `claude/quality-gate`; merges back into it) · **Model:** Opus 5.5 (main session)

**Goal:** every PR reads the same way, AI-written work is visibly labelled, and nothing merges until a human has explicitly accepted it.

**What I added:**
- **PR template** (`.github/pull_request_template.md`) with five sections: **Task** (what and why), **Objective** (the outcome), **Testing** (what's tested, with real results), **Notes** (screenshots, trade-offs, gaps) and **Attestation**: an unticked box saying I'm aware the PR was written with AI and that I've reviewed and approved it.
- **Rules for Claude** in `CLAUDE.md`: fill in every section, never claim a result that didn't run, add the `claude` label, and **never tick the attestation box** or merge.
- **`claude` label** on the repo, so AI-written PRs can be filtered at a glance.
- **`Attestation` workflow:** fails the PR until the box is ticked. It re-runs whenever the description is edited, so ticking the box is enough to turn it green. The description is passed in as an environment variable, never pasted into the script, because PR text is user input and could otherwise inject shell commands. It runs with no token permissions.
- **Branch ruleset:** `main` and `feature/**` require the `attestation` and `test` (CI) checks before anything merges, with no bypass, so it applies to me as admin too. Attestation is now a branch rule, not a convention.

**Why a status check and not just the template:** a checkbox on its own is a convention, and anyone (or any tool) can merge past it. Making it a required check means GitHub enforces it.

**Checks:**
- The local gate passed before the push.
- On PR #1 (rewritten in the new template, labelled `claude`, box unticked): `test` passed, `attestation` failed, and GitHub reports the merge as **BLOCKED**. Ticking the box is the only way to unblock it.

**Reviewed:** *pending*

### 6. Claude's commits are prefixed `CLAUDE - `

**Branch:** `claude/quality-gate` · **Model:** Opus 5.5 (main session)

Every commit Claude makes now starts with `CLAUDE - `, so any commit I make in between stands out in `git log`. This sits alongside the `claude/` branch prefix and the `claude` PR label.

- **Enforced, not just a rule:** the same hook now blocks any `git commit` Claude runs without the prefix. The hook only sees Claude's commands, so my own commits are unaffected.
- Commits made before this rule (entries 3–5) don't have the prefix. I can tell them apart by their `Co-Authored-By: Claude` trailer.

**Reviewed:** *pending*

### 7. The PR template is enforced for the AI, not just suggested

**Branch:** `claude/quality-gate` · **Model:** Opus 5.5 (main session)

**The gap:** GitHub only fills in `.github/pull_request_template.md` in the web UI. When Claude opens a PR with `gh pr create --body ...`, the template is skipped completely, so "remember to use it" depended on memory.

**The fix:** the hook now checks Claude's `gh pr` commands:
- `gh pr create` is blocked unless the body has all five sections (Task, Objective, Testing, Notes, Attestation), the attestation line exactly as worded and unticked, and `--label claude`. It reads `--body-file` too.
- Any `gh pr create` or `gh pr edit` that would tick the attestation box is blocked. Only a human ticks it.

**Checks:** four bad cases were blocked with a clear reason (missing section, no label, box ticked on create, box ticked on edit). A valid PR passed through to the full quality gate, which passed.

**Reviewed:** *pending*

<!-- New entries go below, in order. Each one: branch, what was done and why, checks run and results, what changed after review, then "**Reviewed:** *pending*" for Adam to sign off. -->

