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
| ~~Laravel Boost~~ | — | Dropped (entry 8). I'd use it on a real project, but it adds a dependency this codebase doesn't have. |
| Context7 (MCP) | Local | Current library docs for the AI, in place of Boost's doc search. Not part of the repo. |

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

**What I'd add next: coverage reports on the PR.** Noted, not built. Today CI enforces the 90% floor and prints the coverage table in the job log, but a reviewer has to open the log to see it. I'd like the coverage report visible on the PR itself:
- **Job summary (no new dependencies):** write PHPUnit's text coverage to `$GITHUB_STEP_SUMMARY`, so it shows on the PR's checks page.
- **A PR comment with the change in coverage:** total coverage against the base branch, plus coverage of the lines the PR changed ("patch coverage"), updated on each push. This needs a Clover report (`--coverage-clover`), `pull-requests: write`, and either a small script or a tool such as Codecov.
- Patch coverage matters more than the total. A PR can keep the total above 90% while leaving its own new code untested.

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

### 8. Clean baseline: feature branch, NOTES.md, Boost dropped

**Branch:** `claude/quality-gate` → PR into `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session)

- **Feature branch:** `feature/saved-search-alerts` is created from an untouched `main`. All the setup (entries 2–8) reaches it through one PR (#1), checked by CI and attested by me. Once that merges, the feature branch is the clean baseline every feature task starts from. The hand-back PR (feature → `main`) then shows setup and feature together.
- **NOTES.md started.** It should have existed from the start, because the brief asks for decisions to be recorded as they're made. It now holds the setup decisions, and the feature decisions will go in as they're made.
- **Laravel Boost dropped.** It was planned (entry 2) but never installed. I'd use it on a real project: its doc search and schema/log tools keep an AI grounded in the real app. Here it adds a dev dependency and generated files the codebase doesn't have, which is the same reason I dropped Sail. CLAUDE.md now tells the AI to check current docs (Context7) and real state (`artisan` commands, logs) instead.

**Reviewed:** *pending*

### 9. Baseline confirmed; one feature branch, no worktrees

**Branch:** `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session)

**Baseline (after PR #1 merged):** the main checkout is on `feature/saved-search-alerts` at the merge commit, and the working tree is clean. Native dependencies are installed and the database is migrated and seeded. The full gate passed: 16 tests, **93.2%** coverage.

**Workflow change, my call:** for the feature itself I'm dropping per-task worktrees and PRs. All work goes straight onto the feature branch in one checkout.
- **Why:** with one person and one feature, a PR and attestation per task is overhead without much extra safety. The hook still gates every push locally, and I attest once, on the hand-back PR to `main`.
- **Ruleset:** narrowed from `main` + `feature/**` to `main` only, so pushes to the feature branch are allowed. `main` still requires a PR, the `attestation` and `test` checks, and allows no force-push or deletion.
- **Trade-off:** individual feature commits aren't attested one by one. The `CLAUDE - ` commit prefix still shows which are AI-written.
- Removed the two merged worktrees (`quality-gate`, `pr-process`). CLAUDE.md is updated to match.

**Reviewed:** *pending*

### 10. Specialist agents, documented and reviewed before any code

**Branch:** `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session); one Sonnet run as the UI agent

I defined four project agents in `.claude/agents/`, each with a named model and only the tools it needs. Then I reviewed and reshaped each one before letting any of them write code.

**What I did, and what I changed after review:**
- **Code first, by mistake, then undone.** The first pass went straight to code: a Sonnet UI agent built a brand theme, and I started adding Vitest, Playwright and axe. I stopped it. I wanted to see and approve the agents' instructions before they produced anything, so I reverted every code change and reinstalled `node_modules` from the committed lockfile. Copies were kept outside the repo for later.
- **Product owner:** rewritten. The first draft was effectively a checker of the brief. A product owner should care about buyers, the problem, outcomes, the smallest valuable slice and trust. It now uses the running site in Chrome as a buyer would, treated like real user monitoring (Core Web Vitals, console errors, failed requests, journey friction).
- **Backend:** added OWASP (broken access control is the top risk: load through the user's relationship, 404 for others' records, tests to prove it), fail fast, validation at every trust boundary, and strong typing. I flagged `strict_types` rather than adding it inconsistently.
- **Data:** added SQLite's quirks (type affinity, dates as text, collation, limited `ALTER TABLE`) and portability rules. I wrote up SQLite against a production engine in NOTES.md. I don't know which engine Street runs, so that's the first thing I'd confirm.
- **UI:** brand from two Street sources (the marketing site for the shell, the dashboard for app content), Street's logo vendored, no glassmorphism (neither source has it, which the UI agent's analysis confirmed), full UX ownership (every state designed, Nielsen's heuristics), and strict Tailwind (tokens only, no inline styles or one-off overrides). I checked the existing code; it already follows the rule.
- **Grilling:** I used Matt Pocock's grilling skill to have the AI question me. My first ask was the product owner's own questions, which exposed that the agent was written as a test admin, not a product owner. That led to the rewrite.

**Checks:** documents only, no code changed. The gate runs on push.

**Reviewed:** *pending*

### 11. Local quality gate removed; AI gates noted for later

**Branch:** `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session)

