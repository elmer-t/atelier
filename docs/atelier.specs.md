# Atelier — Design & Handoff Specification

**Version:** 1.1 (unified artifact model)
**Owner:** REDHEADIT
**Deployment target:** `atelier.redheadit.nl` (subdomain), plus a sandbox subdomain (see §6)
**Intended reader:** the engineer/agent building this app. This is a spec, not a tutorial — decisions are already made; where a decision was deliberately deferred it is flagged as **FUTURE**.

**Changes since 1.0:** the separate `Page` and `Attachment` entities are unified into a single **`Artifact`** with a `type` (`markdown` | `html` | `file`). A `file` artifact carries a `placement` (`stage` | `download`) so images, PDFs and office docs render in the viewer (browser-native) or sit in the downloads list, without per-format code. The app never decodes file formats — it streams bytes and the browser renders or downloads. See §3, §5, §7.

---

## 1. Purpose & scope

Atelier is a single-operator web application for presenting in-progress concepts and designs (for websites and applications) to clients. Each **project** bundles an ordered list of **artifacts**. An artifact is one of three types:

- **Markdown** — specs, design briefs, written documentation. Rendered server-side.
- **HTML** — rich, self-contained HTML/CSS/JS showcases (mockups, prototypes). Served with full fidelity inside a sandbox.
- **File** — any uploaded file (image, PDF, office document, zip, …). Browser-renderable files (images, PDFs) open in the viewer; the rest are downloads. A `placement` flag decides which, defaulting from the file's MIME type.

Projects are viewed via a shareable link. Access is controlled per project by a password (private) or open (public). This is a **presentation tool**, not a collaboration or client-account platform — link recipients never log in.

### Explicitly out of scope (v1)
- Client accounts / recipient logins.
- Multi-tenancy / hosting for other operators. (Each operator runs their own installation.)
- Per-artifact access control (visibility is per-project only).
- In-app editing of HTML bundles (re-upload only).
- Content versioning / history (uploads replace).
- Link expiry.
- Analytics / view tracking.

---

## 2. Tech stack & environment

- **Framework:** Laravel (latest stable compatible with the target PHP).
- **PHP:** 8.4 (pinned).
- **Server:** existing LAMP box already running multiple Laravel sites. Apache vhosts.
- **DB:** MySQL/MariaDB (as per LAMP).
- **Markdown rendering:** `league/commonmark` (server-side, at request time).
- **Auth (admin):** Laravel's built-in authentication + user management. Multiple admin users supported (near-free via the framework); no custom roles required in v1.

---

## 3. Core concepts & data model

### 3.1 Entities

**Project**
- `id`
- `title`
- `slug` — unguessable, URL-safe token (e.g. 32+ chars, CSPRNG-generated). Used in shareable links.
- `status` — enum: `active` | `archived`.
- `visibility` — enum: `private` | `public`.
- `password_hash` — nullable. Set when `private`. Hashed (bcrypt/argon via Laravel `Hash`). **Never reversible.** Only an admin can rotate it; there is no "show password" feature.
- timestamps.

**Artifact** (belongs to Project)
- `id`, `project_id`
- `title`
- `type` — enum: `markdown` | `html` | `file`. Fixes the storage pipeline, the serving origin, and how the artifact renders (§5, §7).
- `placement` — enum: `stage` | `download`, nullable. Only meaningful for `file` artifacts; `null` for markdown/html (which always stage). `stage` = renders in the viewer and appears in the sidebar; `download` = downloads-list only. Defaulted from MIME on upload (images, PDFs, text → `stage`; everything else → `download`), operator-overridable.
- `sort_order` — integer, manually set. All artifacts share one ordered list per project.
- For `markdown`: `body` (text) — the raw markdown, stored in DB. Associated uploaded images stored on disk, referenced relatively (see §5.2).
- For `html`: `bundle_path` + `entry_file` — the unpacked HTML bundle directory in sandbox storage and its entry point (see §6). No body in DB.
- For `file`: `stored_path`, `original_filename`, `mime_type`, `size_bytes` — the uploaded file in app storage (see §8).
- timestamps.

The per-type storage columns are nullable and only populated for their type; all type-specific *behaviour* (serving origin, on-disk cleanup, stage rendering) lives in per-type handlers keyed off `type`, not in `switch`/`match` scattered through the codebase.

### 3.2 Relationships
- Project 1—* Artifact.
- Artifacts ordered by `sort_order` within a project, mixed types freely (e.g. brief → mockup → spec → mockup). Deleting a project cascades to its artifacts; each artifact's on-disk files are purged first (§8.2).

---

## 4. Access control & visibility

