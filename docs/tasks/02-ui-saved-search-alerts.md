# Task 2 — UI: brand shell, filters, saved searches and alerts

**Status:** designed; product owner reviewed; RUM baseline captured at `8273f09` (PROCESS.md entry 18); decisions P1–P8 made by Adam. Next: step 1b (backend prep), then step 2 (UI).
**Depends on:** [Task 1 (backend)](01-backend-saved-search-alerts.md) for routes and props.
**Branch:** `feature/saved-search-alerts`.

## Goal

Buyers can filter by ranges and several regions and types, save a search in one click, manage their saved searches, and see their alerts, all in Street's brand, fully keyboard and screen-reader accessible (WCAG 2.2 AA).

## Agents and order

| Step | Agent | Model | Owns |
| --- | --- | --- | --- |
| 1 | `product-owner` | Opus | Review the flows for buyer value before code, plus the RUM baseline ✅ |
| 1b | `backend` | Sonnet | Backend prep the UI needs (below), tests first |
| 2 | `ui` | Sonnet | Theme tokens, shell, pages, components, Vitest + axe tests |
| 3 | `product-owner` | Opus | Use the built flows in Chrome (RUM-style: vitals, errors, friction) and report |
| 4 | main session | Opus | Review the diff, run the checks, show Adam, commit only on his go-ahead |

Agents edit only; they never commit. Styling: Tailwind token classes only, no inline styles, no `<style>` blocks, no arbitrary values, no `!important`.

## Step 1b: backend prep (before the UI agent)

- **P1:** the listings page gets a `searchDescription` prop (`SearchCriteria::description()` of the applied filters; null when nothing is filtered).
- **P6:** deleting a saved search redirects to the last page that still has searches (never a false empty page).
- **P7:** the Saved searches page already gets `savedSearchLimit`; add the user's total (`savedSearchCount`), so "3 of 20 saved" isn't computed from one page.
- **P8:** the Alerts page shows **one card per home**: the latest alert, plus `first_alerted_at` and `alert_count` for that home. Pagination is by home. Viewing marks read every alert for the homes shown. The unread count counts homes with unread alerts, so it matches what the buyer sees.
- **Images:** move the 5 photos to `public/images/listings/`, convert them to WebP at native size, and add `image_url` to `ListingResource` (picked deterministically by `id % count`).
- **P5 (no change):** save and delete keep one shared 10-a-minute limit.

## Brand and shell

- **Tokens** in `resources/css/app.css` `@theme`, from Street's marketing site (navy `#0C0D17` header, cream canvas, square white CTA, column dividers, underlined text links) and dashboard (teal primary, light teal active, rounded white cards with soft shadows, status pills with text). No glass. Every text pair at least 4.5:1.
- **Logo:** vendor `https://web2026.street.co.uk/images/street-logo.svg` into `resources/images/`. Inline it, white on navy via a named `@utility` (it uses `fill="var(--fill-0, …)"`). Accessible name, links home.
- **Header (every page):** logo and a **Listings** link on the left · on the right, a **bookmark icon** (Saved searches), a **bell** (Alerts, unread badge, accessible name e.g. "Alerts, 3 unread", no prefetch), and an **initials avatar** ("Signed in as {name}" for screen readers), **always the rightmost item at every width**.
- **Mobile:** everything fits in one row at 320px (the logo already links to Listings); a menu only if something genuinely doesn't fit.
- **Existing listing card and detail page:** a light token swap only (colours, radius), no layout changes.

## Listing images (placeholders)

- **Source:** free-licensed Unsplash photos, prepared in step 1b as WebP in `public/images/listings/` (replace or extend only with images under the Unsplash licence, never sponsored iStock results).
- **Assignment is data-driven:** `ListingResource` adds `image_url`, picked deterministically from the listing ID (`id % count`), so a listing always shows the same photo. No schema change, nothing decided in Vue.
- **Accessibility:** decorative (`alt=""`). They aren't photos of the actual home, and the card's address and details already describe it.
- **Aspect ratio is a UI concern:** the backend sends only `image_url`. The card's image box has a fixed aspect ratio from a named Tailwind token in `@theme` (e.g. `--aspect-card: 3 / 2` → `aspect-card`) with `object-cover`, so the space is reserved before the image loads (no layout shift, CLS), and every card crops the same whatever the photo's shape. No arbitrary `aspect-[…]` values.
- **Performance:** `loading="lazy"` below the fold, `decoding="async"`.
- Used on listing cards (listings, alerts) and the detail page.

## Listings page