**What happened:** pushing entry 10 failed. The gate's `npm ci` hit `EPERM` because the running Vite dev server had a native file locked in `node_modules`. The AI first proposed swapping to `npm install`, and I said no: the gate should install exactly as CI does. It then proposed "stop Vite before pushing" and committed that without telling me. I pushed back on both:
- **Not telling me.** I had the commit undone; it was local only. CLAUDE.md now says Claude never commits or pushes without me knowing.
- **Treating the symptom.** Having to stop a dev server is a sign the gate is badly designed, not a runtime problem. The real flaw: the gate ran inside my working checkout, so it fought anything running there, and it checked uncommitted files rather than the commit being pushed.

**Decision:** I removed the push-time quality checks from the hook for now, rather than patch a flawed design. The hook still enforces the `CLAUDE - ` commit prefix and the PR template and attestation rules. CI still runs every check on PRs. The right rebuild is a gate that runs in an isolated checkout of the exact commit being pushed.

**Noted for later (NOTES.md):** Claude gates for when the AI hallucinates, drifts off task, or makes decisions that are mine: scope limits, protected decisions and config, checks for references to things that don't exist, and claims that don't match real results. This session gave a live example: the AI committed a decision I hadn't approved.

**Checks:** none run. This change only removes checks from a hook; the commit-prefix and PR rules are unchanged.

**Reviewed:** *pending*

### 12. Feature design: grilling session (backend)

