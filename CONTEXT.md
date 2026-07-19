# Atelier

A single-operator tool for presenting in-progress website and application concepts to
clients over shareable links. A project bundles an ordered list of artifacts; recipients view
them in the browser and never log in.

## Language

### People

**User**:
Any human with an authenticated login. A pure authentication concept — the roles a User
plays (Creator, Client) layer on top, so the term absorbs new roles without redefinition.
Today the sole User is the Creator.

**Creator**:
The role of a User who builds and presents projects — a developer, designer, or PM
showcasing concepts and gathering feedback. Administers the Atelier account (today, the
single-install operator).
_Avoid_: operator, admin, owner, author

**Client**:
The party a Creator shares a project with for review. A business-relationship role,
independent of whether the Client holds a User account: a Client who only views never does,
while one who comments becomes a User (acquiring the Client role on a User account) so the
comment is attributable (#3).
_Avoid_: viewer, recipient, guest

**Organization**:
A grouping of Clients within a single Tenant — a client company or team whose members share
visibility of that Tenant's projects (#4). An audience-side concept: it groups Clients, it
never spans Tenants.
_Avoid_: company, account

**Tenant**:
The isolation boundary of a hosted (SaaS) Atelier — one subscriber's private world of
Creator(s), projects, Clients, and styling, sealed off from every other subscriber's. A
Creator-side concept. Self-hosted today, the whole installation is a single implicit Tenant,
which is why the term is otherwise invisible.
_Avoid_: instance, workspace

<!-- How a commenting Client's identity is realised (a passwordless User, upgradeable) is a
     data-model decision recorded in ADR-0003, kept out of this glossary (which stays
     implementation-free). -->

**Agent**:
A non-human User that reads and writes artifacts on the Creator's behalf. It holds its own
distinct identity — a dedicated User — so its edits are attributed to _it_, which is exactly how
human and Agent changes are told apart in an Artifact's Revision history (#15). It operates
within the Creator's projects but is never the Creator. Since most artifacts are Agent-authored,
the Agent is a first-class producer and consumer of content, not an afterthought.
_Avoid_: bot, integration, API client; and "acts as the Creator" (it acts _for_ the Creator
under its own identity, not _as_ them)


### Content

**Project**:
The unit of sharing: a titled collection of artifacts presented under one shareable link, with
its own visibility and access rules.
_Avoid_: presentation, showcase, deck

**Artifact**:
A single piece of content in a project — a markdown, HTML, or file artifact. The one unified
content entity, manually ordered among its siblings. The term names the entity only; the
leftover files an artifact leaves on disk are its "on-disk files", never "artifacts".
_Avoid_: asset, page, attachment, content item, resource

**Markdown artifact**:
Written content (specs, briefs) authored as raw markdown and rendered to sanitized HTML by
the app at request time.

**HTML artifact**:
A self-contained HTML/CSS/JS showcase (mockup, prototype) uploaded as a zip and served from
the sandbox as a bundle. Never calls the app back.

**File artifact**:
Any uploaded file (image, PDF, office document, zip, …) streamed to the browser as-is;
Atelier never decodes its format. The browser renders it inline or downloads it.

### Presentation

**Stage**:
The main area of the project page where the selected artifact renders, and the placement value
for file artifacts that open there. Markdown and HTML artifacts always stage.
_Avoid_: viewer (too generic; the audience is the Client), canvas

**Placement**:
For a file artifact, whether it belongs on the stage or in downloads. Markdown and HTML artifacts
carry no placement — they always stage.

**Download**:
A file artifact that only downloads and never opens on the stage; surfaced in the project's
separate downloads list.

**Public index**:
The app's root page listing every active, public project. Private and archived projects
never appear.

### Feedback

**Comment**:
A piece of feedback left on an Artifact, attached to a specific spot on it and attributed to
the name its author gave. Left by a Client or the Creator.
_Avoid_: note, annotation, remark

**Anchor**:
The specific spot on an Artifact a Comment marks — a passage of a markdown Artifact, a region
of an image or PDF, or a point on an HTML mockup. Every top-level Comment has one.
_Avoid_: pin, marker, location

**Thread**:
A top-level Comment together with its Replies, all sharing the one Anchor. The unit that is
Resolved.
_Avoid_: conversation, discussion

**Reply**:
A Comment made in response to another within its Thread. Carries no Anchor of its own and is
never itself replied to — threads are one level deep.
_Avoid_: response, answer

**Resolved**:
The state of a Thread the Creator has marked as handled, closing the feedback loop. Reversible,
and only the human Creator sets it — an Agent (a non-human User) may reply to a Thread but
never Resolve it, so a human eye always gates the loop.
_Avoid_: closed, done, archived

### Serving & isolation

**Origin**:
Where an artifact is served from, which fixes its protection model. App-origin artifacts (markdown,
file) are genuinely gated by the project session; sandbox-origin artifacts (HTML) are
obscurity-only.

**Sandbox**:
The PHP-less, separate-subdomain static host that serves HTML bundles, isolating untrusted
uploaded HTML/JS from the app's origin, cookies, and session.

**Bundle**:
The unpacked directory of an HTML artifact — an entry file plus its relative files — living in
sandbox storage.

**Entry file**:
The HTML file a bundle opens at (e.g. `index.html`); the target of the iframe that embeds the
bundle.

**Slug**:
The unguessable, CSPRNG token in a project's shareable link. Rotating it invalidates any
previously shared link.

**Sandbox token**:
The unguessable per-project token in bundle URLs, kept separate from the slug so sandbox
paths can't be derived from the shareable link.

### Access

**Visibility**:
Whether a project is _private_ (password-gated) or _public_ (open to anyone with the link and
listed on the public index). There is no half-public state.

**Status**:
Whether a project is _active_ (reachable) or _archived_ (hard-disabled — every route,
including the password gate, returns 404). Un-archiving restores access.

**Project session**:
The per-project flag remembering that a viewer passed the password gate, so the password is
not re-checked per artifact. Versioned, so rotating the password invalidates it.
_Avoid_: login (clients never log in)
