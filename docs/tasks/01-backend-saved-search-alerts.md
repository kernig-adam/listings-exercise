# Task 1 — Backend: saved searches, outbox and alerts

**Status:** designed (grilling session, decisions in [NOTES.md](../../NOTES.md)); product owner reviewed and changes accepted by Adam; ready for step 2 (data).
**Branch:** `feature/saved-search-alerts`. **UI:** separate task, after a UI/UX grilling session.

## Goal

A buyer can save a search, and when a listing **goes live** (or comes **back on the market**) and matches it, they get **one alert per home per go-live**. Generation runs through a **transactional outbox**, not the request.

## Agents and order

| Step | Agent | Model | Owns |
| --- | --- | --- | --- |
| 1 | `product-owner` | Opus | Review this task against buyer value before any code |
| 2 | `data` | Sonnet | Migrations, models, enums, factories, relationships, indexes and constraints |
| 3 | `backend` | Sonnet | Routes, Form Requests, controllers, Resources, the shared match scope, outbox write, relay command, alert consumer, feature tests |
| 4 | main session | Opus | Review the diff, run the four checks, show Adam, commit only on his go-ahead |

Agents edit only; they never commit. Tests are written first.

## Data (step 2)

- **`saved_searches`:** `user_id` (FK, cascade), `min_price`, `max_price`, `min_bedrooms`, `max_bedrooms` (nullable unsigned ints; null = "any"), timestamps. Index `user_id`.
- **`saved_search_regions`:** `saved_search_id` (FK, cascade), `region` (string). Unique (`saved_search_id`, `region`). Index `region`.
- **`saved_search_property_types`:** `saved_search_id` (FK, cascade), `property_type` (string, cast to `PropertyType`). Unique pair. Index `property_type`.
- **`alerts`:** `user_id` (FK, cascade), `listing_id` (FK, cascade), `outbox_message_id` (FK, the go-live event that caused it), `reason` (string, cast to a new **`AlertReason`** enum: `new_listing`, `back_on_market`), `read_at` (nullable), timestamps. **Unique (`user_id`, `outbox_message_id`)**, which enforces one alert per home per go-live and makes reprocessing idempotent. Index (`user_id`, `listing_id`) for the "alerted before?" and unread-guard lookups.
- **`alert_saved_search`:** `alert_id` (FK, cascade), `saved_search_id` (FK, **cascade**, so deleting a search removes only the link and the alert stays). Unique pair.
- **`outbox_messages`:** `type` (string, e.g. `listing.went_live`), `payload` (JSON: key IDs plus `occurred_at`), `sent_at` (nullable), timestamps. Index `sent_at`.
- **Models:** `SavedSearch`, `Alert`, `OutboxMessage`, with `$fillable` (never `user_id`), `casts()`, typed relations with generics, `@property` docs, factories with states. `User` gets `savedSearches()` and `alerts()`.
- Portable SQL only (see the data agent's SQLite rules).

## Behaviour (step 3)

1. **One match definition.** Extract the listing filters into a model scope used by **both** the listings page and alert matching: price min–max, bedrooms min–max, property types (several), regions (several, via the branch).
2. **Search page:** add `min_price`, `max_bedrooms`, and comma-separated several values for `region` and `property_type` (`?region=Liverpool,Newcastle`). Old single-value links keep working. Validation: whole numbers in range, min ≤ max, types in the enum, regions in the branches' distinct regions.
3. **Saved searches:** list (paginated at 15, like listings and alerts), create and delete, scoped to `$request->user()`.
   - Create from validated criteria only. At least one criterion. A duplicate of an existing search returns a validation error ("already saved"), compared after **normalising**: order-insensitive sets for regions and types, blanks absent, `page`/`per_page` ignored. One normaliser is used by both the duplicate check and the "already saved" prop. A cap of 20 per user. Write routes throttled.
   - The cap and throttle return **friendly messages** the UI can show in place ("You have 20 saved searches. Delete one to save another."), never a raw 429 page.
   - The saved-search Resource supplies the plain-words description and the **"View homes" URL** (filters as a query string), so Vue never rebuilds how filters map to the URL.
   - Another user's search returns **404**.
4. **Going live → outbox.** When a listing's status **changes to `live`** (created live, published, or back from under offer), write an `outbox_messages` row **in the same transaction**. Nothing else happens in the request.
5. **Relay:** `php artisan outbox:relay` reads unsent messages, hands each to the consumer in-process (standing in for SNS), and marks them sent. Scheduled every minute in `routes/console.php`. A failure leaves the message unsent, so the next run retries it. Dead-lettering and alarming are documented in NOTES.md only.
6. **Consumer:** match against the saved searches that exist **when it runs**. Reload the listing and **re-check it's still live** (otherwise do nothing). Find every matching saved search in **one query**. For each matching user:
   - **Unread alert for this listing already exists:** refresh it (bump to the top, link any new matching searches), don't add another. This is the flip-flop guard.
   - **Otherwise:** insert-or-skip one alert keyed by (user, this message), with `reason = back_on_market` if the user has ever been alerted about this listing, else `new_listing`, and link **all** of that user's matching searches. Idempotent: running the same message twice changes nothing.
7. **Alerts:** list newest first (paginated at 15), with listing card data (including `image_url`), when it was alerted, the matched searches' descriptions (or an honest "matched a search you've since deleted" when none remain), and whether the listing is still live. **Capture each alert's unread state, then mark read only the alerts on the page shown.** The unread count is shared to every page (`HandleInertiaRequests`) and reflects the state after the view.
   - **Bad or stale filter values** on the listings page (unknown region or type, hand-edited URL) are rejected by validation, but the buyer lands on a working page with a clear message, never a blank page or a redirect loop.
   - Echo the exact filter values used, so the UI can show non-preset prices sensibly.
8. **Props for the UI task:** region options (data-driven) and type options (`PropertyType::options()`) on the listings and saved-search pages. **Price and bedroom preset options** for the range dropdowns, defined once on the backend, with validation accepting any whole number in range so presets can change freely. Whether the current filters are already saved (for the UI's "Already saved"). A plain-words description of each saved search comes from the Resource.

## Acceptance (feature tests, written first)

- Going live writes exactly one outbox message, in the same transaction. Edits to an already-live listing write none. A rolled-back change writes none.
- The relay plus consumer create one alert for a matching user. None for non-matching criteria, other users' searches, or a listing no longer live when consumed. One alert (linked to both searches) when two searches match. Running the same message twice creates no duplicate.
- No backfill: saving a search creates no alerts for listings that are already live.
- Saved searches: create, list and delete work; validation rejections each get a test (empty, min > max, unknown type or region, duplicate, over the cap); another user's search returns 404 for view and delete.
- Search page: ranges, several regions and types, old single-value links, blank values treated as absent.
- Alerts: scoped to the user, newest first, "no longer available" flag, unread count shared. Viewing page 1 marks only page-1 alerts read, and they still show as new in that response. An alert with all its searches deleted still lists and explains itself. Alerts expose their `reason` (for the UI's "Back on the market" label).
- Re-listing: live → under offer → live creates a **second** alert with reason `back_on_market` for a user whose first alert was read. If their first alert is still unread, it's refreshed instead (still one alert). Reprocessing the same message creates nothing new.
- Duplicate detection: `Newcastle,Liverpool` equals `Liverpool,Newcastle`, and blanks and paging are ignored.
- Description: a Resource test per shape ("Up to £200,000", "From £100,000", "Any price", "2–3 beds", "3+ beds", several types and regions in a consistent order).
- Bad filter link: an unknown region in the listings URL returns a usable page with a clear error, not a crash or a loop.
- Cap and throttle: friendly messages, not a raw 429.
- All four checks pass: tests with coverage ≥ 90%, Pint, PHPStan, build.

## Could (if cheap)

Seed one demo saved search for the demo user, so a reviewer doesn't start on empty pages.

## Out of scope (in NOTES.md)

The SNS publisher, dead-letter and alarming, digests and batching, a price-drop alert, query-string keys for large searches, listing-management screens.

## Demo

```bash
php artisan tinker --execute="App\Models\Listing::where('status','draft')->first()->update(['status' => 'live', 'listed_at' => now()]);"
php artisan outbox:relay
```
