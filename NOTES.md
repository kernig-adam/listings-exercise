# Notes

The short version. The full, time-ordered working is in [PROCESS.md](PROCESS.md).

## See an alert in about five minutes, from a fresh install

Each step was rehearsed on an empty database. Run from the project folder.

**1. Install and run** (the README's "Getting started", unchanged):

```bash
composer install
npm install
php artisan migrate --seed      # also creates a demo saved search: "Up to £400,000 · 2+ beds"
php artisan serve               # terminal 1 → http://127.0.0.1:8000
npm run dev                     # terminal 2
```

Open the site. The bell (top right) has no badge, and **Saved searches** (the bookmark icon) shows the demo search.

**2. A new home goes live** (there's no listing-management screen, so use `tinker`). This takes an unlisted (draft) home that matches the demo search and publishes it:

```bash
php artisan tinker --execute="App\Models\Listing::where('status','draft')->where('price','<=',400000)->where('bedrooms','>=',2)->firstOrFail()->update(['status'=>'live','listed_at'=>now()]);"
```

The listing and its `listing.went_live` outbox message are written in one transaction. **No alert exists yet**: alerts are generated outside the request.

**3. Run the relay** (the scheduler does this every minute; run it by hand to skip the wait):

```bash
php artisan outbox:relay
```

**4. See it:** refresh any page and the bell shows **1**. Open **Alerts**: the home appears with **"New"**, **"New listing"** and **"Matched: Up to £400,000 · 2+ beds"**. Viewing marks it read, so the badge clears, but the card keeps "New" for this visit.

**5. Optional: a sale falls through.** Once you've viewed that alert, put the same home under offer and back to live, then relay again:

```bash
php artisan tinker --execute="App\Models\Alert::firstOrFail()->listing->update(['status'=>'under_offer']);"
php artisan tinker --execute="App\Models\Alert::firstOrFail()->listing->update(['status'=>'live']);"
php artisan outbox:relay
```

The bell shows **1** again, and Alerts shows that home once, with **"Back on the market"** and **"First alerted {date}"**. If you skip viewing the first alert, it's refreshed instead of duplicated (the anti-spam guard).

**Also try:** save your own search from the listings page (pick filters, press Search, then **Save this search**), then put a home live that matches it. Homes that don't match any saved search create no alert.

*Notes:* seeding is random, so if step 2 reports "No query results", run `php artisan migrate:fresh --seed` and try again. On Windows, `composer dev` doesn't work (see below); the two-terminal steps do. If port 8000 is taken, `php artisan serve` picks the next free port.

## Key decisions and trade-offs

### Setup

- **Native PHP, as the README defines, though I'd use Docker.** Locally that's PHP 8.4 through Chocolatey and Composer through its Windows `.exe` installer: exactly the per-machine upkeep Docker avoids. I tried Laravel Sail and reverted it. It added a dependency and silently rewrote `phpunit.xml`, which is working against the codebase. On a real project I'd propose Sail through Docker: the same platform on every machine, no Chocolatey or Composer upkeep on Windows, and the same image deployable to AWS (ECR to Lambda or ECS).
- **I run the app exactly as the README's "Getting started" says,** natively: `composer install`, `npm install`, `php artisan migrate --seed`, then `php artisan serve` and `npm run dev` in two terminals. The repo's `composer dev` script (which the README only hints at) doesn't work on native Windows: it starts Pail, which needs PHP's `pcntl` extension (Unix only), and `--kill-others` then stops the whole stack. I tried WSL to get `composer dev`, but running from `/mnt/c` made page loads take about 6 seconds (the Windows↔Linux file bridge), and fixing that meant a second copy of the repo. So I went back to the README's two-terminal steps. It's another native-Windows limitation that the Docker approach would avoid.
  - **The README and `composer dev` aren't the same thing.** The README's instructions are `php artisan serve` and `npm run dev`. It never tells you to run `composer dev`. Its only hint is a comment ("or use the combined dev script") that doesn't name the command or say what it runs. The `dev` script in `composer.json` starts **four** processes, adding `php artisan queue:listen` and `php artisan pail`, and Pail fails on Windows. So I follow the README's actual instructions. The README should either name the script and what it runs (noting Pail needs a Unix-like environment), or not hint at it.
  - **"Getting started" on Windows needs far more than the README lists.** The README says PHP 8.4, Composer, Node and "SQLite (bundled with PHP, no separate database server needed)". On Windows I also had to:
    - install PHP itself (Chocolatey gave 8.6 by default, so I pinned 8.4 to match CI),
    - install Composer separately (its Windows installer),
    - **enable extensions by hand in `php.ini`:** `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `openssl`, `curl`, `zip`. SQLite is shipped but **off** by default on Windows, so "bundled" is misleading,
    - download the PCOV DLL separately for coverage parity with CI,
    - fix PATH by hand when two PHP versions were installed.

    That's exactly the per-machine setup and drift a container removes, and why I'd use Docker on a real project.
- **Local and CI must agree.** Same PHP minor version (8.4) and the same coverage driver (PCOV). Coverage is 93.2% on both.
- **Coverage floor of 90%** in CI, against a 93.2% baseline, so new work has to arrive with tests.
- **Local quality gate: removed for now.** A Claude Code hook used to block pushes until Pint, PHPStan, the tests with coverage, and the build passed locally. It ran inside the working checkout, so its `npm ci` collided with the running Vite dev server, and it checked uncommitted files rather than the commit being pushed. Rather than patch around a flawed design, I removed it. CI still runs every check on PRs, and the hook still enforces the commit prefix and PR attestation. To bring it back, run it in an isolated checkout of the exact commit being pushed.
- **AI work is visible and attested.** `claude/` branches, `CLAUDE - ` commits, the `claude` PR label, and a PR template whose attestation box a human must tick. A required check and a branch ruleset enforce this, not just convention.
- **One feature branch, attested once.** I dropped per-task worktrees and PRs for the feature: one person, one feature, so a PR per task was overhead. The ruleset protects `main`, so the hand-back PR (with CI) is the single attestation point.
- **Laravel Boost: dropped, but I'd use it.** Its doc search and schema/log tools keep an AI grounded in the real app. Here it would add a dependency the codebase doesn't have.
- **Proportion:** this setup is heavier than a 4-hour exercise strictly needs. I did it because the brief cares how the AI is directed and reviewed, and these are the guard rails I'd want before letting an AI write production code.

### Front end, accessibility and UI testing

*Planned. The agent definitions are written; the tooling isn't built yet.*

- **UI testing as seriously as backend testing.** Vitest with Vue Test Utils for component tests, including basic usability checks (keyboard interaction, focus, visible labels, error messages). Playwright is noted, not used (see the UI section below).
- **WCAG 2.2 AA is the minimum.** Automated with axe-core inside Vitest, so an AA violation fails the suite. Automated tools only catch part of WCAG, so I also check by hand with two Chrome extensions:
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
    - **Authentication *and* authorisation, as separate guards.** Authorisation (whose data) is enforced by always loading through the user's relationships, so another user's search is a 404. Authentication (is anyone signed in) was initially missing: the user-scoped routes relied on the auth stub always returning a user, so a guest hit `null->alerts()` and got a **500**. I spotted it reviewing the code. Those routes now sit behind a small `RequireUser` middleware that returns a **401** before any controller runs, proven by tests for every route. I couldn't use Laravel's `auth` middleware because the stub sets the request's user resolver, not the auth guard, so `auth` would have refused even the demo user. With real authentication, it becomes `auth` plus a redirect to login.
    - **Fail fast.** Validate at the boundary before any work, use guard clauses, no silent fallbacks, and database constraints as the last line of defence.
    - **Validate constantly.** A Form Request on every write, re-checks at each trust boundary (a queued job confirms the listing is still live), and tests for the rejections as well as the happy path.
    - **Tests follow AAA (Arrange, Act, Assert):** three clear blocks in that order, exactly one action under test, one behaviour per test. A failing test then says exactly what broke, and tests read as a specification of the behaviour. The existing tests already separate their blocks this way.
    - **Strongly typed.** Types on every method and property, enums instead of strings, typed request access, PHPStan generics. I'd recommend `declare(strict_types=1)` across the codebase, but the existing files don't use it, so I'm flagging it rather than adding it to new files only.
    - **On a real project I'd type more strictly than this codebase allows.** `declare(strict_types=1)` everywhere; PHPStan at a much higher level than 5 (towards `max`); typed DTOs or value objects for event payloads and props instead of `array` (the outbox payload is a plain array here); and generated TypeScript types for the Inertia props, so the front end is checked against the back end at build time. I kept to the codebase's current level, so the new code doesn't look unlike the old.
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

### Feature: UI and UX

Decided in a second grilling session, focused on the UI.

- **Street's header on every page:** navy bar with the Street logo and a **Listings** link on the left; on the right, a **bookmark icon** for Saved searches, a **bell** for Alerts (with the unread count), and a small **initials avatar**. (It evolved as I reviewed it in the browser: first "Welcome back, {name}" with text nav links, then a centred nav with name, avatar and bell, then this. Icons next to the account are the pattern buyers recognise from other apps, and the bar fits on a phone without a menu.)
- **Rebrand scope:** brand tokens on the shell, the new pages and the new filter controls. The existing listing card and detail page get a light token swap only, no layout changes ("not visual polish").
- **Regions and property types as pill-style chips:** native checkboxes underneath (a `fieldset` and `legend`), so they're accessible with no library.
- **Price and bedroom ranges as preset dropdowns** ("No min", £50k, £75k… "No max"). The presets come from the backend (data-driven), and each max hides values below the chosen min, so an invalid range can't be picked.
- **Filters keep the existing "Search" button** and URL-driven state.
- **"Save this search" next to the results count,** with a plain-words summary of what will be saved. States: disabled with a hint when nothing is filtered, "Already saved ✓", and an inline success message (announced to screen readers) with a link to Saved searches.
- **Deleting a saved search:** an inline two-step confirmation in place, with no browser dialog. Undo is noted for later.
- **Alerts reuse the listing card,** with a "New" pill when unread, a "Matched: …" line, and a muted "No longer available" state with no link. The empty state explains alerts and links to saving a search.
- **The header floats, like street.co.uk's,** staying at the top while the page scrolls, for consistency with Street's own site. A sticky header has an AA catch: WCAG 2.2's **2.4.11 Focus Not Obscured** fails if a keyboard user tabs to something the header then covers. So the page reserves scroll padding the height of the header, and focused elements and the skip-link target always land below it.
- **Accessible tooltips on the header icons.** I noticed the bookmark, bell and avatar had no visible labels. Their accessible names were already in place for screen readers (so not an AA failure in itself), but a sighted user who doesn't recognise an icon got no hint. Rather than a `title` attribute (which doesn't show on keyboard focus or touch, and screen readers handle it inconsistently), the tooltips meet **WCAG 1.4.13**: they show on hover and keyboard focus, Escape dismisses them, you can move the pointer onto them, and they stay until you leave.
- **Filter options arrive alphabetically from the API.** Regions already did (the query sorts them). Property types were coming in the enum's declaration order (Detached, Semi-detached, Terraced, Flat, Bungalow), so `PropertyType::options()` now sorts by label. The order is decided once, on the backend, and the UI renders what it receives, data-driven like the options themselves.
- **Filters in a big navy card across the top,** with price, bedrooms and bathrooms in a three-column grid, and Search and Clear aligned to the right, clearly separating "what you're searching for" from the results below, and echoing the marketing site's navy band. (Originally planned as a left-hand panel; I changed it when reviewing the page, for stronger separation and one layout that works the same on mobile.)
- **"View homes" on each saved search** opens the listings page with its filters. It's just the URL, since filters live in the query string.
- **An empty result invites saving the search:** "No homes match yet. Save this search and we'll alert you when one does." That turns a dead end into capture, the moment the feature exists for.
- **Consistent pagination:** Alerts and Saved searches both paginate at 15, the same as the listings page, using the existing `Pagination` component.
- **Placeholder listing photos,** so the cards look like a real property site. They're free-licensed Unsplash images, assigned to listings deterministically by ID in the Resource (no schema change), decorative `alt=""` (they aren't photos of the actual home), sized and lazy-loaded so they don't hurt LCP or CLS. The backend sends only the image URL; aspect ratio is handled in the UI, where the card's image box has a fixed ratio from one named Tailwind token with `object-cover`. Space is reserved before the image loads (no layout shift), and every card crops the same whatever the photo's shape. Hard-coding each photo's pixel size in the backend wouldn't be data-driven. They're WebP at native size (no upscaling), about 218 KB for all five instead of about 1.7 MB of PNG. In production, real photos would come from the listing data (an upload or a CDN).
  - *A licensing check that mattered:* the first set I gathered were iStock images, one with a watermark, and two of the second set turned out to be sponsored iStock results inside Unsplash search. They're identifiable by iStock's 612px preview size. Those aren't free to redistribute in a public repo, so they were excluded. Only images under the Unsplash licence are used. It doesn't require attribution, so I haven't added a credits file for placeholders.
- **Check contrast with browser extensions like Dark Reader switched off.** Dark Reader inverted Street's white logo back to dark on the navy header, which looked like a bug in the app but wasn't. Extensions that restyle pages also skew WAVE's contrast results, so accessibility checks are only valid against the page as built. I chose not to add Dark Reader's opt-out meta tag; a visitor's own extension choices are theirs.
- **Dependency versions need updating.** `npm audit` flags a high-severity advisory in Vue's server renderer (fixed in 3.5.42). This app doesn't server-render, so it isn't exposed, and I didn't bump it mid-feature. On a real project I'd keep dependencies current on a schedule (Dependabot or Renovate, see "With more time"), rather than leave it to whoever notices an audit warning.
- **Well-structured semantic HTML:** each element means what the content is. Landmarks (`header`, `nav`, `main`, `aside`), ordered headings, lists for results, saved searches and alerts, `article` cards, buttons for actions and links for navigation, labelled forms with `fieldset`/`legend`, `dl` for criteria, `time` for dates, and ARIA only where native HTML falls short. It's the foundation of accessibility, and tests that query by role enforce it.
- **Decided after the product owner's review and the RUM baseline (P1–P8):**
  - **No summary text next to Save on the listings page.** I first had the backend send a plain-words summary of the current search to show beside the Save button. Seeing it in the browser, with five regions and five types it ran across the whole row, and it only repeated what the filter card already shows. I removed it, and the backend prop with it, rather than abbreviating it. The Save button now sits on the same row as the results count. Saved searches still get their plain-words description, where it's the only way to recognise a search.
  - **Once saved, one line: "✓ Search criteria saved · View Saved Searches",** and keyboard focus moves to that link, because the Save button the user just pressed disappears and focus would otherwise drop to the page body. This replaced a separate "Already saved" label plus a "Search saved. View saved searches" message: one clear confirmation that also says where to find it, announced to screen readers.
  - **Save saves the filters actually applied** (the URL), not half-edited panel values, so what's saved matches the results on screen and the "Already saved" check.
  - **A non-preset price or bedroom value from a link shows as an extra, selected option** in its dropdown, so the control never misrepresents the filter that's actually applied.
  - **Honest copy: "We'll add new matching homes to your Alerts."** Alerts are in-app only, and there's no backfill, so "We'll alert you" would imply email or push we don't send, and that older homes will arrive. (I first chose "We'll alert you", then changed my mind: it's a trust risk Support has already flagged.)
  - **Saving and deleting keep one shared rate limit** (10 a minute). The product owner suggested a separate, looser limit for deletes. I kept one limit: it's simpler, and tidying more than 10 searches a minute is an edge case. **The limit is bound to the account**, not the IP, so one user can't get round it by changing networks, and one heavy user can't use up the allowance of others behind a shared IP (an office or mobile network). Combined with the 20-search cap, this bounds what a malicious account can do.
  - **Deleting the last search on a page lands on the last page that still has searches,** never a false "no saved searches".
  - **Saved searches shows usage, e.g. "3 of 20 saved",** so buyers see the limit before they hit it.
  - **Viewing alerts is a safe GET; marking them read is an explicit PATCH.** Originally `GET /alerts` marked alerts read as a side effect, which breaks HTTP semantics (GET must be safe) and made prefetching the bell dangerous: hovering it would have marked alerts read unseen. Now the page loads with no side effects, and once it's shown it sends `PATCH /alerts` with the IDs of the alert cards displayed (`{ alert_ids: [...], read: true }`). PATCH, not POST or DELETE: a read alert isn't removed (it stays on the page and its history feeds "First alerted"), it has one field changed. One request per page view, every ID validated as the user's. The bell can now prefetch.
  - **One card per home on the Alerts page:** the latest alert, with earlier ones summarised ("First alerted 3 Nov"). A re-listed home reads as news, not a duplicate, and "notify again" still holds.
- **Fixes from the RUM baseline:** "Back to all listings" keeps the buyer's filters and page; a skip link to the results; filter URLs always comma-separated (today's form produces `region[0]=` URLs that leave the Area select blank while still filtering); bad-link errors shown on the page (today `?region=Atlantis` silently shows every home); price is no longer a free input with a £10k step that blocks real budgets.
- **UI tests:** Vitest for components, and axe-core inside Vitest at WCAG 2.2 AA on every page, both blocking in CI. Manual checks with the Headers and WAVE extensions.
  - **Playwright: I'd use it, but not here.** On a real project I'd add Playwright for end-to-end flows, real-browser keyboard checks and full axe runs including colour contrast. Here it needs a separate browser download, and **the person reviewing this should be able to get in and use it easily**: `composer install`, `npm install`, run. axe in Vitest keeps automated accessibility checks with no extra setup. jsdom can't measure contrast, so that's covered by the token contrast check and a manual WAVE pass.

### Feature: backend

Decided in a grilling session (the AI questions me, I decide).

- **"Becomes live" = the status changes to live.** That covers publishing a draft, creating a listing already live, and coming back to live after under offer falls through. Edits to an already-live listing (e.g. a price drop) don't alert; that's a separate feature.
- **Criteria: a price range (min–max), a bedrooms range (min–max), property types, regions.** I've looked at other property sites, and a range is key: a buyer looking at £100k–£200k isn't interested in anything under £100k. All criteria are optional, and matching shares its definition with the search page, so what you see when searching is what you get alerted about.
- **One alert per home per user each time it goes live,** even if several saved searches match. A unique constraint in the database enforces it. This is the direct answer to Support's "please don't spam people".
  - *At scale:* with heavy listing churn, generating and sending alerts one at a time won't hold. I'd batch them (e.g. collect matches and send a periodic digest), and could offer buyers a choice of instant or digest. That's a question of how the notification infrastructure is built for mass delivery, not something to build here.
- **No backfill.** Only listings that go live after a search is saved create alerts. Backfill would flood new users on day one. It's also an abuse risk: a malicious user saving huge numbers of broad searches would trigger a burst of matches against every live listing.
- **Alerts stored in their own table,** not Laravel's notifications table: a typed model, real foreign keys, a unique constraint, read/unread.
  - *At scale:* the link between alerts, saved searches and listings becomes a **many-to-many record of interest**, which supports point-in-time analysis both ways: how much interest a property attracts, and which properties a user is interested in.
- **Saving a search:** a "Save this search" button on the listings page saves the filters currently applied. A **Saved searches** page shows each search's criteria in plain words (e.g. "Liverpool, £100,000–£200,000, 3+ beds") and lets the user delete it.
- **At least one criterion is required.** An empty search would match every new listing, which is spam by design.
- **Price and bedrooms are both ranges (min–max),** on the search page and in saved searches, with validation that min ≤ max. Someone who wants a 2-bed flat doesn't want 5-bed houses.
- **Saving the same search twice tells the user it already exists,** rather than creating a duplicate. It's checked in **the UI as well as the API**: the page already knows the user's saved searches, so it can show "Already saved" without a wasted request. The server still enforces it.
- **Regions and property types are both data-driven multi-selects.** Type options come from the `PropertyType` enum; region options come from the data (the distinct regions of our branches), never a hard-coded list. The API accepts only those known values, so the UI and API agree.
  - *Why data-driven is the focus:* most UI → API bugs happen when the front end and back end each keep their own copy of the rules: a hard-coded option the API no longer accepts, a label that drifts from the enum, a region that exists in one list but not the other. Here every option the UI offers comes from the same source the API validates against (the enum, the branches table), sent as props. Add a region or a property type and both sides pick it up at once, with no front-end change and no chance of offering something the API will reject. The same goes for "Already saved": the UI checks against the user's real saved searches, and the API enforces the same rule.
- **"No homes right now" is not an error.** A buyer can save Newcastle even if no Newcastle homes are live today. The page explains that nothing matches *yet* and that they'll be alerted when something does. Inputs are still checked for shape: whole numbers in range, min ≤ max, known types and regions.
  - *Where I overruled the AI again:* it proposed rejecting regions with no current listings ("fail fast"). Technically tidy, commercially wrong: an empty result today is exactly why someone saves a search. Fail fast applies to malformed input, not to "no results yet".
- **The filter changes are backwards compatible.** Filters live in the URL, so people already have links shared, bookmarked and in their history. None of them break:
  - **Old links still work unchanged.** `?region=Leeds&property_type=detached&max_price=300000&min_bedrooms=2` returns the same homes as before. A single value is just a list of one. The original `ListingPageTest` filter tests still pass, and `test_old_single_value_links_still_work` pins it.
  - **New parameters are additive and optional.** `min_price`, `max_bedrooms` and multiple values are new; leaving them out means "any", exactly as before. Nothing that used to be accepted is now rejected for being old.
  - **Every format is read, one is written.** Comma lists (`region=Leeds,Liverpool`), array style (`region[]=Leeds`) and single values are all accepted, and so is any casing (`leeds` → `Leeds`). The app always produces the canonical comma form, so links get tidier over time without old ones failing.
  - **Stale or broken links degrade gracefully.** A region that no longer exists is dropped with a visible message, keeping the valid filters, rather than producing an error page or silently showing everything.
  - **Saved searches don't depend on URL format.** They're stored as typed criteria (columns and join tables), not query strings. Their "View homes" link is generated from the criteria each time, so changing the URL format later can't break a saved search.
  - **Future criteria won't break existing searches.** A new criterion is a nullable column meaning "any", so every existing saved search keeps matching exactly what it did.
- **Bathrooms are searchable (min–max), added mid-build.** Every listing already had a `bathrooms` count, and the cards showed it, but buyers couldn't filter on it: data we held and didn't let people use. I had it built rather than left as a note, following the bedrooms pattern exactly, and it proved the backwards-compatibility design above: two nullable columns on `saved_searches` (null = any, so existing saved searches are unaffected), a range in `SearchCriteria`, the match scope and its inverse, validation, presets and the description ("2+ baths"), plus the parity test to keep matching in step.
  - **One honest exception:** the `filters` prop echoed to the front end changed shape. Numbers are now numbers (`300000`, not `"300000"`), and region and type are lists. That's an internal contract between our own backend and front end, updated together and covered by tests, not something users or bookmarks rely on.
- **Several regions and several property types per search,** stored in join tables (portable SQL, indexable; no JSON columns that behave differently across engines). A buyer might look in Liverpool *and* Newcastle, for detached *or* semi-detached.
  - *Where I overruled the AI (the first time):* it recommended one property type per search, reasoning systemically (fewer tables, simpler matching). Commercially that's wrong: buyers are interested in several types, and the product should serve how they actually search. I identified and corrected this. It's a reminder that the AI optimises for system simplicity unless someone brings the commercial view.
- **The search page takes several values too,** comma-separated in the query string (`?region=Liverpool,Newcastle`); old single-value links keep working.
  - *At scale:* as criteria grow into hundreds of combinations, query strings hit length limits. I'd store a search's filters server-side under a short unique key, so a saved or shared search loads by key and is unpacked server-side, retrievable at scale. Not needed at this size.
- **One alert per home per go-live, across all of a user's searches,** linked to **every** saved search it matched (a many-to-many join table, not polymorphic: alerts are only about listings for now). It's deduplicated, but the buyer still sees which search or searches the home relates to.
- **20 saved searches per user, and rate-limited saves.** This is an artificial starting limit to bound abuse and matching cost, to be raised as product feedback comes in.
- **Alerts page:** newest first, unread highlighted, an unread count in the header. Each alert says why it matched, and shows "No longer available" if the home has since gone under offer or sold.
  - **"Read" means seen:** only the alerts on the page being viewed are marked read, and each one's unread state is captured first so its "New" pill still shows. The header count updates after.
  - **An alert whose searches have all been deleted** still says why, honestly: "Matched a search you've since deleted".
- **A re-listed home alerts again, labelled "Back on the market".** If a home goes live, then under offer, then live again, matching buyers are told again. A sale falling through is exactly the moment a buyer who missed out wants to know. (The AI first recommended staying silent to protect "one alert per home"; I overruled it on commercial grounds.)
  - *How it stays spam-free:* alerts are unique per user **per go-live event** (keyed by the outbox message), not per home. So several matching searches still produce one alert, and a repeated message still changes nothing, but a genuine re-listing is a new event. An `AlertReason` enum (`new_listing` / `back_on_market`) records why.
  - *Flip-flop guard:* if the buyer still has an **unread** alert for that home, it's refreshed (moved to the top, shown as available) rather than duplicated, so a listing toggling between live and under offer can't flood anyone.
- **Matching uses the saved searches that exist when the consumer runs,** not a snapshot from the moment of going live. The gap is under a minute; the worst case is a search saved in that minute hearing about a home just listed, which is harmless.
- **Duplicate searches are compared after normalising:** order doesn't count (Liverpool,Newcastle = Newcastle,Liverpool), blanks count as absent, and paging is ignored. The UI's "Already saved" and the server use the same comparison.
- **Bad or stale filter links** (a region that no longer exists, a hand-edited URL) are still rejected, but the buyer sees a clear message on a working page, never a broken one.
- **Friendly limits:** the cap and the rate limit come back as plain messages ("You have 20 saved searches. Delete one to save another."), never a raw error page.
- **Deleting a saved search keeps its alerts;** only the link to that search goes. Deleting means "stop telling me", not "forget what you told me".
- **No listing-management screen.** A documented `tinker` one-liner sets a listing live for demos; the feature tests prove the flow.
- **Transactional outbox for "listing went live".** The status change and an outbox message are written in the **same database transaction**, so a listing can never go live without the message, and no message can exist for a change that rolled back. A relay then publishes the message (in production, to an **SNS topic**), and a separate consumer generates the alerts.
  - **This outbox is a demonstration, not how I'd build it for real.** It's here purely to show how the pattern works inside this project: the atomic write, at-least-once delivery, an idempotent consumer, and re-checking state on consume. Everything runs in one Laravel app, with a polling artisan command standing in for the relay and the consumer called in-process. In the real world I'd architect it differently, with event publishing and alert generation outside the web app, on infrastructure built for messaging and fan-out at scale, and with alerting and dead-letter handling as first-class parts of the design rather than notes.
  - There are plenty of producer and subscription models to choose from: **Kinesis**, **Kafka**, or a simple **SNS → SQS** fan-out. The right one depends entirely on what's needed (throughput, ordering, replay, how many consumers, cost), so that choice is out of scope for this task.
  - *Why:* alert generation leaves the request and the listing write path entirely. That removes contention on hot data when lots of listings change at once, and lets alerting scale on its own infrastructure.
  - *Built here:* the outbox table (written in the same transaction as the status change), and an `outbox:relay` command that hands each message to the alert consumer in-process, standing in for SNS and its subscriber. It's scheduled every minute for this exercise, and can be run by hand for demos. The SNS publisher is described, not built.
  - *The message is small:* a `listing.went_live` event with the key IDs and a timestamp only. The consumer reloads the listing and re-checks it's still live before alerting. At larger scale (very large subscription sets per event), fan-out may need other methods, e.g. partitioned consumers or pre-computed subscription indexes.
  - *Failures (documented, not built; theoretical at this size):* a failed message would stay unsent, recording its attempt count and last error, and be retried on the next run. After a set number of attempts (e.g. 5) it would be moved aside as failed, like a dead-letter queue, so one poison message can't block the rest. I'd **alarm on failed messages** (e.g. a CloudWatch alarm on the dead-letter count) so they're looked at quickly, and provide a way to **re-run** them once the cause is fixed. Sent messages would be cleaned up after a retention period (e.g. 30 days). In this exercise the relay simply leaves a failed message unsent, so the next run retries it.
  - *Delivery is at-least-once,* so the consumer must be idempotent. The unique (user, go-live event) constraint and insert-or-skip make a repeated message harmless.

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

*More to add once the feature exists.*

- **Broad searches can still be noisy.** A search for just "Liverpool" alerts on every Liverpool home. Alerts are in-app only and deduplicated, but at volume this needs digests or relevance limits.
- **The alert card relies on the controller to attach its history** (first alerted, alert count, unread state). It fails loudly if that's missing, rather than showing wrong data, but it's a coupling: any new place that renders alerts must remember to do it. With more time I'd load those counts through an Eloquent relationship.
- **Engagement signals are thin.** Deleting a saved search is a hard delete, and alert click-throughs aren't recorded, so "searches deleted soon after an alert" (a trust-loss signal) and "alert → listing" conversion can't be measured yet.
