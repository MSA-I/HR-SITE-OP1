# Pitch shots

What HR Design actually sees. Everything in `docs/shots/` is captured from the running
local site — no mockups, no comps, no retouching.

Desktop is 1440×900 at 2x. Mobile is 390×844 at 3x.

## Shot list

| # | Shot | Viewport | Why it earns a slide |
|---|---|---|---|
| 01 | Hero | 1440 | The identity in one frame: Karantina display, their own 2560px room photograph, two buttons, no carousel. |
| 02 | Category tiles | 1440 | Real term images, real counts, the asymmetric 3-2 split. |
| 03 | **Shop the Space** | 1440 | The differentiator. Five real products, hotspots revealed. |
| 04 | **Shop the Space — card open** | 1440 | The mechanism wired to the sale: price, dimensions, add to cart. |
| 05 | Featured collection | 1440 | The horizontal section — native inline scroll, not a pinned hijack. |
| 06 | Shop by room | 1440 | The arch portals. The only radius in the system. |
| 07 | Catalogue | 1440 | Studio plates and room photographs side by side, reading as one system. |
| 08 | Filters applied | 1440 | Colour swatches with real counts. Works with JS off. |
| 09 | Product page | 1440 | Sticky buy box, the plate, the WhatsApp link as text and not a floating blob. |
| 10 | Dimension diagram | 1440 | Generated from three numbers: the isometric drawing and the 175cm scale silhouette. |
| 11 | Mega menu | 1440 | One preview plate, not thirty thumbnails. |
| 12 | Catalogue | 390 | Two-up on mobile, quick-add always visible. |
| 13 | **Shop the Space** | 390 | The strip is the control; the pins are the legend. A separate portrait composition, not a crop. |
| 14 | Product page | 390 | The buy bar arrives only after the in-flow button leaves. |

## Running

Chrome needs a debugging port. The capture drives it over CDP directly — no Puppeteer, no
dependency:

```
chrome.exe --remote-debugging-port=9222 --user-data-dir=%TEMP%\hrd-shots --headless=new
node tools/shots/capture.mjs
```

Writes to `docs/shots/`, which **is** committed — these are the deliverable.

Scroll offsets are measured, not guessed. `node tools/shots/offsets.mjs` prints where each
section actually sits; the first capture used round numbers and cut every section heading
off the top of the frame.

`node tools/shots/overflow.mjs` and `overflow-edge.mjs` check that the body never scrolls
sideways. They found three separate causes at 390px — a flex header, a grid item refusing
to shrink below its content, and pagination that could not wrap.

## Honest notes for the deck

Say these out loud rather than letting them be discovered:

- **Dimensions marked `~` are estimates.** 129 of 250 products have measured dimensions;
  the other 119 are inferred and rendered with a tilde, a tooltip and a screen-reader
  label. They never touch WooCommerce's native fields — `wp post meta delete --all
  _hrd_dims_estimated` removes every one.
- **The Shop the Space room is an illustration, not a photograph.** It is composed from
  five real product cut-outs on flat drawn planes, because the catalogue has no lifestyle
  photography of a living room and the studio cut-outs are bathroom fittings and lighting.
  Every hotspot points at a product HR Design actually sells.
- **The catalogue is 250 of 923 products**, seeded from the live Store API.
- **Two of the brief's asks were not built**, on purpose: reviews with photos (there are
  zero reviews in the entire store) and AR product placement (needs 3D models that do not
  exist).