**Branch:** `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session) · **Skill:** Matt Pocock's `grilling`

Before any feature code, I had the AI grill me on the brief and its optional items, in rounds of numbered questions, each with a recommended answer. Every decision went into NOTES.md as I made it. The result is a backend task ([docs/tasks/01-backend-saved-search-alerts.md](docs/tasks/01-backend-saved-search-alerts.md)) with agents, order, data model, behaviour and acceptance tests.

**Where I overruled the AI's recommendations:**
- **Price and bedrooms as ranges** (it proposed max price and min bedrooms only). Other property sites show a range is key.
- **Several regions and several property types** (it proposed one of each). It was thinking systemically, not commercially.
- **No rejecting regions without current homes** (it proposed failing them). An empty result today is why buyers save a search.
- **Regions as a data-driven multi-select,** like types, so the UI can never offer what the API rejects.
- **A transactional outbox to SNS** (it proposed an after-commit queued job). That takes alert generation off the write path and removes hot-data contention.
- **Duplicate checks in the UI as well as the API,** using data the page already has, to save requests.

**Scale notes I added:** batching and digests for mass delivery; many-to-many interest data for analytics; server-side keys for large saved filters instead of long query strings; other fan-out methods for very large subscription sets; alarming and re-runs for failed messages (documented only).

**Next:** a separate grilling session on UI and UX, because this one was backend-focused.

**Checks:** none. Design and documents only.

**Reviewed:** *pending*

### 13. Feature design: grilling session (UI and UX)

**Branch:** `feature/saved-search-alerts` · **Model:** Opus 5.5 (main session) · **Skill:** `grilling`

A second grilling session on the UI only, because the first one leaned towards the backend. The result is the UI task, [docs/tasks/02-ui-saved-search-alerts.md](docs/tasks/02-ui-saved-search-alerts.md).

**Decisions:** Street's header on every page with a "Welcome back, {name}" greeting; brand tokens on the shell and new pages, with a light swap only on existing pages; pill chips for regions and types; preset range dropdowns from the backend; the existing Search button; "Save this search" with every state designed; inline delete confirmation; alerts on the listing card; a mobile Menu and a Filters panel; "View homes" on saved searches; Vitest, Playwright and axe AA blocking in CI.

**My additions and changes:** the "Welcome back" greeting; an empty search result invites saving (to increase capture); consistent pagination at 15 on every list, rather than the AI's "no pagination on saved searches because of the cap"; and **no Playwright**. The reviewer must be able to get in and run it easily, and Playwright's browser download works against that. Automated accessibility checks move to axe-core inside Vitest, and Playwright is noted as what I'd use on a real project.

**Checks:** none. Design and documents only.

**Reviewed:** *pending*

### 14. Task 1, step 1: product owner review

**Branch:** `feature/saved-search-alerts` · **Agent:** `product-owner` (Opus)

The product owner agent reviewed the backend task against buyer value before any code.

- **Browser baseline not captured:** the Claude in Chrome extension wasn't connected, so the agent stopped after three failed attempts, as its instructions say, and reviewed from the documents and code. I'll capture the RUM baseline before Task 2 changes the pages.
- **Gaps it found, now in the task:** duplicate checks on *normalised* criteria; capturing "unread" before marking read, and marking only the page viewed; honest wording when all of an alert's searches have been deleted; the "View homes" URL from the Resource; friendly cap and rate-limit messages; tests for the plain-words description and for what a stale filter link shows the buyer.
- **Decisions put to me:** (B) match the searches that exist when the consumer runs; (C) "read" means seen; (D) keep strict validation, but the buyer sees a clear message on a working page. All three accepted as recommended.
- **(A) overruled:** the recommendation was that a re-listed home stays quiet, to protect "one alert per home". I rejected it. A re-listing is often a sale that fell through, exactly when a buyer who missed out wants to know. The design changed to deduplicating **per go-live event** (alerts unique per user and outbox message), with an `AlertReason` enum so the buyer sees "Back on the market", and a guard that refreshes an existing unread alert instead of adding another, so flip-flopping listings can't spam.
- It also caught an inconsistency in NOTES.md ("min bedrooms" where the decision was a range), now fixed, and added two "where it won't hold up" points: noisy broad searches, and engagement signals we can't measure yet.

**Checks:** none. Documents only.

**Reviewed:** *pending*

### 15. Task 1, step 2: data layer

**Branch:** `feature/saved-search-alerts` · **Agent:** `data` (Sonnet), reviewed by the main session (Opus)

**Built by the data agent:**
- 3 migrations: `saved_searches` plus region and type join tables; `outbox_messages`; `alerts` plus `alert_saved_search`.
- The `AlertReason` enum.
- Models `SavedSearch`, `SavedSearchRegion`, `SavedSearchPropertyType`, `Alert` (unread scope), `OutboxMessage` (unsent scope), plus `User` relationships.
- Factories with states, and 17 data-invariant tests.

Key constraints:
- Alerts are unique per user and go-live event (idempotent reprocessing, re-listings allowed).
- Deleting a saved search removes only its alert links.
- `outbox_message_id` is restrict-on-delete, so pruning the outbox later must be a deliberate decision.
- Every index carries a comment naming the query it serves.

**Found in review, and fixed:** the agent claimed `saved_searches.user_id` was "indexed by the FK". That's true on MySQL only: SQLite and PostgreSQL don't auto-index foreign keys, so listing or counting a user's searches would scan the table. I added an explicit index with a comment explaining why. It's exactly the kind of engine difference the data agent is meant to catch, and a reminder to review agent output rather than trust its report.

**Process slip, recorded honestly:** the agent wrote the migrations and models *before* the tests, against CLAUDE.md's "failing test first" rule. It said so in its report. The tests do assert real behaviour (constraints, cascades, casts), but they weren't written first. For the backend step I'll hold the agent to writing the failing tests first.

**Portability notes from the agent (for the backend step):** region case differs by engine (normalise it), SQLite doesn't enforce unsigned integers (validation must reject negatives), and the JSON payload must only be read through its cast, never queried.

**Checks (re-run by me after the fix):** `migrate:fresh --seed` OK; 33 tests passed, 162 assertions, **94.9%** coverage (the new models and enum at 100%); Pint passed; PHPStan 0 errors.

**Reviewed:** *pending*

### 16. Task 1, step 3a: search criteria and saved searches; `composer dev` via WSL

**Branch:** `feature/saved-search-alerts` · **Agent:** `backend` (Sonnet), reviewed by the main session (Opus)

I split step 3 in two (3a: search and saved searches; 3b: outbox, relay, consumer, alerts), so each half is reviewable on its own.

**Tests first, this time:** after step 2's slip, I required the agent to write the tests first and show them failing. First run: 59 tests, 55 failing (missing routes and props, `min_price` ignored), then green.

**Built:**
- `SearchCriteria` (`app/Support`): one typed value object that normalises (sorted sets, blanks absent, paging ignored, region casing), describes a search in plain words, builds the "View homes" URL, and checks for duplicates. The search page, saving, and later the alert matcher all use it, so they can't disagree.
- `Listing::scopeMatching`: the one definition of a match, replacing the controller's inline filters.
- A shared rules base (`SearchCriteriaRequest`) for the listings page and for saving.
- Saved-search routes: index, store, destroy. Destroy loads through the user's relationship, deliberately avoiding route model binding so another user's search is a 404 that doesn't confirm it exists.
- A 20-search cap, a normalised duplicate check, and a 10-a-minute throttle, with friendly messages on one error key.
- A bad listings link redirects to the same page minus the bad filters (no loop), with the error shown.
- Data-driven props: region options from branches, price and bedroom presets, normalised filters, `currentSearchSaved`.

**Found in review, and fixed:** the placeholder Saved searches page rendered two `<h1>`s (an empty one from `AppLayout`, whose required `heading` prop wasn't passed, plus its own). That breaks the one-`h1` semantic rule. Fixed by passing the heading to the layout.

**Known gap until the UI task:** the existing `Listings/Index.vue` region dropdown expects one value but now receives a list, so it won't show the selected region. Filtering still works. The UI task rewrites this page.

**Running the app: back to the README.** `composer dev` failed natively because Pail needs PHP's `pcntl` extension (Unix only), and `--kill-others` then stops every process. I tried WSL Ubuntu (PHP 8.4 with `pcntl` and PCOV, Node 20). `composer dev` ran, but from `/mnt/c` every page took about 6 seconds because of the Windows↔Linux file bridge, and the remedies (a second clone in Linux's filesystem, or a local Docker setup) were turning into a project of their own. I stopped and went back to the README's "Getting started" exactly: `php artisan serve` and `npm run dev` in two terminals. Lesson: the codebase defines how it runs; work with that rather than around it. The WSL packages stay installed, but nothing in the repo depends on them.

**Checks (re-run by me):** 93 tests passed, 590 assertions, **97.8%** coverage; Pint passed; PHPStan 0 errors; build OK.

**Reviewed:** *pending*

### 17. Task 1, step 3b: outbox, relay, consumer and alerts

**Branch:** `feature/saved-search-alerts` · **Agent:** `backend` (Sonnet), reviewed by the main session (Opus)

**Tests first:** 41 tests written before any code. The first run had 38 failing (no relay command, no alerts route, no inverse scope, no outbox write), then green. Mid-task I added a rule that tests follow AAA; the agent applied it to its new tests.

**Built:**
- **Atomic go-live:** `Listing::save()` wraps the save in a transaction and writes the `listing.went_live` outbox row inside it. Whether the listing is "going live" is decided *before* saving, while the old status is known. A test forces the outbox write to fail and proves the status change rolls back.
- **`outbox:relay`:** an explicit one-entry handler map, id order, marks sent, logs failures (no personal data) and carries on, scheduled every minute without overlap. The in-process call is marked as standing in for SNS.
- **Consumer (`App\Outbox\ListingWentLive`):** re-checks the listing is still live, then matches in one query through `SavedSearch::scopeMatchingListing`. A **parity test** (13 searches × 5 listings) keeps that inverse scope in step with `Listing::scopeMatching`. It creates one alert per user (or refreshes an existing unread one), sets `back_on_market` for homes alerted before, and is idempotent per message.
- **Alerts page:** user-scoped, newest first, 15 per page. "New" is captured before marking read, only the shown page is marked read, and marking read doesn't re-sort the list. Alerts whose searches were deleted are still explained. The page prop is `alertList`, so it doesn't clobber the shared `alerts.unread`.
- **Seeder:** marks the seeded stock's outbox rows sent, so the first relay doesn't flood the demo user, and adds one demo saved search.

**Found in review:** the agent reported that reprocessing a message "changes nothing, not even `updated_at`". That's true for newly created alerts, but if the message *refreshed* someone's unread alert, a reprocess touches it again. The only effect is the alert moving back to the top, so it's harmless; I'm recording it accurately rather than repeating the claim.

**Live demo (run by me on the real database):**
- `migrate:fresh --seed` leaves 0 unsent messages.
- Setting draft listing #74 live, then running `outbox:relay`, created alert #1 (`new_listing`, linked to the demo search).
- Marking it read, setting under offer, then live again, then relaying created alert #2 (`back_on_market`, unread). The re-listing rule works end to end.

**AAA audit** (all new tests, including the earlier steps): the structure is mostly sound, but two patterns break strict AAA. Setup steps that also assert (e.g. a setup relay run ending in `->assertSuccessful()`), and Act chained to Assert (`$this->post(...)->assertSessionHasErrors(...)`, a common Laravel idiom). The step 3a tests also lack blank-line separation. **I chose to tidy, consistently:** every feature test, including the two original files, now uses the same AAA layout (`// Arrange`, `// Act`, `// Assert`; no assertions in setup; one captured action). Test-only change: no behaviour or coverage changes.
- **Verified, not assumed:**
  - Same 134 tests, 98.3% coverage, and no tests with zero assertions (`--fail-on-risky`).
  - The assertion count fell from 778 to 725. I checked why: setup assertions were removed, and multi-request tests now compare one combined result instead of asserting per request.
  - To prove the tests still catch bugs, I **deliberately broke the consumer twice**: forcing every alert to `new_listing` failed 1 test, and removing the flip-flop guard failed 2. Then I restored the code (byte-identical) and the suite went green again.
