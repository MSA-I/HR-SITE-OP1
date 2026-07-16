# Audit tooling

Diagnostics that shaped the build. Kept because each one exists to answer a question the
code could not answer by inspection — and because several of them caught things that
would otherwise have shipped.

| Tool | Question it answers | What it found |
|---|---|---|
| `contrast.mjs` | Do the tokens meet WCAG, and is the accent luminance contract real? | Olive and rust land on **4.90:1 each — drift 0.00**. Deep blue is 9.99:1, which is why it runs in reverse mode. |
| `price-html.php` | Does WooCommerce wrap prices in `<bdi>`? | It does. |
| `kses-bdi.php` | Then why is there no `<bdi>` in the DOM? | `wp_kses_post()` strips it — `bdi` is missing from WordPress's allowed tags (`bdo` is there, `bdi` is not). Every price on a Hebrew site, silently mangled. Fixed in `theme/inc/bidi.php`. |
| `../seed/analyze.mjs` | How much dimension data is really parseable? | 3% from the pattern the plan assumed covered 90%. Union with native fields: 51%. |
| `../seed/dims-by-category.mjs` | Does dimension coverage cluster by category? | Yes, but not enough to save the card's dimension bar: furniture tops out at 62%. |
| `../seed/measure-backdrop.mjs` | Are the product photos really shot on white? | No. 28% studio, 72% room photographs. The whole `multiply` plate premise rested on this. |
| `../seed/image-sizes.mjs` | Can the catalogue carry a full-bleed hero? | Barely. Median upload 750px, 69% under 800px. The cut-outs reach 3195px while the styled interiors are mostly 473-750px — backwards from what design needs. |

## Running them

```
node tools/audit/contrast.mjs
docker compose exec wpcli wp eval-file /tools/audit/price-html.php
docker compose exec wpcli wp eval-file /tools/audit/kses-bdi.php
```

## In-page probes

`theme/scene-preview/*.php` render live audits into the page. They are all gated on
`WP_DEBUG` and hang off query strings:

| URL | What |
|---|---|
| `/?hrd_a11y=1` | Accessibility panel on any page |
| `/?hrd_probe=enqueue` | What is enqueued and who pulled it in |
| `/?hrd_probe=backdrop` | Photo backdrop luminance distribution |
| `/?hrd_probe=sheet` | Contact sheet of studio-classified products |
| `/?hrd_probe=scenes` | Contact sheet of room photography |
| `/?hrd_probe=pick&src=6623` | One product's photos with a 10% grid, for placing hotspots |

**Delete `theme/scene-preview/` and `theme/backdrop-probe.php` before any handoff.** They
are development instruments, not part of the theme.

## Measurement notes

Two things that cost real time, recorded so they do not cost it again:

- **A backgrounded tab does not paint.** LCP and FCP come back `null`, CSS transitions
  freeze part-way, and `IntersectionObserver` never fires. Programmatic `scrollIntoView`
  does not wake it; a real scroll event does. Every "the reveal is broken" panic traced
  back to this.
- **`getBoundingClientRect()` includes transforms.** A hotspot mid-reveal is scaled to
  0.4, so the rect reports an 18px touch target for a button that is 44px at rest. Audit
  hit areas with `offsetWidth`.
