# Notes

The short version. The full, time-ordered working is in [PROCESS.md](PROCESS.md).

## Key decisions and trade-offs

### Setup

- **Native PHP, as the README defines, though I'd use Docker.** Locally that's PHP 8.4 through Chocolatey and Composer through its Windows `.exe` installer: exactly the per-machine upkeep Docker avoids. I tried Laravel Sail and reverted it. It added a dependency and silently rewrote `phpunit.xml`, which is working against the codebase. On a real project I'd propose Sail through Docker: the same platform on every machine, no Chocolatey or Composer upkeep on Windows, and the same image deployable to AWS (ECR to Lambda or ECS).
- **Local and CI must agree.** Same PHP minor version (8.4) and the same coverage driver (PCOV). Coverage is 93.2% on both.
- **Coverage floor of 90%** in CI, against a 93.2% baseline, so new work has to arrive with tests.
- **Local quality gate: removed for now.** A Claude Code hook used to block pushes until Pint, PHPStan, the tests with coverage, and the build passed locally. It ran inside the working checkout, so its `npm ci` collided with the running Vite dev server, and it checked uncommitted files rather than the commit being pushed. Rather than patch around a flawed design, I removed it. CI still runs every check on PRs, and the hook still enforces the commit prefix and PR attestation. To bring it back, run it in an isolated checkout of the exact commit being pushed.
- **AI work is visible and attested.** `claude/` branches, `CLAUDE - ` commits, the `claude` PR label, and a PR template whose attestation box a human must tick. A required check and a branch ruleset enforce this, not just convention.
- **One feature branch, attested once.** I dropped per-task worktrees and PRs for the feature: one person, one feature, so a PR per task was overhead. The ruleset protects `main`, so the hand-back PR (with CI) is the single attestation point.
- **Laravel Boost: dropped, but I'd use it.** Its doc search and schema/log tools keep an AI grounded in the real app. Here it would add a dependency the codebase doesn't have.
- **Proportion:** this setup is heavier than a 4-hour exercise strictly needs. I did it because the brief cares how the AI is directed and reviewed, and these are the guard rails I'd want before letting an AI write production code.

### Front end, accessibility and UI testing

*Planned. The agent definitions are written; the tooling isn't built yet.*

- **UI testing as seriously as backend testing.** Vitest with Vue Test Utils for component tests, and Playwright for end-to-end flows and basic usability checks (keyboard-only use, focus order, visible labels, error messages).
- **WCAG 2.2 AA is the minimum.** Automated with axe-core inside the Playwright tests, so an AA violation fails the suite. Automated tools only catch part of WCAG, so I also check by hand with two Chrome extensions:
  - **Headers** (heading structure): one `h1`, no skipped levels.
  - **WAVE Evaluation Tool** (WebAIM): contrast, labels, ARIA and structure errors.
