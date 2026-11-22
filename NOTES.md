# Notes

The short version. The full, time-ordered working is in [PROCESS.md](PROCESS.md).

## Key decisions and trade-offs

### Setup

- **Native PHP, as the README defines, though I'd use Docker.** Locally that's PHP 8.4 through Chocolatey and Composer through its Windows `.exe` installer: exactly the per-machine upkeep Docker avoids. I tried Laravel Sail and reverted it. It added a dependency and silently rewrote `phpunit.xml`, which is working against the codebase. On a real project I'd propose Sail through Docker: the same platform on every machine, no Chocolatey or Composer upkeep on Windows, and the same image deployable to AWS (ECR to Lambda or ECS).
- **Local and CI must agree.** Same PHP minor version (8.4) and the same coverage driver (PCOV). Coverage is 93.2% on both.
- **Coverage floor of 90%** in CI, against a 93.2% baseline, so new work has to arrive with tests.
- **Quality gate as a Claude Code hook.** Pushes and PRs are blocked until Pint, PHPStan, the tests with coverage, and the build pass locally. Checks don't depend on the AI remembering them, and CI minutes only go on work that passes.
- **AI work is visible and attested.** `claude/` branches, `CLAUDE - ` commits, the `claude` PR label, and a PR template whose attestation box a human must tick. A required check and a branch ruleset enforce this, not just convention.
- **One feature branch, attested once.** I dropped per-task worktrees and PRs for the feature: one person, one feature, so a PR per task was overhead. Every push is still gated locally, and the ruleset protects `main`, so the hand-back PR is the single attestation point.
- **Laravel Boost: dropped, but I'd use it.** Its doc search and schema/log tools keep an AI grounded in the real app. Here it would add a dependency the codebase doesn't have.
- **Proportion:** this setup is heavier than a 4-hour exercise strictly needs. I did it because the brief cares how the AI is directed and reviewed, and these are the guard rails I'd want before letting an AI write production code.

### Feature

*To be recorded as decisions are made.*

## Deliberately left out

*To be filled in.*

## With more time

- **Vulnerability checks.** A remote code execution flaw was recently reported in this PHP version line, and green tests say nothing about that. I'd add `composer audit` and `npm audit` to the gate and CI, fail on PHP patch versions with known advisories, pin CI to a patched PHP, and turn on Dependabot. With Docker, I'd also scan images (Trivy or ECR).
- **Coverage on the PR:** a job summary and a PR comment showing patch coverage (the lines the PR changed), not only the total.
- **One CI pipeline:** remove the four leftover Laravel skeleton workflows (`tests.yml` and others), which duplicate `ci.yml`.
- **Node parity:** CI runs Node 20, local runs Node 24.

## Where it won't hold up yet

*To be filled in once the feature exists.*