- **Filters in a big navy card, full width across the top** of the page, clearly separate from the results below (echoing the marketing site's navy band). Filters are laid out in a responsive grid inside it, stacking on mobile. Everything inside must meet AA **on navy**: light text, light chips with a clear selected state, `focus-ring-inverse`, and inputs/selects legible on the dark card.
  - **Regions and property types:** pill-style chips over native checkboxes (`fieldset` + `legend`). Options from props (data-driven), never hard-coded.
  - **Price, bedrooms and bathrooms:** min/max **preset dropdowns** from props (`priceOptions`, `bedroomOptions`, `bathroomOptions`) ("No min" … "No max"). The max hides values below the chosen min. **P3:** a non-preset value in the URL (e.g. £180,000) appears as an extra, selected option.
  - The existing **"Search" button** applies filters to the URL. **Always comma-separated** (`region=Liverpool,Newcastle`), never `region[0]=` (the baseline found that form leaves the select blank while filtering).
  - **Field errors from a bad link** (e.g. `?region=Atlantis`) are shown next to their field. The baseline showed every home silently.
  - A **skip link** to the results, before the filters.
- **"Save this search"** next to the results count, with the backend's `searchDescription` (P1) as the summary. **P2:** it saves the **applied** URL filters, never unsubmitted panel values. States:
  - disabled when nothing is filtered, with the hint as **visible text** (a disabled button can't take focus)
  - **"Already saved"** from `currentSearchSaved` (no request needed). The "✓" is `aria-hidden`.
  - saving (disabled)
  - success: `flash.status` in a polite live region, with a link to Saved searches
  - error: the `criteria` message next to the button, tied with `aria-describedby` (cap, duplicate, rate limit)
- **Empty results (P4):** "No homes match yet. Save this search and we'll add new matching homes to your Alerts.", with the Save button.
- **Detail page:** "Back to all listings" returns to the buyer's filtered results and page, not a bare `/`.

## Saved searches page

- Paginated at 15. **P7:** shows "3 of 20 saved" (from `savedSearchCount` / `savedSearchLimit`). Each search shows its plain-words description and its criteria as chips.
- **"View homes"** link (`view_url`). **Delete:** an inline two-step confirmation ("Delete this search?" Delete / Cancel). After deleting, focus moves to the next search, or to the heading if none is left (never `body`), and `flash.status` is announced.
- Empty state explains saved searches and links to the listings page.

## Alerts page

- **P8: one card per home**, paginated at 15, newest first. The card reuses the listing card, with a **"New"** pill when unread, the reason pill from `reason_label` (e.g. "Back on the market"; text, not colour alone, never hard-coded), a **"Matched: …"** line (or "Matched a search you've since deleted" when `matched_deleted`), and "First alerted {date}" when the home has had more than one alert.
- **No longer available** (`is_available` false): a muted card with a text label, and **no link**. `ListingCard` needs a non-link variant.
- **Marking read (2b backend first):** `GET /alerts` stops marking alerts read (no side effects; `is_new` reflects the real unread state). New route `PATCH /alerts` (named, behind `RequireUser`, throttled) with `{ alert_ids: int[], read: true }` and a Form Request: ids required, distinct, at most one page (15), each must be one of the **user's** alerts (others → 404/422, never touched). It marks read every alert for those alerts' homes (consistent with one card per home), then redirects back. Tests first.
- **UI:** once the Alerts page is shown, it sends one `PATCH` with the displayed cards' alert ids, using `preserveState`, `preserveScroll` and `only: ['alerts']`, so only the shared unread count refreshes and the "New" pills stay visible for this view. The bell link **may now prefetch**.
- Empty state explains how alerts work and links to saving a search.

## Semantic HTML

The right element for the job (see the UI agent): `header`/`nav`/`main`/`aside` landmarks, ordered headings, `ul`/`li` for results, saved searches and alerts, an `article` per card, `button` for actions and `a` for navigation, `form`/`fieldset`/`legend`/`label` for filters, `dl` for criteria, `time` for dates, and ARIA only where native HTML can't do it. Tests query by role, so poor semantics fail them.

## Accessibility (WCAG 2.2 AA)

One `h1` per page, no skipped heading levels, visible focus, 24×24px minimum targets, labels on every control, errors tied with `aria-describedby`, status messages in live regions, colour never the only signal, works at 320px with no horizontal scroll.

## Tests (blocking in CI)

Easy to run is a requirement: everything installs with `npm install` and runs with `npm test`. No browser downloads or extra setup for a reviewer.

- **Vitest + Vue Test Utils (jsdom):** chips toggle and serialise to the query, min/max dropdown filtering, Save button states, the delete confirmation flow, alert card states (new, matched, no longer available), and the Menu and Filters disclosures (`aria-expanded`, Escape). Keyboard interaction via dispatched key events. Query by role and label.
- **axe-core inside Vitest, WCAG 2.2 AA rules:** each page component (Listings, Saved searches, Alerts, including empty states) renders with zero violations. jsdom can't compute colour contrast, so contrast is covered by the token contrast check (every pair ≥ 4.5:1) and the manual WAVE pass.
- CI: add an `npm test` step. npm script: `test`.
- Manual: the Headers and WAVE Chrome extensions on each page before hand-back.
- **Not used: Playwright.** I'd use it on a real project for end-to-end flows, real-browser keyboard checks and full axe runs including contrast. Here its browser download would make the project harder for a reviewer to run (see NOTES.md).

## Out of scope (in NOTES.md)

Undo for delete, a redesign of existing listing pages, glass effects, server-side keys for large saved filters.
