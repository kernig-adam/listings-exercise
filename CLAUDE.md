# Street Listings — Saved-Search Alerts

Interview exercise. Read [TASK.md](TASK.md) (the brief) and [README.md](README.md) (setup, layout) before any work.

**Goal:** a clean, well-tested vertical slice of saved searches + alerts, in 3–4 hours. Design decisions and tests matter more than feature count. Do not gold-plate.

## Stack

- Laravel 13, PHP 8.4, SQLite
- Inertia v3 + Vue 3.5 (`<script setup>`) + Tailwind v4, built with Vite
- PHPUnit 12 (not Pest), Pint, Larastan level 5
- No JSON API. Controllers return `Inertia::render()`; API Resources shape the props.
- Auth is stubbed: `ActAsDemoUser` makes every request the seeded demo user. Use `$request->user()` / `auth()->user()`. Run `php artisan migrate --seed` first.

## Follow existing patterns

Match what is already in the code. If a pattern looks wrong, say so in `NOTES.md` — do not work around it silently.

| Concern | Pattern to copy |
| --- | --- |
| Validation | Form Request per action — `app/Http/Requests/ListingIndexRequest.php` |
| Props shape | API Resource — `app/Http/Resources/ListingResource.php` |
| Enums | Backed enums in `app/Enums`, cast on the model, `options()` for the front end |
| Query rules | Model scopes (`scopeLive`), not inline repeats |
| Models | `@property` docblocks for PHPStan, `casts()` method, `$fillable` |
| Factories | States per meaningful variant (`live()`, `sold()`) |
| Pages | `resources/js/pages/<Area>/<Page>.vue` (lowercase dirs), shared bits in `resources/js/components` |
| Routes | Named routes in `routes/web.php` |
| Tests | Feature tests with `RefreshDatabase`, `test_snake_case_names`, assert behaviour not implementation |

Comments explain *why*, not *what* (see the `id` tiebreaker comment in `ListingController`).

## Quality gate

Native PHP, as the README describes. Local must match CI: same PHP minor version, and **PCOV** as the coverage driver.

All four must pass before a task is done. CI runs the same.

```bash
php artisan test --coverage --min=90   # threshold lives in .github/workflows/ci.yml only
vendor/bin/pint --test        # fix with: vendor/bin/pint
vendor/bin/phpstan analyse    # keep the ignoreErrors baseline empty
npm run build
```

**Enforced by a hook.** [.claude/hooks/quality-gate.mjs](.claude/hooks/quality-gate.mjs) runs all four before any `git push` or `gh pr create|merge|ready`, and blocks the command if anything fails, or if local PHP/PCOV doesn't match CI. Run the push from inside the worktree (or with `git -C <worktree>`) so the right checkout is checked. Never bypass or weaken the hook, and never lower the coverage threshold, without Adam's say-so.

Write the failing test first for any behaviour change (matching, scoping to the user, dedup). Do not claim a task is done without running the checks and showing the output.

## Laravel Boost

Boost is a dev MCP server + AI guidelines. Install once:

```bash
composer require laravel/boost --dev
php artisan boost:install     # choose Claude Code; writes .mcp.json, boost.json, guidelines
```

- Boost owns only the `<laravel-boost-guidelines>` block in this file. Write project rules **outside** that block.
- Use its `search-docs` tool for Laravel/Inertia/Vue questions before guessing at an API.
- Use `database-schema`, `database-query`, `last-error`, `read-log-entries` to check real state instead of assuming it.
- Refresh after dependency changes: `php artisan boost:update`.

## Workflow: worktrees and small tasks

- Base branch for the feature: `feature/saved-search-alerts`, cut from `main`.
- Each task gets its own worktree and branch, then merges back into the feature branch:
  ```bash
  git worktree add .worktrees/<task> -b claude/<task> feature/saved-search-alerts
  ```
- **Any branch Claude creates or pushes must be prefixed `claude/`**, so the history shows which work was automated. Never push to a branch without the prefix.
  `.worktrees/` is gitignored. A new worktree needs `composer install` and `npm ci`; Composer's install script creates `.env` and `database/database.sqlite`.
- One task = one reviewable change (e.g. "migration + model + factory", "matching logic + tests"). If a task touches more than one layer it is probably two tasks.
- Commit small. Commit messages say *why*. Only push or open PRs when asked.
- Hand-back: PR from the feature branch **against our own fork** (`kernig-adam/listings-exercise`), never upstream.

## Pull requests

Every PR uses [.github/pull_request_template.md](.github/pull_request_template.md). Fill in every section:

- **Task:** what is being done, and why.
- **Objective:** what we are trying to achieve (the outcome, not the steps).
- **Testing:** what the tests check for, and the checks that ran, with real results (test count, coverage %). Never claim a result that didn't run.
- **Notes:** screenshots for UI changes, trade-offs, follow-ups, known gaps. Write "None" rather than deleting the section.
- **Attestation:** leave the box **unticked**. Only a human ticks it. Never tick it, edit it, or reword it. The `Attestation` check matches its exact wording and blocks the merge until it's ticked.

Also:
- Add the **`claude`** label: `gh pr create --label claude ...`.
- Title: imperative and specific, e.g. "Alert users when a listing goes live".
- End the body with the Claude Code attribution line.
- Never merge a PR, and never use `--admin` to bypass branch rules.

## Model per task

Every task in a plan names its model. Default to the cheapest one that can do it well.

| Model | Use for |
| --- | --- |
| `opus` (Opus 5.5) | Planning, design decisions (match rules, dedup, backfill), code review, NOTES.md |
| `sonnet` (Sonnet 5.5) | Implementing a planned task: migrations, models, controllers, Vue pages, tests |
| `haiku` (Haiku 5.5) | Mechanical work: searches, renames, running checks, Pint fixes |

## Deliverables

1. Working feature on the feature branch, all checks green.
2. `NOTES.md` (about half a page): key decisions and trade-offs, what was left out, what to do next, where it won't hold up (e.g. scale). Record decisions in it **as they are made**, not at the end.
3. `PROCESS.md`: the time-ordered log of how the work was done, written for the reviewers. After each task, append a numbered entry: branch, what was done and why, which checks ran and their results, and what changed after review. Write it in the first person as the candidate. Keep facts accurate. Do not log a check as passing unless it actually ran.
   - Write the entry **during the task**, as part of finishing it. Never rebuild entries afterwards from transcripts or session exports.
   - End every new entry with `**Reviewed:** *pending*`. Only Adam changes it to a sign-off. Never mark an entry reviewed yourself.

## Don't

- Send real email or need a running queue worker. A persisted alert / database notification is enough. Describe any queue or scheduler design in `NOTES.md`.
- Add dependencies for what a few lines can do.
- Add abstractions with one implementation (repositories, interfaces, service layers) unless the code already uses them.
- Touch the auth stub.
