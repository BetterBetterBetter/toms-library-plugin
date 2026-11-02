# Library screencast scripts

Recording scripts for the member Library admin walkthroughs. Each script
pairs 1:1 with a recipe in the in-plugin **Library → Guide & Help** screen, so
the written steps and the recorded videos always cover the same tasks.

These are **narration + shot-list scripts for a human to record** — open the
real admin screen, hit record, and follow the on-screen steps while reading
the narration.

## Automated recording + voiceover

The clips can also be recorded and narrated automatically, on the local clone
only (`tomschooloflife.test`):

1. Record the silent clips (Playwright, from `tomschooloflife.com/test/e2e`):
   `RECORD_SCREENCASTS=1 npx playwright test specs/screencast-record.spec.ts`
   (`CLIPS=01,04` for a subset). Each clip writes `NN-*.mp4` plus
   `NN-*.cues.json` — the start time of every numbered shot.
2. Narrate them with a local TTS voice (Kokoro `af_heart` via mlx-audio):
   `<tts-venv>/bin/python scripts/screencast-voiceover.py [01 04 …]`
   This reads each script's **Narration** lines, speaks one per shot from its
   cue, holds the last frame if a line runs long, and writes
   `artifacts/screencasts/narrated/NN-*.mp4`.

The shot numbers in each script must match the `r.cue(n)` marks in the spec;
the voiceover builder refuses a clip where they disagree.

## Before you record

- Record on a **staging/clone site**, never production. Member names, emails,
  and real content appear on these screens.
- Set the browser to a clean window at 1280×800 and hide bookmarks.
- Use a demo course/series you don't mind showing (create one named
  "Demo — Screencast" if needed).
- Keep each clip to the single task. If you fumble a step, pause and retake
  that step rather than restarting.

## Script format

Every script has: **Goal**, **Length**, **Have ready**, **Shot list**
(what's on screen + the narration line for each beat), and **Call out** (the
one gotcha to emphasise).

## The scripts

| # | Task | Script |
|---|------|--------|
| 01 | Add a new course | [01-add-a-course.md](01-add-a-course.md) |
| 02 | Add episodes to a series | [02-add-series-episodes.md](02-add-series-episodes.md) |
| 03 | Add a single video or resource | [03-add-single-content.md](03-add-single-content.md) |
| 04 | Change who can access something | [04-change-access.md](04-change-access.md) |
| 05 | Check who can access something | [05-check-access.md](05-check-access.md) |
| 06 | Feature content on the homepage | [06-feature-homepage.md](06-feature-homepage.md) |
| 07 | Post an announcement to members | [07-post-announcement.md](07-post-announcement.md) |
| 08 | Organise with Collections and Topics | [08-collections-topics.md](08-collections-topics.md) |
| 09 | Schedule or unpublish content | [09-schedule-unpublish.md](09-schedule-unpublish.md) |

> Menu labels here are the default (`Library`, `Courses`, …). The top-level
> menu label follows the site's brand, so it may read differently per site —
> the submenu items are the same.
