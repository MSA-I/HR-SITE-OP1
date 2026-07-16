# Pitch deliverable

What HR Design actually sees. Everything here is generated from the running local site —
no mockups, no comps.

## Shot list

| # | Shot | Viewport | Why it earns a slide |
|---|---|---|---|
| 1 | Hero | 1440 | The identity in one frame: Karantina display, their own 2560px room photograph, two buttons, no carousel. |
| 2 | Category tiles | 1440 | Real term images, real counts, the 3-2 asymmetric split. |
| 3 | **Shop the Space** | 1440 | The differentiator. Five real products, hotspots revealed. |
| 4 | **Shop the Space — card open** | 1440 | The mechanism wired to the sale: price, dimensions, add to cart. |
| 5 | Catalogue grid | 1440 | Studio plates and room photographs side by side, reading as one system. |
| 6 | Filters applied | 1440 | Colour swatches with real counts; works with JS off. |
| 7 | Product page | 1440 | Sticky buy box, generated dimension diagram, scale silhouette. |
| 8 | Mega menu | 1440 | One preview plate, not thirty thumbnails. |
| 9 | Catalogue | 390 | Two-up on mobile, quick-add always visible. |
| 10 | Shop the Space | 390 | The strip is the control; the pins are the legend. |
| 11 | Product page | 390 | The buy bar arrives only after the in-flow button leaves. |

## Running

```
node tools/shots/capture.mjs
```

Writes to `seed/shots/`. Git-ignored — they are regenerable.

## Honest notes for the deck

Things worth saying out loud rather than letting them be discovered:

- **The dimensions marked `~` are estimates.** 129 of 250 products have measured
  dimensions; the other 119 are inferred and rendered with a tilde, a tooltip and a
  screen-reader label. They never touch WooCommerce's native fields — `wp post meta
  delete --all _hrd_dims_estimated` removes every one.
- **The Shop the Space room is an illustration, not a photograph.** It is composed from
  five real product cut-outs on flat drawn planes, because the catalogue has no lifestyle
  photography of a living room and the studio cut-outs are bathroom fittings and
  lighting. Every hotspot points at a product HR Design actually sells.
- **The catalogue is 250 of 923 products**, seeded from the live Store API.
- **Two of the brief's asks were not built**, on purpose: reviews with photos (there are
  zero reviews in the entire store) and AR product placement (needs 3D models that do not
  exist).
