# Bootcademy public academy websites

Each academy receives a unique public-site record when its workspace is created. The first theme is `launch`; the theme key is stored independently so additional themes can be added without rewriting academy content.

## Local use

Academy owners edit the website at `/app/settings/website`. Local previews use:

```text
http://127.0.0.1:8000/sites/{academy-subdomain}
```

Programme pages and the application UI are beneath that URL. The public application form is intentionally a non-submitting preview until the public submission, consent, abuse-protection and payment workflow is approved.

Programme creation remains intentionally short. After creation, Owner/Admin users can use Edit to add an optional public profile: a replaceable hero image, duration, delivery format, programme type, timezone, level, formatted outcomes, skills, curriculum, eligibility, audience, demand and application process, plus repeatable tools/platforms, FAQs and testimonials. Rich content is allow-list sanitized on the server before storage. Hero images remain in private storage and are served through academy-scoped routes. Public programme pages combine this profile with academy-scoped active cycles, branch availability, fee breakdowns and payment arrangements.

## Production subdomains

Set the root tenant domain in the environment:

```text
TENANT_DOMAIN=bootcademy.com
```

Production infrastructure must also:

- point the wildcard DNS record `*.bootcademy.com` to the Laravel application;
- provision TLS for `*.bootcademy.com` and `bootcademy.com`;
- preserve the incoming `Host` header through the reverse proxy;
- reserve platform labels such as `www`, `app`, `api`, `admin` and `platform`.

Laravel then serves academy sites at `https://{academy-subdomain}.bootcademy.com` while retaining strict academy-scoped queries.

## Content and privacy

- Public sections are disabled independently and only enabled content is rendered.
- Programmes, branches, active cycles, fee schedules and applicant custom fields are academy-scoped.
- Programme rich text permits headings, emphasis, lists, quotes and safe links; scripts, event attributes, embeds and unsafe URL schemes are removed server-side.
- Team members, testimonials, learner stories, gallery images, memberships and sponsors should only be published with appropriate permission.
- Website media currently accepts public HTTPS URLs. A managed public media library can be added as a separate feature.
