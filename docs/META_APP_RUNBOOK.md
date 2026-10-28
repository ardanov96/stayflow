# META APP RUNBOOK — registering the app for the Instagram feed

Task `P0-09` (Meta developer app and permissions). The registration is
human-owned: it needs a real business, a real identity and a review.
This runbook exists so the registration is a checklist and not a
research project, and so the review status is tracked (OQ-07).

**Verified on 2026-10-27** against
`https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user`
(the machine-readable index of the whole platform is
`https://developers.facebook.com/documentation/instagram-platform/llms.txt`).

## 1. What Stayflow needs the app for

`P5-11` (the Instagram feed plugin) shows the photos of the hotel on the
booking page. That is a **read-only** use: the app reads the media of one
Instagram account and renders it. Stayflow never publishes on behalf of
the hotel, and it never asks for a permission that would let it.

That matters: the fewer permissions the app asks for, the shorter the
review and the fewer the ways it can be used against the hotel.

## 2. The three ways to connect (verified)

Meta documents three variants of the Instagram API. Only one of them
fits, and picking the wrong one costs weeks of review.

| Variant | Token | Host | Permission |
|---|---|---|---|
| **Instagram API with Unified Login** | Business Integration System User access token | `graph.facebook.com` | `instagram_basic` |
| **Instagram API with Instagram Login** | Instagram User user access token | `graph.instagram.com` | `instagram_business_basic` |
| **Instagram API with Facebook Login** | Facebook User access token | `graph.facebook.com` | `instagram_basic` + `pages_read_engagement` |

**Stayflow takes the first one: the Instagram API with Unified Login.**
It is the current path, it needs a single permission
(`instagram_basic`), and it is the one Meta pushes the other two towards.

The third one (Facebook Login) is the older path; it needs
`pages_read_engagement` on top, and it only works when the app user was
granted a role on the Page through the Business Manager. It is only
needed if the hotel also wants the product-tagging features, which
Stayflow does not.

## 3. What the account needs before the app can be created

- An **Instagram professional account** — a Business Account or a Creator
  Account. A personal account is not eligible.
- The professional account **linked to a Facebook Page**.
- A **Meta Business account** (a Business Manager) that owns the Page.
- The **identity verification** of the business (the documents Meta asks
  for; the list depends on the country of the legal entity).

The developer app itself is created in the Meta app dashboard, with a
business portfolio attached to it.

## 4. The steps

1. **Create the Meta Business account** for the legal entity of the
   operator, and verify it. Keep the documents Meta asks for in a place
   the person doing the registration can reach.
2. **Create the Facebook Page** of the hotel, and **convert the Instagram
   account to a professional account** and link it to the Page.
3. **Create the developer app** in the Meta app dashboard:
   - the app is a **Business** app (not a Consumer app);
   - the business portfolio is attached to it;
   - the app is in **development mode** while the work is in progress.
4. **Add the `instagram_basic` permission** to the app. Nothing else.
5. **Get an access token** for a person who has a role on the Page (an
   admin role in the Business Manager is the simplest), and exchange it
   for a **long-lived token**. The long-lived token is what the server
   uses; the short-lived one is only for the setup.
6. **Read the media** of the hotel account and check that the fields
   Stayflow needs come back.
7. **Request the advanced access** (the `instagram_basic` permission in
   "standard access" is limited to the app's own testers). This is the
   step that goes through a review, and it is the step with a duration
   that is not ours to decide.
8. **Track the review** in `docs/DECISIONS.md` §3 (OQ-07), with the date
   of the request and the date of the answer.

## 5. The endpoint Stayflow will call (verified)

```
GET https://graph.facebook.com/v26.0/<IG_USER_ID>
    ?fields=id,username,biography,website
    &access_token=<long-lived token>
```

and for the feed itself:

```
GET https://graph.facebook.com/v26.0/<IG_USER_ID>/media
    ?fields=id,caption,media_type,media_url,permalink,thumbnail_url,timestamp
    &limit=<n>
    &access_token=<long-lived token>
```

The response is a JSON object with the requested `fields` and `edges`.
The edges are **paginated with cursors** (`before` / `after`), so the
plugin has to follow the pages.

The `media_type` tells the plugin what it is looking at: an image, a
video or a carousel. The plugin renders an image and a link for the
others; it does not transcode a video.

## 6. To verify before the plugin is written

- The **exact fields** of the media object in the current version of the
  API (the list above is the documented one; the API version moves).
- The **version of the graph API** to pin: the example of the
  documentation uses `v26.0`. Stayflow pins the version in the
  configuration, so a change of the default version does not break the
  plugin silently.
- The **rate limits** of the media edge.
- What happens when the token **expires** and how it is renewed without
  the hotel doing anything.

## 7. If the review does not come

`P5-11` is **second in the cut order** of `docs/ROADMAP.md` §6, and the
risk register (R6) already says the feed is optional. The fallback is a
**static link** to the hotel's Instagram profile: it is a link, not a
feed, and it needs no app, no review and no permission.

The booking page must therefore be built so that the feed is an
**optional block**: when the plugin is not configured, the page shows the
link. This is decided now, while the review is pending, not later.

## 8. The secrets

| What | Where it goes |
|---|---|
| The long-lived access token of the hotel account | The server secret store, per organization. Never in the repository, never in the database of another organization. |
| The app id and the app secret | The server secret store |

The token belongs to **one hotel**. When a second hotel is onboarded, it
gets its own token; the plugin must never reuse the token of another
organization (AGENTS.md §3.4: every tenant-scoped query is scoped).