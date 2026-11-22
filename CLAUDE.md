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

Match what is already in the code. If a pattern looks wrong, say so in `docs/DECISIONS.md` — do not work around it silently.

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

**Run them yourself before every push.** The local push gate is removed for now (see docs/NOTES.md). The hook in [.claude/hooks/quality-gate.mjs](.claude/hooks/quality-gate.mjs) still enforces the commit prefix and PR rules, and CI enforces the checks on PRs. Show the output of all four before asking to push. Never lower the coverage threshold without Adam's say-so.

**Never commit or push without Adam knowing.** Show the diff summary and the proposed message, then wait for his go-ahead.

Write the failing test first for any behaviour change (matching, scoping to the user, dedup). Do not claim a task is done without running the checks and showing the output.

## Docs and real state

Laravel Boost is **not** used in this exercise, because it would add a dependency the codebase doesn't have (see `docs/NOTES.md`). Instead:

- Check Laravel, Inertia and Vue APIs against current docs (Context7) before using them. Don't guess.
- Check real state rather than assuming it: `php artisan migrate:status`, `php artisan route:list`, `php artisan tinker`, and `storage/logs/laravel.log`.

## Workflow: the feature branch and small tasks

- All feature work happens on `feature/saved-search-alerts` (cut from `main`), in the main checkout. No worktrees.
- Commit and push straight to the feature branch. The branch ruleset protects `main` only. Every push still goes through the quality-gate hook, and attestation happens once, on the hand-back PR to `main`.
- Never push to `main` or any other branch. Any *new* branch Claude creates is prefixed `claude/`.
- One task = one reviewable change (e.g. "migration + model + factory", "matching logic + tests"). If a task touches more than one layer it is probably two tasks.
- Commit small. Commit messages say *why*. Only push or open PRs when asked.
- **Every commit subject starts with `CLAUDE - `**, e.g. `CLAUDE - Add saved searches table`, so Adam's commits in between stand out. The hook blocks a `git commit` without it.
- Hand-back: PR from the feature branch **against our own fork** (`kernig-adam/listings-exercise`), never upstream.

## Pull requests

Every PR uses [.github/pull_request_template.md](.github/pull_request_template.md). `gh pr create` does not apply it for you: copy the template into the body (a `--body-file` works best) and fill in every section. The hook blocks a `gh pr create` that is missing a section, the unticked attestation line, or `--label claude`, and blocks any `gh pr` command that ticks the box.

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
| `opus` (Opus 5.5) | Planning, design decisions (match rules, dedup, backfill), code review, docs/NOTES.md |
| `sonnet` (Sonnet 5.5) | Implementing a planned task: migrations, models, controllers, Vue pages, tests |
| `haiku` (Haiku 5.5) | Mechanical work: searches, renames, running checks, Pint fixes |

## Deliverables

1. Working feature on the feature branch, all checks green.
2. `docs/NOTES.md` stays **short (about half a page)**, as the brief asks: a fresh-install walkthrough, then key decisions and trade-offs, what was left out, what's next, and where it won't hold up. It links to the detail.
   - **`docs/DECISIONS.md`** is the full decision record. Record decisions there **as they are made**, not at the end.
   - When Adam says "note", "notes" or "add to the notes", write it to `docs/DECISIONS.md` **straight away, in that same turn**, with his reasoning. Only touch `docs/NOTES.md` if it changes the half-page summary, and keep docs/NOTES.md short.
3. `docs/PROCESS.md`: the time-ordered log of how the work was done, written for the reviewers. After each task, append a numbered entry: branch, what was done and why, which checks ran and their results, and what changed after review. Write it in the first person as the candidate. Keep facts accurate. Do not log a check as passing unless it actually ran.
   - Write the entry **during the task**, as part of finishing it. Never rebuild entries afterwards from transcripts or session exports.
   - End every new entry with `**Reviewed:** *pending*`. Only Adam changes it to a sign-off. Never mark an entry reviewed yourself.

## Don't

- Send real email or need a running queue worker. A persisted alert / database notification is enough. Describe any queue or scheduler design in `docs/DECISIONS.md`.
- Add dependencies for what a few lines can do.
- Add abstractions with one implementation (repositories, interfaces, service layers) unless the code already uses them.
- Touch the auth stub.