This is the most security-sensitive part of the design. Read carefully — artifact types have **different** protection models depending on the origin they are served from, and "private" does **not** mean "everything is locked."

### 4.1 Two protection models

Each artifact type declares an **origin**, which fixes its protection model:

| Artifact type | Origin | Where it lives | How it's protected |
|---|---|---|---|
| Markdown | App | The Laravel app | Password gate (if private) — real access control |
| File (image/PDF/office/…) | App | The Laravel app | Password gate (if private) — real access control |
| HTML (bundles) | Sandbox | Sandbox subdomain, static files | **Obscurity only** — unguessable path, no password gate ever |

App-origin artifacts (markdown, files) are always streamed through the app, so the project session/visibility check is enforced on every request — files are never linked from a public path.

**Implication (must be documented for the operator):** the sandbox serves HTML bundles by path with no authentication. Anyone who obtains a sandbox URL can view that mockup regardless of the project's `private`/`public` flag. Therefore:

- **Confidential content must live in markdown or file artifacts (app-gated), never in HTML bundles.**
- HTML mockups are treated as "safe to be obscurity-protected." This was an accepted design decision. If a future requirement demands truly gated HTML, escalate to signed/expiring iframe URLs (see §6.4 **FUTURE**).

### 4.2 Visibility semantics

- **`private`** — project requires the password. Viewer hits the link → password prompt → on success, a **session** is established for that project (password is **not** re-checked per artifact). All app-origin content (markdown, file artifacts — inline previews and downloads) is then viewable for the session. HTML bundles render via iframe (sandbox path is included in the page once the session is active).
- **`public`** — **fully open**. No password anywhere. The project is:
  - viewable by anyone with the link, no prompt, AND
  - **listed on a public index page** at the app root (see §7.3).
  - There is no half-public state: a public project's app content, files, downloads, and mockups are all open.

### 4.3 Session handling
- Password entry sets a per-project session flag (e.g. keyed by project id in the session store).
- No expiry beyond normal session lifetime.
- Rotating a project's password should invalidate existing sessions for that project (simplest: bump a per-project token/version included in the session key).

---

## 5. Content pipelines

The artifact *concept* is unified, but each type has a **distinct storage pipeline**. Do not try to collapse the pipelines into one — they differ in where bytes live and which origin serves them. What *is* unified is the model, the admin flow, and the ordered list.

### 5.1 Markdown artifacts
- Authored via the admin UI: paste/enter markdown in a form (textarea), or upload a `.md` file (same result — stored as text in `Artifact.body`).
- Rendered server-side at request time with CommonMark.
- **Sanitization:** markdown may contain raw HTML (intentional escape hatch). Because markdown renders on the app's own origin, run rendered output through an HTML sanitizer to strip script/dangerous content. Rich/interactive content belongs in HTML artifacts, not markdown.
- **Editing:** edit-in-place in a textarea in the admin UI.

### 5.2 Markdown images
- Images used in markdown are uploaded alongside the markdown artifact and referenced by relative path.
- Store under app storage (see §8), served by the app. These are a sub-resource of the markdown artifact, **not** a `file` artifact of their own.
- These are app-origin artifacts, distinct from sandbox HTML artifacts.

### 5.3 HTML artifacts (bundles)
- An HTML artifact is **not a single file** — it is an HTML entry point plus a folder of artifacts (CSS/JS/images) with relative links.
- **Upload = zip.** Admin uploads a `.zip`; the app unpacks it into a per-project/per-artifact directory in **sandbox storage** (§6).
- HTML concepts are **self-contained** — they never call the Atelier backend (no forms posting to the app, no API). This keeps the sandbox origin airtight.
- **Editing = re-upload the zip** (replaces the bundle). No in-app editing. This asymmetry with markdown is intentional and accepted.
- On unpack: strip symlinks and path-traversal entries from the zip (untrusted-input hygiene, even though the operator is the uploader). Identify the entry file (e.g. `index.html`; if absent, require the operator to specify it).

### 5.4 File artifacts
- **Upload = the file itself.** Admin uploads any file; it is stored under app storage (see §8) with its original filename, MIME type and size recorded.
- **The app never decodes formats.** Serving is format-agnostic: the file is streamed through the app (gated by the project session) with its stored `Content-Type`. Rendering is the **browser's** job — modern browsers render images and PDFs (and some office formats) inline and fall back to downloading anything they can't. This is why there is no per-format code and no `image`/`pdf`/`office` artifact subtypes.
- **`placement`** decides where the file surfaces (§7.1): `stage` files render in the viewer (inline, with a download button); `download` files appear only in the downloads list. Default inferred from MIME on upload, operator-overridable.
- **Two dispositions, one file:** stage rendering requests the file with `Content-Disposition: inline`; the download action requests it with `attachment`. Both go through the same gated controller.
- **Editing = re-upload** (replaces the stored file) and/or change title/placement.