- **Judgement calls I accepted:** three tests gather several GETs inside one Act and compare them together (a mild stretch of "one action", but one behaviour per test); the no-backfill test proves it with "no alerts, no pending messages" after saving, instead of a second relay run.

**Checks (re-run by me):** 134 tests passed, 778 assertions, **98.3%** coverage; Pint passed; PHPStan 0 errors; build OK.

**Reviewed:** *pending*

### 18. Task 2, step 1: product owner review and RUM baseline

**Branch:** `feature/saved-search-alerts` · **Agent:** `product-owner` (Opus) · **Baseline commit:** `8273f09`

**Review (from the docs and code):**
- **Gaps:** the listings page needs a plain-words description of the *current* search from the backend, so Vue doesn't re-implement it; and `image_url`, WebP files and image credits are missing.
- **Must-have UI rules:** don't prefetch the Alerts link (viewing marks alerts read); a visible hint on the disabled Save; hide "✓" from screen readers; move focus sensibly after a delete; no link on unavailable homes; alert reasons come from props.
- **Corrected in my review:** the agent claimed a rate-limited save shows a raw 429. Step 3a already turns it into a friendly message, and a test covers it. Its valid point is that save and delete share one 10-a-minute budget.
- **Seven decisions put to me (P1–P7).**

**RUM baseline (measured in Chrome).** It needed two attempts. The extension wasn't connected until I restarted after the session's account changed. Dev-server timings are relative.