- **Branding from two Street sources,** as Tailwind v4 `@theme` tokens:
  - **The marketing site** gives the shell. A near-black navy header (`#0C0D17`, the logo's own colour) with the Street logo, white nav and a square white call-to-action button; a warm cream canvas; content in columns with thin dividers and underlined "Learn more" links.
  - **The dashboard** gives the app content. Deep teal primary, light teal active states, white rounded cards with soft shadows, orange "Behind" and green "On Track" pills with text labels.
  - **The logo** is Street's own SVG (`web2026.street.co.uk/images/street-logo.svg`), vendored into the repo rather than hot-linked. Its fill is a CSS variable, so it turns white on the dark header with no second file. The logo and brand belong to Street. They're used here only to keep the exercise on-brand and topical, because this is Street's own exercise.
  - **No glassmorphism:** neither source uses it.
  - **Strict Tailwind, no one-off styling.** No inline styles, no component `<style>` blocks, no arbitrary values (`bg-[#...]`), no `!important`. Anything new becomes a token in `@theme` or a named `@utility`, so the design system stays in one place and components can't drift.
- **The UI agent owns UX, not just visuals:** the buyer's task first, every state designed (empty, loading, success, error), Nielsen's heuristics, accessible forms, mobile-first, plain microcopy, and reusing existing patterns before inventing new ones.
- **Matt Pocock's AI skills** (`mattpocock-skills`) to direct the AI. For example, **grilling** stress-tests a plan by having the AI question me before anything is built, so gaps surface in conversation, not in code.
- **Specialist agents** in `.claude/agents/`, each with a named model:
  - **Product owner:** keeps the feature focused on buyer value (the problem, the outcomes, the smallest valuable slice, protecting trust) rather than a checklist. It uses the running site in Chrome as a buyer would, treated like **Real User Monitoring (RUM)**: Core Web Vitals (LCP, CLS, INP), console errors, failed requests and journey friction, measured in the browser. Its feedback comes from real use, not from reading code. In production I'd back this with a real RUM tool collecting the same signals from actual buyers (e.g. CloudWatch RUM or Sentry). I still set direction.
  - **UI:** Vue and Tailwind, the brand, accessibility.
  - **Backend:** follows the existing Laravel patterns exactly, and adds:
    - **OWASP Top 10 / ASVS.** Broken access control is this feature's biggest risk, so every saved search and alert is loaded through the user's relationship, other users' records return 404, and that's proven by tests. Also bound queries only, mass assignment from validated input only, CSRF kept, write routes rate-limited, no personal data in logs.
    - **Fail fast.** Validate at the boundary before any work, use guard clauses, no silent fallbacks, and database constraints as the last line of defence.
    - **Validate constantly.** A Form Request on every write, re-checks at each trust boundary (a queued job confirms the listing is still live), and tests for the rejections as well as the happy path.
    - **Strongly typed.** Types on every method and property, enums instead of strings, typed request access, PHPStan generics. I'd recommend `declare(strict_types=1)` across the codebase, but the existing files don't use it, so I'm flagging it rather than adding it to new files only.
  - **Data:** schema, enums, models, and keeping Vue tied to the same values. It knows SQLite's quirks (loose type affinity, dates stored as text, different case and collation rules, limited `ALTER TABLE`) and keeps queries and migrations portable to a production engine.

### Database engine: SQLite vs a production-like engine

SQLite causes formatting problems: dates stored as text, loose typing, different collation. A green test suite on SQLite can hide bugs that MySQL or PostgreSQL would expose. **My preference:** run the real production engine locally and in the pipeline. I don't know which engine Street runs in production (MySQL or PostgreSQL), so that's the first thing I'd confirm with the team; everything below applies to either. I'm keeping SQLite here because it's what the codebase defines. The trade-off:

| | Keep SQLite (current) | Production engine locally + in CI |
| --- | --- | --- |
| **Parity with prod** | ✗ Type affinity, text dates and collation differ; bugs can pass tests | ✓ Same engine, same types, same date and collation behaviour |
| **Setup** | ✓ Zero: bundled with PHP, matches the README | ✗ A local DB server (best via Docker, see Sail above), README changes |
| **CI / infra** | ✓ Nothing extra | ✗ A database service container in GitHub Actions, migrations against it, secrets/env config |
| **Test speed** | ✓ In-memory, very fast | ✗ Slower (a real server, needs transactions or truncation between tests) |
| **Fit with this exercise** | ✓ Works with the codebase as defined | ✗ Changes the infrastructure the brief gave me |

**Middle ground I'd propose first:** keep SQLite as the fast local default, and add a **second CI job** that runs the same suite against the production engine as a GitHub Actions service container. That catches engine-specific bugs before merge, at the cost of one extra CI job. Locally, Docker would bring full parity without a manual install. Not built here; noted as the next step.

### Feature

*To be recorded as decisions are made.*

## Deliberately left out

*To be filled in.*

## With more time

- **Claude gates for when the AI hallucinates, drifts off task, or makes decisions it shouldn't.** Rules in CLAUDE.md are guidance; a model can still invent an API, wander outside the task, or quietly decide something that's mine to decide. I'd add hooks that enforce the boundaries mechanically:
  - **Scope:** block edits outside the files and areas the current task names (e.g. no touching the auth stub, CI config or the coverage threshold).
  - **Decisions:** block changes to `NOTES.md` decision lines, ruleset or CI config unless I've approved them, and block commits or pushes without my explicit go-ahead.
  - **Hallucination checks:** fail when code references a class, route, method or config key that doesn't exist (PHPStan and the build catch some of this, so wire them into a gate), and when a commit or PR claims a test result the run didn't produce.
  - **Rebuilt quality gate:** the push gate above, in an isolated checkout of the commit being pushed.
- **Vulnerability checks.** A remote code execution flaw was recently reported in this PHP version line, and green tests say nothing about that. I'd add `composer audit` and `npm audit` to the gate and CI, fail on PHP patch versions with known advisories, pin CI to a patched PHP, and turn on Dependabot. With Docker, I'd also scan images (Trivy or ECR).
- **Coverage on the PR:** a job summary and a PR comment showing patch coverage (the lines the PR changed), not only the total.
- **One CI pipeline:** remove the four leftover Laravel skeleton workflows (`tests.yml` and others), which duplicate `ci.yml`.
- **Node parity:** CI runs Node 20, local runs Node 24.

## Where it won't hold up yet

*To be filled in once the feature exists.*