---

## 6. Sandbox architecture (HTML isolation)

### 6.1 Why
Serving arbitrary HTML/JS from the app's own origin would let uploaded scripts access the app's cookies/session/localStorage (stored-XSS / phishing surface on `redheadit.nl`). HTML artifacts are therefore served from an **isolated origin** and embedded via iframe.

### 6.2 Topology (single box)
- **App vhost:** `atelier.redheadit.nl` → Laravel app (PHP). Serves the app shell, markdown, file artifacts (inline previews and downloads), admin.
- **Sandbox vhost:** e.g. `sandbox.redheadit.nl` → **static file server only. No PHP. No Laravel.** Its `DocumentRoot` points at the HTML bundle storage directory.
- **Shared storage, no file movement:** the app (during upload) unpacks bundles directly into the directory that is the sandbox vhost's docroot. One write, two read paths. Do **not** store HTML bundles under Laravel's `storage/app` (permissions friction); use a dedicated top-level directory, e.g. `/var/www/atelier-sandbox/`, owned appropriately so the app can write and the sandbox vhost can read.

Directory layout (illustrative):
```
/var/www/atelier-sandbox/
  <project-sandbox-token>/
    <artifact-id>/
      index.html
      artifacts/...
```

### 6.3 Rendering
- App renders a project page shell (sidebar + main stage) on the app origin.
- HTML artifacts render as an `<iframe>` in the main stage, `src` pointing at the sandbox origin, with a restrictive `sandbox` attribute on the iframe (allow scripts as needed for the mockup, but the cross-origin boundary is the primary protection — the iframe cannot reach app cookies because it's a different origin).
- Sandbox paths use an **unguessable per-project token** (separate from, or derived independently of, the project slug) so bundle URLs aren't trivially enumerable.

### 6.4 FUTURE — gated HTML
If confidential HTML is ever required: generate short-lived **signed/expiring iframe URLs** in the app and validate the signature at the sandbox (lightweight rewrite/verification). Not built in v1. Until then, confidential material must not be placed in HTML bundles.

---

## 7. Public-facing UX

### 7.1 Project view (the "stage")
- **Sidebar:** ordered list of the project's **stage artifacts** (markdown, html, and `file` artifacts with `placement = stage`), in `sort_order`. Clicking one loads it in the main stage:
  - markdown → rendered HTML in the stage (app origin).
  - html → iframe in the stage (sandbox origin).
  - file (stage) → browser-native inline render (image `<img>`, PDF/office `<iframe>`), streamed inline from the app origin, with a per-item download button. If the browser can't render the type, it falls back to downloading.
- **Downloads section:** separate from the sidebar. Lists `file` artifacts with `placement = download`. These do not "open in the stage" — they download.
- Keep the sidebar and downloads list visually/functionally separate (do not merge into one list). The split is now driven by `placement`, not by separate entities. A download-only file is never reachable via the stage route.

### 7.2 Access flow
- Link → if `private` and no active session: password prompt → on success, session established → project view.
- Link → if `public`: straight to project view.

### 7.3 Public index
- A public index page at the app root (`atelier.redheadit.nl`) lists **all `public` projects** (that are also `active` — archived projects are not listed; see §9).
- Each entry links to that project's view.
- Private projects never appear on the index.

---

## 8. Admin area

Behind Laravel auth (admin login). Multiple admin users supported.

### 8.1 Required features
- **Project list view** — all projects, showing title, status (`active`/`archived`), visibility (`private`/`public`), and the shareable link. Filter/sort desirable but not required.
- **Create / edit project** — title, generate/regenerate slug, set status, set visibility, set/rotate password (private only; set-and-forget, hashed, never displayed).
- **Manage artifacts** within a project (one unified manager, one ordered list):
  - add markdown artifact (paste/upload `.md` + optional images), edit body in place.
  - add HTML artifact (upload zip → unpack to sandbox), re-upload to replace.
  - add file artifact (upload any file); choose `placement` (defaulted from MIME), re-upload to replace.
  - set/reorder `sort_order` (manual ordering across all types).
  - delete artifact (purges its on-disk files).
- **Status control** — toggle `active`/`archived` per project.
- **Visibility control** — toggle `private`/`public` per project.

### 8.2 Storage split (summary)
- **App storage** (Laravel `storage/`): markdown bodies (DB), markdown images, file artifacts.
- **Sandbox storage** (`/var/www/atelier-sandbox/`, served by sandbox vhost): unpacked HTML bundles only.
- **DB:** projects, artifacts (markdown bodies + html bundle refs + file metadata), admin users.
- **Cleanup:** deleting an artifact (or its project) purges its artifacts via a per-type handler — markdown = nothing on disk, html = its sandbox bundle directory, file = its stored file. Project deletion also removes the whole per-project sandbox tree as a safety net; DB rows cascade.

---

## 9. Status behaviour

- **`active`** — normal. Viewable via link; if public, listed on the index.
- **`archived`** — **hard-disabled** (decided). Retained in the DB but not reachable: not listed on the public index, and a direct link (project view **or** password gate) returns 404. Un-archiving restores access.

---

## 10. Security checklist (implementer must verify)

- [ ] HTML bundles are served **only** from the sandbox origin, never from the app origin.
- [ ] Sandbox vhost runs **no** application code (static only).
- [ ] App origin never links directly to raw uploaded files that bypass access checks (file artifacts — inline previews and downloads — streamed through a controller that checks the project session/visibility).
- [ ] The file controller serves only `file`-type artifacts scoped to the project in the URL; it never serves a foreign project's artifact.
- [ ] No directory indexing (`Options -Indexes`) on any vhost, especially sandbox.
- [ ] Markdown-rendered output is sanitized before serving.
- [ ] Zip unpacking strips symlinks and rejects path-traversal (`../`) entries.
- [ ] Passwords hashed (never reversible, never displayed); rotation invalidates existing project sessions.
- [ ] Slugs and sandbox tokens generated with a CSPRNG, long enough to resist enumeration.
- [ ] Private project content (markdown/files) is genuinely gated; operator is aware HTML mockups are obscurity-only.

---

## 11. Data protection (GDPR / ePrivacy)

Atelier is deployed in the EU (`atelier.redheadit.nl`) and processes client personal data, so the following baseline is a launch requirement, not an enhancement. It is scoped to a **single-operator** tool that captures client contact details at comment time (ADR-0003); a full consent-management platform and multi-tenant DPAs are out of scope.

### 11.1 What is processed
- **Name + email** of a commenter, captured at comment time (never at view time). Pure viewers stay anonymous.
- The **`atelier_commenter` cookie** — a persistent (up to one year) reference to the commenter's identity, set **only** as a direct result of the visitor choosing to comment, so they don't re-enter their details on return visits. Because it is set solely to deliver the commenting the visitor actively requested, the point-of-capture notice discloses it and treats submitting identity as informed consent; no separate cookie banner is used.

### 11.2 Disclosures (implemented)
- A **privacy policy** page at `/privacy` (`route('privacy')`) states what is collected, why, the legal basis (legitimate interest, engaged only on choosing to comment), retention, the cookie, data-subject rights, and a contact address (`config('atelier.privacy.*')`).
- The policy is linked from the **public index**, the **public project page**, and the **comment identity-capture prompt**.
- The identity-capture prompt carries a **plain-language notice** of what is collected and why, and that a cookie remembers the visitor.

### 11.3 Retention & erasure
- **Retention:** client PII is kept for as long as the project is active; it is erased on request or when the project is deleted (project deletion cascades its comments — §8.2). The exact wording shown to data subjects is `config('atelier.privacy.retention')`, to be confirmed with the owner (REDHEADIT) before shipping copy.
- **Erasure (right to be forgotten):** an operator runs `php artisan atelier:forget-client {email}` to fulfil a request. `ClientDataEraser` anonymises the Client `User` (identifying name replaced, email re-pointed to an unroutable `@atelier.invalid` address, remember-token cleared) and **redacts the bodies of the comments they authored**, while keeping every `Comment` row so Thread structure, replies, and resolution state are preserved. Only Client-role Users are erasable this way — the command refuses Creators/Agents, guarding the operator's own account.
- **Known limitation:** erasure scrubs the requester's identity and their *own* comment bodies, but does not scrub the requester's name/email if another participant quoted it verbatim inside *their* comment. Redacting a third party's feedback is a manual operator judgement, so it is left out of the automated path.

### 11.4 Confirm with owner
- Final legal-basis and retention wording for the published privacy policy (see §13).

## 12. Naming

Product name: **Atelier**. Internal-facing (REDHEADIT), formal register; connotes a studio of work-in-progress, which matches the tool's purpose. Deployment subdomain `atelier.redheadit.nl`.

---

## 13. Open items to confirm with owner

1. Sandbox subdomain final name (`sandbox.redheadit.nl` assumed).
2. Final legal-basis and retention wording for the privacy policy (§11).

### Resolved
- ~~Archived projects: link-reachable-but-hidden, or hard-disabled?~~ → **hard-disabled** (§9).
- ~~PHP version pin (8.3 vs 8.4)?~~ → **8.4** (§2).