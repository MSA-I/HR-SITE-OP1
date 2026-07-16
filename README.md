# HR Design — Option 1: "Gallery Apartment"

**This is the first of three separate design directions for hr-design.co.il, each in its
own repository.** They are alternatives, not stages — three different answers to the same
brief, meant to be compared side by side.

| | Direction | Repo |
|---|---|---|
| **1** | **Gallery Apartment** — this one | `HR-SITE-OP1` |
| 2 | *to come* | *separate repo* |
| 3 | *to come* | *separate repo* |

A custom WordPress + WooCommerce theme (Hebrew, RTL) built against 250 of HR Design's
real products, implementing the brief in `הערת ה צ'אט.txt`.

**This is a pitch build.** It is not deployed and never touches the live store.

---

## The direction

The brief's diagnosis: *"the site works as a shop but doesn't tell a story."* And its
warning: don't build another beige luxury furniture store — that direction is already
generic.

**Gallery Apartment** is the answer this option commits to: a furniture catalogue staged
like a curated apartment-gallery. Hard editorial grid, oversized condensed Hebrew display
type hanging off a right-hand rail, product photos treated as specimens pinned to
coloured planes, and one saturated accent per collection that takes over an entire section
like a painted wall.

It is not beige-generic, and the reason is structural rather than stylistic. Beige-luxury
has a Latin grammar — centred symmetric hero, letterspaced small-caps, blurred ambient
shadows. **Three of those moves are impossible in Hebrew.** There is no uppercase, and
positive tracking on Hebrew destroys word recognition. Sites that copy the template anyway
end up as a Latin skeleton wearing Hebrew, which is exactly why they all look alike and
all look slightly wrong.

So: asymmetric instead of centred. Negative tracking on a condensed Hebrew display face
(Karantina) instead of letterspaced caps. Contact shadows and 1px hairlines instead of
blur. Radius 0, with one arch motif used in exactly one section. Cream is a **ground**,
never a highlight.

---

## Run it

```
docker compose up -d
docker compose exec wpcli wp core install --url=http://localhost:8080 --title="HR Design" \
  --admin_user=admin --admin_password=admin --admin_email=you@example.com --skip-email
docker compose exec wpcli wp language core install he_IL --activate
docker compose exec wpcli wp plugin install woocommerce --activate
docker compose exec wpcli wp theme activate hr-design
docker compose exec wpcli wp rewrite structure '/%postname%/' --hard

npm install
node tools/fonts.mjs            # self-host the webfonts (OFL, fetched not committed)
npm run build

# Seed 250 products from the live Store API. One pass, ~510 polite requests.
node tools/seed/fetch.mjs
docker compose exec wpcli wp eval-file /tools/seed/import.php
docker compose exec wpcli wp eval-file /tools/seed/normalize.php
docker compose exec wpcli wp eval-file /tools/seed/rank.php
docker compose exec wpcli wp eval-file /tools/seed/classify-photos.php
docker compose exec wpcli wp eval-file /tools/seed/import-estimates.php

# Shop the Space
node tools/scene/compose.mjs
docker compose exec wpcli wp eval-file /tools/scene/install.php
node tools/scene/fetch-hero.mjs
docker compose exec wpcli wp eval-file /tools/scene/install-hero.php
```

→ http://localhost:8080

**On Windows with a Hebrew path**, probe the bind mount before anything else — it fails
*silently*, mounting an empty directory rather than erroring:

```
docker run --rm -v "D:\משה פרוייקטים\HR_DESIGN-SITE:/x" alpine ls /x
```

---

## What is here

`theme/` is the deliverable — 67 files, ~7,700 lines. Everything else is how it was built.

| | |
|---|---|
| `theme/` | The theme |
| `tools/seed/` | Store API seeder, importer, normaliser, and the analyses that reshaped the plan |
| `tools/scene/` | The Shop the Space composer |
| `tools/audit/` | Contrast and bidi audits — see `tools/audit/README.md` |
| `tools/dev-probes/` | In-page diagnostics, loaded as a mu-plugin, never as theme code |
| `tools/shots/` | Pitch screenshots — see `tools/shots/README.md` |

