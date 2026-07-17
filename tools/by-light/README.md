# לפי אור — the four prompts

The section's four frames are generated, not photographed, and these are the exact prompts
that made them. They live here rather than in `seed/` because `seed/` is excluded from this
repository — it holds HR Design's own photography and catalogue, which are not ours to
republish. **These prompts are ours, and a clone that cannot reproduce the section is not a
reproducible pitch.**

## What produced the frames

Nano Banana Pro (via Higgsfield), image-to-image, fed **two** references:

| | |
|---|---|
| the room | product **5932** "ספת שזלונג בונו" — their real living room, 2560x2560 from the live store |
| the lamp | product **6659** "מנורת תקרה ממתכת AM933" — their real pendant, 480 ₪, in stock |

Feeding the lamp as a second reference is why the pendant in the scene is *their* AM933
rather than a lamp the model invented.

## The order matters, and it is the whole reason the set holds still

Generate **night first, from the room source**. Then generate morning, noon and evening
**from the night render**, not from the source.

Four independent generations of "this living room at hour X" will not hold the ginger jars
still, and cross-fading between drifting frames reads as furniture morphing rather than as
light changing — which is far more visible than a bad still, because motion is what the eye
is best at. Chaining them to one anchor is what produced **0px drift** across the room.

## Verify before shipping a regenerated set

```
node tools/by-light/byl-frames.mjs <07> <12> <18> <23>
```

Block-wise normalised cross-correlation on gradient magnitude, so it survives a brightness
change and measures whether content **moved**. It exits 1 if the room drifted anywhere the
lamp cannot explain. The lamp's own region is exempt by design; a cell too dark to align
reports *indeterminate* rather than passing.

The gate cannot see an object swapped **in place** — that scores 0px and passes. Check the
vitrine at full resolution by eye as well: the five ginger jars must be the same five jars,
same order, same patterns.
