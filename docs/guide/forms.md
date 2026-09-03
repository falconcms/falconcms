# Form Builder

Build contact and lead-capture forms visually, drop them into any page with a shortcode, and
read what people send you from the dashboard. Forms are part of the free core — no Pro license
is needed.

## Building a form

Go to **Forms** in the dashboard and create one. The builder lets you add fields, set each
one's label, placeholder and validation, and arrange them in the order visitors will see.

Eleven field types are available:

| | |
|---|---|
| `text` | single-line text |
| `textarea` | multi-line text |
| `email` | email address |
| `tel` | telephone number |
| `number` | numeric input |
| `date` | date picker |
| `select` | dropdown |
| `radio` | one choice from several |
| `checkbox` | one or more choices |
| `file` | file upload |
| `hidden` | a value the visitor does not see |

## Putting a form on a page

Every form has a slug, and the builder shows you the shortcode to copy:

```
[falcon_form slug="contact"]
```

Paste it into any page, post, or a Text element in the page builder. It renders the form with
your theme's styling.

## What happens on submit

You choose what the visitor sees after sending — either a **success message** shown in place,
or a **redirect** to a URL of your choice.

Set a **notification email** on the form and each submission is emailed there as it arrives.

Every submission is also stored, so nothing depends on that email being delivered.

## Reading submissions

**Forms → Submissions** lists everything that has come in, either per form or across all of
them. Each row records:

- the submitted field values
- the sender's IP address and user agent
- whether it has been read yet

Uploaded files are stored on the public disk and linked from the submission.

## Spam protection

Two layers are applied automatically:

- a **honeypot** field, invisible to people but usually filled in by bots
- **Cloudflare Turnstile**, when you have configured it in the CMS settings

Both are stripped from the stored data — you only ever see the fields you asked for.

## Moving a form between sites

**Export** downloads the whole form as JSON — fields, labels, validation, success message and
settings. **Import** recreates it on another site. Useful for reusing a contact form across
projects, or for moving one from staging to live.

Submissions are not included in the export; only the form itself travels.
