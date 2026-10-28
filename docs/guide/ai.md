# AI Writing

**Falcon AI** writes with you in the post editor: a whole article from a short brief, better
versions of a passage you select, and SEO titles, descriptions and alt text. It is free, comes with
FalconCMS (on by default) and works with **Claude** (Anthropic), **OpenAI** or **Google Gemini**,
using your own API key. You pay the provider for what you use; FalconCMS adds nothing. Gemini's
Flash models have a free tier, so you can start without paying.

## Set it up

Open **AI → Settings & Usage**.

1. Under **Provider**, paste a key for one or more of them:
   - Claude, from [console.anthropic.com](https://console.anthropic.com/settings/keys). A Claude Pro
     subscription (claude.ai) does not include API credit: the API is paid separately.
   - OpenAI, from [platform.openai.com](https://platform.openai.com/api-keys).
   - Gemini, from [Google AI Studio](https://aistudio.google.com/apikey), free, no card needed. New
     keys start with `AQ.` (older ones with `AIza`); copy the whole key. If Google refuses a new
     key, create another one in AI Studio.
2. Choose the **model**. For Claude: Opus 5.5 gives the best writing, Sonnet 5.5 is faster at half
   the cost, Haiku 5.5 is the fastest and cheapest. For OpenAI and Gemini the list comes from your
   account, so new models appear as they are added. On Gemini's free tier choose a **Flash** model
   (a few requests a minute, about a thousand a day; Pro models need billing).
3. Choose which provider to **write with**. With several keys saved you can switch at any time.
4. **Save Changes**, then **Test** beside the key. The test costs nothing.

Keys are stored encrypted and never sent back to the browser: the field shows only their first and
last characters. Saving the field unchanged keeps the key; **Remove** deletes it.

Also on this screen:

- **Language** and **Tone**: the defaults for new articles (each article can choose its own).
  About 80 languages, from Bangla, English, Hindi and Arabic to Japanese, Swahili and Welsh, or
  "the language of the topic". Type in the list to find one.
- **Who can write with AI**: administrators always can. Tick the other roles that may (Editor by
  default). Only users who can write posts or pages see the tools.
- **Monthly limit**: a ceiling on the tokens used each month (0 = no limit). When it is reached, AI
  writing stops until the next month. A medium article takes roughly 10,000–20,000 tokens.
- **This month**: requests and tokens, by provider, model and use. Only counts are kept, never the
  text.

## Write an article

In the post editor, **✨ Write with AI** sits beside the Rich Editor and Page Builder tabs.

1. **Brief**: the topic, and optionally the focus keyword, the audience, language, tone, length
   (short, medium or long) and notes ("mention our Dhaka shop", "no prices").
2. **Outline**: a title and the sections, each with the points it will cover. Change any heading or
   point, reorder sections, add or remove them, or ask for a new outline.
3. **Write the article**: each section is written in turn and appears as it is written. **Stop** at
   any time; **Continue** picks up where it stopped.
4. **Put it in the editor**: replace what is there, or add the article after it, and optionally use
   the title. Then the SEO title, meta description, excerpt and tags are written too; fields that
   already have text are left alone.

Nothing is saved until you save the post. The article goes into the Rich Editor; with the Page
Builder open, switch to the Rich Editor first. **Read it through before you publish**: AI can get
facts wrong, and search engines reward pages written for people.

## Improve a passage

Select text in the Rich Editor, then **Improve selection**: rewrite, expand, shorten, simplify,
translate to English, or translate to Bangla. The selection is replaced; **Ctrl+Z** puts it back.

## SEO suggestions

With [Falcon SEO](/guide/seo), **✨ Suggest with AI** appears beside the SEO title and meta
description in the editor's SEO box (and in its Analysis tab for a weak one): three options for the
page and its focus keyword, each with the reason. Site Analysis's AI fixes (Pro) use it as well.

## Privacy and security

- Only the text being worked on is sent to the provider, when someone asks for it.
- Everything the AI writes is cleaned before it reaches the editor: scripts, styles, frames, event
  attributes and unsafe links are removed, so a reply cannot run code in the admin.
- Each request is checked twice (the admin's access rules and the request itself), limited per
  minute, and held to fixed sizes.

## For developers

- `falcon_ai_providers` (filter): `array<string, class-string>` — add a provider class implementing
  `FalconAi\Contracts\AiProvider`.
- `falcon_ai_generated` (action): `string $feature, AiResult $result` — after every answer.
- `app(FalconAi\Support\AiManager::class)->json(...)` / `->stream(...)` — ask the chosen provider
  from your own code; the monthly limit and the usage record apply.