| Page / action | LCP | CLS | Latency | Errors | Buyer friction |
| --- | --- | --- | --- | --- | --- |
| `/` cold load | 652ms | 0 | TTFB 222ms | none | No way to reach Saved searches or Alerts; no Save |
| Filter submit | — | 0 | ~199ms request, <16ms interaction | none | Works, but a second submit produces `region[0]=` array URLs |
| Array-form URL | — | 0 | — | none | **The Area select is blank while results are filtered**, so the filter is hidden |
| Max price £275k | — | 0 | — | none | The browser blocks it: the input steps in £10k |
| No results | — | 0 | ~196ms | none | Dead end; no way forward |
| `/?region=Atlantis` | ~650ms | 0 | — | none | **Silently shows all 184 homes**, with no message |
| Detail page, then "Back to all listings" | — | 0 | ~167ms | none | Drops the filters and page (browser back keeps them); no photo |
| Keyboard only | — | 0 | — | none | Focus always visible, no traps; no skip link past the filters |
| `/saved-searches` | 240ms | 0 | — | none | A bare link; no delete, count or empty state |
| `/alerts` | 264ms | 0 | — | none | The same home twice (new, then back on the market), with no date, price, read state or "matched" line |

**Conclusion:** performance isn't the problem (CLS 0 everywhere, LCP under 700ms, no errors or failed requests). The "after" comparison is about **journey friction and whether the buyer can understand what they're seeing**.