**`seed/` is not in this repository, deliberately.** It holds 409 of HR Design's product
photographs, their catalogue text, and scene SVGs with their photography embedded. This
repo is public and none of that content is ours. `node tools/seed/fetch.mjs` reproduces
all of it, so nothing is lost.

---

## Decisions worth defending

**No GSAP, no Lenis, no framework.** The theme's JavaScript is **4KB**. ScrollTrigger's
unique value is pinning and scrubbing; both are scroll hijacking, which the brief bans by
name. IntersectionObserver + CSS + cross-document View Transitions cover the whole thing.

**Authored natively RTL.** No `rtl.css`, no build-time flip — the site is Hebrew-only, so
it uses logical properties throughout. Two documented exceptions: the Shop the Space stage
and the dimension diagrams are forced `direction: ltr`, because their coordinates are
physical positions in an image, not text flow. Get that wrong and every hotspot mirrors
onto the wrong furniture — and casual QA never catches it, because the dots are still on
objects.

**Progressive enhancement, verified.** Filters are plain links. Add-to-cart is
`?add-to-cart=ID`, intercepted by JS. Both paths were tested with an HTTP client that runs
no JavaScript at all.

**Reduced motion is a data attribute, not a media query.** Set before first paint from
`localStorage ?? the OS preference`. The OS setting is honoured on the first frame, a
footer toggle lets users opt out without touching an OS setting most Israeli Windows users
have never seen, and with JS disabled the attribute is simply absent — so the site renders
static.

---

## Not built, on purpose

- **Reviews with photos** — there are **zero reviews across the entire store**. An empty
  five-star row is the loudest "this is a template" signal there is.
- **AR / "see it in your space"** — needs a 3D model per product. A second project, not a
  feature.

---

## What the data forced

Every row here overturned something in the approved plan. This is the honest record, and
it is the most useful part of this repo for options 2 and 3.

| The plan assumed | Measured reality | What changed |
|---|---|---|
| Content must be scraped | The Store API is public | A paginated fetch, zero npm dependencies |
| 90% of products have parseable dimensions | **3%** for that pattern; 51% across five formats plus native fields | The card's dimension bar was unbuildable as designed |
| Products are shot on white, so `multiply` drops the backdrop | **28% studio, 72% room photographs** | The card branches per photo, measured at import |
| The living room can be composed from their cut-outs | The cut-outs are bathroom fittings and lighting; the one studio sofa is pale-on-white, and multiply erases it | The scene is an **entryway**, and an obvious illustration rather than a fake photograph |
| Their photography can carry a full-bleed hero | Median upload **750px**. The styled interiors are 473–750px while the cut-outs reach 3195px — backwards from what design needs | The hero is one of only 44 room photos above 1400w |
| WooCommerce wraps prices in `<bdi>` | It does — and `wp_kses_post()` strips it, because `bdi` is missing from WordPress's allowed tags | `theme/inc/bidi.php`. Without it every price on a Hebrew site is silently mangled. |

---

## Honest limitations

- **LCP was never measured.** A backgrounded automation tab does not paint, so LCP and FCP
  return `null`. Payload is verified: **75KB across 10 requests** on a first visit; theme
  JS 4KB, CSS 8KB. The plan's "LCP < 2.5s on throttled 4G" gate is **not** claimed as
  passed.
- **119 of 250 products carry estimated dimensions.** They render with a `~`, a tooltip and
  a screen-reader label, live in `_hrd_dims_estimated`, and never touch WooCommerce's
  native fields. `wp post meta delete --all _hrd_dims_estimated` removes every one.
- **The catalogue is a 250-product sample** of 923.
- **The Shop the Space room is illustrated**, not photographed. Every hotspot points at a
  real product HR Design sells.
