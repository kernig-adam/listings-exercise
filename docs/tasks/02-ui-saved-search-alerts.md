# Task 2 — UI: brand shell, filters, saved searches and alerts

**Status:** designed (UI/UX grilling session, decisions in [NOTES.md](../../NOTES.md)); not started.
**Depends on:** [Task 1 (backend)](01-backend-saved-search-alerts.md) for routes and props.
**Branch:** `feature/saved-search-alerts`.

## Goal

Buyers can filter by ranges and several regions and types, save a search in one click, manage their saved searches, and see their alerts, all in Street's brand, fully keyboard and screen-reader accessible (WCAG 2.2 AA).

## Agents and order

| Step | Agent | Model | Owns |
| --- | --- | --- | --- |
| 1 | `product-owner` | Opus | Review the flows for buyer value before code |
| 2 | `ui` | Sonnet | Theme tokens, shell, pages, components, Vitest + axe tests |
| 3 | `product-owner` | Opus | Use the built flows in Chrome (RUM-style: vitals, errors, friction) and report |
| 4 | main session | Opus | Review the diff, run the checks, show Adam, commit only on his go-ahead |

Agents edit only; they never commit. Styling: Tailwind token classes only, no inline styles, no `<style>` blocks, no arbitrary values, no `!important`.

## Brand and shell

- **Tokens** in `resources/css/app.css` `@theme`, from Street's marketing site (navy `#0C0D17` header, cream canvas, square white CTA, column dividers, underlined text links) and dashboard (teal primary, light teal active, rounded white cards with soft shadows, status pills with text). No glass. Every text pair at least 4.5:1.
- **Logo:** vendor `https://web2026.street.co.uk/images/street-logo.svg` into `resources/images/`. Inline it, white on navy via a named `@utility` (it uses `fill="var(--fill-0, …)"`). Accessible name, links home.
- **Header (every page):** logo · Listings · Saved searches · Alerts (unread count, with an accessible label like "3 unread alerts") · **"Welcome back, {name}"** top right, from `auth.user`.
- **Mobile:** nav and greeting behind a "Menu" button (`aria-expanded`, Escape closes). Logo and alerts count stay visible.
- **Existing listing card and detail page:** a light token swap only (colours, radius), no layout changes.

## Listing images (placeholders)

- **Source:** free-licensed Unsplash photos in `resources/images/listings/house-*.png` (5 now; replace or extend only with images under the Unsplash licence, never sponsored iStock results). Record each photo's Unsplash URL and photographer in `resources/images/listings/CREDITS.md`.
- **Prepare:** resize to card size and convert to WebP; keep the files small.
- **Assignment is data-driven:** `ListingResource` adds `image_url`, picked deterministically from the listing ID (`id % count`), so a listing always shows the same photo. No schema change, nothing decided in Vue.
- **Accessibility:** decorative (`alt=""`). They aren't photos of the actual home, and the card's address and details already describe it.
- **Performance:** `width`/`height` set (no layout shift, CLS), `loading="lazy"` below the fold, `decoding="async"`.
- Used on listing cards (listings, alerts) and the detail page.

## Listings page

- **Filters in a left panel** on desktop, behind a "Filters" button on mobile.
  - **Regions and property types:** pill-style chips over native checkboxes (`fieldset` + `legend`). Options from props (data-driven), never hard-coded.
  - **Price and bedrooms:** min/max **preset dropdowns** from props ("No min" … "No max"). The max hides values below the chosen min.
  - The existing **"Search" button** applies filters to the URL (comma-separated several values).
- **"Save this search"** next to the results count, with a plain-words summary of what will be saved. States:
  - disabled with a hint when nothing is filtered
  - **"Already saved ✓"** (from props: no request needed)
  - saving (disabled)
  - success: an inline message in a polite live region, with a link to Saved searches
  - error: the API message (e.g. cap reached)
- **Empty results:** "No homes match yet. Save this search and we'll alert you when one does", with the Save button.

## Saved searches page

- Paginated at 15. Each search shows its plain-words description and its criteria as chips.
- **"View homes"** link (the listings URL with its filters). **Delete:** an inline two-step confirmation ("Delete this search?" Delete / Cancel), focus managed, the result announced.
- Empty state explains saved searches and links to the listings page.

## Alerts page

- Paginated at 15, newest first. Each alert reuses the listing card, with a **"New"** pill when unread and a **"Matched: …"** line.
- **No longer available:** a muted card with a text label, and no link.
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
