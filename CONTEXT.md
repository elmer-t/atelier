# Atelier

A single-operator tool for presenting in-progress website and application concepts to
clients over shareable links. A project bundles an ordered list of assets; recipients view
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
independent of whether the Client holds a User account — today they open a link and never
log in; in future they may authenticate to comment (#3).
_Avoid_: viewer, recipient, guest

<!-- Organization / company and tenant: grouping and isolation terms, still being resolved
     in this session (see #4, #8). Role-vs-entity modeling of Client is a data-model decision
     for #3, deliberately kept out of this glossary. -->


### Content

**Project**:
The unit of sharing: a titled collection of assets presented under one shareable link, with
its own visibility and access rules.
_Avoid_: presentation, showcase, deck

**Asset**:
A single piece of content in a project — a markdown, HTML, or file asset. The one unified
content entity, manually ordered among its siblings.
_Avoid_: page, attachment, content item, resource

**Markdown asset**:
Written content (specs, briefs) authored as raw markdown and rendered to sanitized HTML by
the app at request time.

**HTML asset**:
A self-contained HTML/CSS/JS showcase (mockup, prototype) uploaded as a zip and served from
the sandbox as a bundle. Never calls the app back.

**File asset**:
Any uploaded file (image, PDF, office document, zip, …) streamed to the browser as-is;
Atelier never decodes its format. The browser renders it inline or downloads it.

### Presentation

**Stage**:
The main area of the project page where the selected asset renders, and the placement value
for file assets that open there. Markdown and HTML assets always stage.
_Avoid_: viewer (too generic; the audience is the Client), canvas

**Placement**:
For a file asset, whether it belongs on the stage or in downloads. Markdown and HTML assets
carry no placement — they always stage.

**Download**:
A file asset that only downloads and never opens on the stage; surfaced in the project's
separate downloads list.

**Public index**:
The app's root page listing every active, public project. Private and archived projects
never appear.

### Serving & isolation

**Origin**:
Where an asset is served from, which fixes its protection model. App-origin assets (markdown,
file) are genuinely gated by the project session; sandbox-origin assets (HTML) are
obscurity-only.

**Sandbox**:
The PHP-less, separate-subdomain static host that serves HTML bundles, isolating untrusted
uploaded HTML/JS from the app's origin, cookies, and session.

**Bundle**:
The unpacked directory of an HTML asset — an entry file plus its relative assets — living in
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
not re-checked per asset. Versioned, so rotating the password invalidates it.
_Avoid_: login (clients never log in)