**My decisions (P1–P8), asked one by one:**
- Accepted as recommended: backend sends the current-search description (P1); Save saves the applied URL filters (P2); a non-preset value shows as an extra option (P3); the last delete on a page lands on the last non-empty page (P6); "3 of 20 saved" (P7); one card per home on Alerts (P8, raised by the baseline itself).
- **P4:** I first chose "We'll alert you", then changed to "We'll add new matching homes to your Alerts". Alerts are in-app only with no backfill, so the shorter line over-promises.
- **P5, overruled:** save and delete keep one shared rate limit, rather than a looser one for deletes. It's simpler, and the case is rare.

The UI task now includes these decisions, a new **step 1b** (backend prep: P1, P6, P7, P8, images), and the baseline's fixes as must-haves (comma URLs, visible bad-link errors, skip link, "Back to all listings" keeps filters).

**Reviewed:** *pending*

### 19. Task 2, step 1b: backend prep for the UI

**Branch:** `feature/saved-search-alerts` · **Agent:** `backend` (Sonnet) · **Images:** converted by the main session

**Tests first (AAA):** 11 new or changed tests failing first (missing props, two cards for one home, unread counting alerts rather than homes, wrong redirect page), then green.

**Built:**
- **P1:** a `searchDescription` prop. **P7:** `savedSearchCount` (the total, not the page's count).
- **P6:** a delete redirects to the last page that still has searches. The page comes from the referer, as a clamped whole number only, so it's safe; with no referer it falls back to page 1.
- **P8, one card per home:**
  - `Alert::scopeLatestPerListing`, a portable `NOT EXISTS` query (no window functions).
  - One grouped query per page for `first_alerted_at`, `alert_count` and the "new" state.
  - Viewing marks every alert for the shown homes read.
  - The unread count counts **homes**, matching what the buyer sees.
- **Images:** the 5 photos converted to **WebP at native size** (about 218 KB, from about 1.7 MB of PNG); the PNG originals removed. `image_url` picked deterministically by listing ID. The agent also added a constant map of each photo's pixel size plus `image_width`/`image_height` props. **I had those removed:** hard-coded image dimensions in the backend aren't data-driven, and aspect ratio is a UI concern, handled with a named Tailwind aspect token on the card's image box. I dropped the agent's `CREDITS.md`: the Unsplash licence doesn't require attribution, and a credits file for placeholder photos isn't relevant to this exercise.

**Found in review:**
- **My own slip:** I first upscaled the 500px photos to 640px, which only adds blur, then redid them at native size.
- **A known coupling, recorded, not rewritten:** `AlertResource` needs the controller to attach a `history` aggregate. Built anywhere else without it, it fails loudly (fail fast) rather than showing wrong data. A cleaner long-term version would load the counts through an Eloquent relationship.
- The agent found and fixed its own bug while testing: a reused query builder kept an `id` filter, giving the wrong page count.

**Security gap I found reviewing the code: no authentication guard.** Reading `AlertController` I asked what happens if someone just hits the endpoint. Authorisation (whose data) was solid, but nothing checked *that* a user was signed in. The routes trusted the auth stub, so a guest crashed on `null->alerts()` with a 500. Tests first: `AuthenticationTest` showed all four user-scoped routes returning 500. The fix is a `RequireUser` middleware on a route group, returning a clean 401 before any controller runs; listings stay public. Laravel's `auth` middleware wasn't usable: the stub sets the request's user resolver, not the auth guard, so `auth` would have refused even the demo user. The stub is untouched. Confirmed on the running app: the demo user still gets 200 on every page.

**Checks (re-run by me):** 151 tests passed, 820 assertions, **98.4%** coverage; Pint passed; PHPStan 0 errors; build OK.

**Reviewed:** *pending*

### 20. Task 2, step 2a: UI foundations

**Branch:** `feature/saved-search-alerts` · **Agent:** `ui` (Sonnet), reviewed by the main session (Opus)

I split the UI into 2a (foundations) and 2b (pages), so each is reviewable on its own.

**Built:**
- **Test tooling:** Vitest 4, Vue Test Utils, jsdom 26 and axe-core. The versions run on both Node 20 (CI) and 24 (local); Vitest 5 and jsdom 27 need Node 22+, so they were avoided. An axe helper runs WCAG 2.2 AA rules, with only `color-contrast` disabled because jsdom can't compute it. CI gains an `npm test` step.
- **Theme tokens:** navy, cream canvas, teal brand scale, text-safe status colours, radius, shadow, `aspect-card`, plus `logo-inverse` and focus-ring utilities. Every text pair was contrast-checked: the lowest is muted on canvas at **6.05:1**, all above 4.5:1.
- **Logo:** Street's SVG vendored (12 paths, nothing else) and inlined so `logo-inverse` turns it white. Its accessible name is "Street".
- **Shell:** skip link, navy header, main nav with `aria-current`, a mobile Menu button (`aria-expanded`, Escape closes it and restores focus), and one `<main>` with one `h1`.
- **Header, reworked after I reviewed it in the browser:**
  - The first version had a "Welcome back, {name}" greeting and a text "Alerts" link. I changed it to the logo on the left, **Listings and Saved searches centred**, and on the right a small **initials avatar** and a **bell** linking to Alerts, with an unread badge.
  - I then removed the visible name text. The avatar now carries "Signed in as {name}" for screen readers, with the initials hidden so they aren't read out letter by letter.
  - The bell's accessible name is "Alerts, 3 unread", and it doesn't prefetch (see below).
  - Final layout: the logo and **Listings** on the left; a **bookmark icon** (Saved searches), the bell, then the avatar on the right, with **the avatar always rightmost at every width**.
  - With one text link left, the mobile Menu button and its code were removed. The logo already links to Listings, so the "Listings" text hides on small screens.
  - **Checked at 320px:** the agent only calculated the fit, so I loaded the page into a 320px frame in Chrome. Everything fits in one row with no horizontal scroll, and the avatar is last.
- **Listing card:** photo in a 3:2 box with `object-cover`, `alt=""`, lazy loading, an `h2` per card, and a non-link variant for unavailable homes.
- **Tests:** 14 Vitest tests, AAA layout, including axe on the shell (with and without a user and unread alerts) and on both card variants.

**Found in review:**
- **"The logo isn't inverted":** I checked in Chrome rather than guessing. Our CSS works (all 12 paths compute to white). The **Dark Reader** extension in my browser was inverting the white SVG back to dark (`filter: invert(1)` plus `data-darkreader-inline-invert`). It isn't an app bug, but it changes how the brand renders for any visitor using it, and it would skew WAVE contrast checks.
- The logo is rendered with `v-html`. That's acceptable here because it's a static SVG vendored in the repo, never user input; the "no `v-html` with user data" rule still holds.
- `npm audit` flags a high-severity advisory in Vue's server renderer (below 3.5.42). The app doesn't server-render, but the runtime bump is put to me as a decision.
- Vitest on Windows fails under Git Bash's lowercase `/c/...` path ("failed to find the current suite"). It works from PowerShell; CI on Linux is unaffected.

**Checks (re-run by me):** `npm test` 14 passed; build OK; PHP suite 151 passed; no inline styles, `<style>` blocks, arbitrary values or `!important` in `resources/`.

**Reviewed:** *pending*

<!-- New entries go below, in order. Each one: branch, what was done and why, checks run and results, what changed after review, then "**Reviewed:** *pending*" for Adam to sign off. -->

