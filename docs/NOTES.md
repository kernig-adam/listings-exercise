# Notes

The half-page version. Every decision, with its reasoning, is in [docs/DECISIONS.md](DECISIONS.md); how the work was done, step by step, is in [PROCESS.md](PROCESS.md).

## See an alert, from a fresh install

Rehearsed on an empty database. Install and run as the README says (`composer install`, `npm install`, `php artisan migrate --seed`, then `php artisan serve` and `npm run dev`). Seeding creates a demo saved search, "Up to £400,000 · 2+ beds". Then publish a matching draft home and run the relay:

```bash
php artisan tinker --execute="App\Models\Listing::where('status','draft')->where('price','<=',400000)->where('bedrooms','>=',2)->firstOrFail()->update(['status'=>'live','listed_at'=>now()]);"
php artisan outbox:relay
```

The bell shows **1**; **Alerts** shows the home as "New", with "Matched: Up to £400,000 · 2+ beds". To see a sale fall through, after viewing it run the same `update` with `'under_offer'`, then `'live'`, on `App\Models\Alert::firstOrFail()->listing`, and relay again: the home comes back as "Back on the market". (If the random seed has no matching draft, `migrate:fresh --seed` and retry.)

## Key decisions and trade-offs

- **AI-native, but directed and checked.** I defined four specialist Claude agents (product owner, UI, backend, data), had Claude **grill me** before building (separate product and UI sessions), and reviewed every result myself, in the code and in the browser, before approving a commit. Review caught real bugs the agents reported as done. See [How I worked with Claude](DECISIONS.md#how-i-worked-with-claude-directing-questioning-evaluating), and [How I'd re-architect this](DECISIONS.md#how-id-re-architect-this-for-a-real-product) (containers first: a native Windows install took [all this](DECISIONS.md#a-native-windows-install-what-it-actually-took)).
- **A "match" is every criterion the buyer set,** with empty meaning "any". Price, bedrooms and bathrooms are **ranges**; region and property type allow **several** values. Search and alerts share one definition (`SearchCriteria`, `Listing::scopeMatching`), with a parity test so they can't drift. Options come from the data (enum, branches), so the UI can't offer what the API rejects.
- **"Becomes live" is a status change to live,** whether a draft is published or a sale falls through. It's written to a **transactional outbox** in the same database transaction, then relayed to a consumer, so alerting never slows or breaks the listing write. Delivery is at-least-once, so the consumer is idempotent.
- **Don't spam (Support's ask):** one alert per home per go-live, linked to every search it matched. An unread alert is refreshed rather than repeated. **No backfill.** A re-listed home *does* alert again ("Back on the market"), because a fallen-through sale is exactly when a buyer wants to know.
- **HTTP semantics:** viewing alerts is a safe GET; marking them read is a PATCH. That lets the bell prefetch.
- **Security:** user-scoped routes need a user (401), and records load through the user (another user's record is a 404). Validation at the boundary, limits per account.
- **Logging and monitoring:** system logs (what each go-live produced, relay runs, failures with stack traces), security warnings, and **user-activity events on their own channel** (searches, views, saves, alert click-through), so we can see how buyers use the product. IDs and counts only, never personal data; in production this would feed an analytics pipeline with consent and retention handled.
- **Accessibility and brand:** WCAG 2.2 AA (axe in tests; Headers and WAVE checks by hand), semantic HTML, Street's brand as Tailwind tokens only.
- **Worked with the codebase:** native PHP as the README defines, though I'd use Docker. I flagged problems rather than working around them: `composer dev` fails on Windows, leftover skeleton CI workflows, SQLite vs production.

## Deliberately left out

Sending email or push; real message infrastructure (SNS/SQS, dead-letter queues); digests; price-drop alerts; listing-management screens; Playwright (to keep setup to `npm install`); Docker and Laravel Boost (new dependencies the codebase doesn't have).

## With more time

A production-engine CI job; vulnerability checks (`composer audit`, `npm audit`, Dependabot); collapsible filters on mobile; patch coverage on PRs; mechanical "Claude gates" that stop the AI acting outside its task.

## Where it won't hold up yet

Matching runs one set-based query per go-live, which won't scale to millions of saved searches without partitioning or pre-computed subscriptions. The relay is a single polling process. Tests run on SQLite, not the production engine. Broad searches can be noisy. The 20-search and rate limits are guesses, not data. Activity logs go to a file here; real usage monitoring needs an event pipeline and dashboards.
