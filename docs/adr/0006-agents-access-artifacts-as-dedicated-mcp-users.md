# Agents access artifacts through an MCP server, as dedicated Users, within a fixed capability boundary

> ADR-0005 is reserved for the Revisions pointer model (#15); this record follows it.

To let AI agents read and write artifacts (#7), we host an **MCP server** that agents connect to
as **dedicated `Agent`-role Users**, authenticated by a revocable Sanctum token, and confined to a
capability boundary of _shape content, don't control exposure_. Agents produce and consume content
as a first-class path — most artifacts are agent-authored — but the human keeps the trust and
sharing decisions.

## The actor: an Agent is a dedicated User, not the Creator and not a separate entity

An Agent is a **non-human User** carrying a third `UserRole` value — `Creator | Client | Agent`,
extending the discriminator introduced for commenting (#14). This was the pivotal decision, and we
reversed our first instinct to reach it: modelling the agent as a mere "mode of access" that _acts
as the Creator_ (attributing its work to the Creator's identity) is simpler on paper, but it makes
human-vs-agent edits impossible to tell apart — and that distinction is the entire point of the
feedback loop. The settled Revisions design (#15) already attributes every edit by `user_id` and
explicitly rejects a polymorphic actor type, so the agent must be a plain User with its own
identity. Its edits are then ordinary attributed Revisions, and provenance reads straight off the
authoring User's role.

## The surface: MCP over a REST API

The agent-facing surface is an MCP server Atelier hosts, not a conventional REST/JSON API. The
consumers are AI agents, which speak MCP natively, and `laravel/mcp` is already a project
dependency; artifacts and unresolved feedback map onto MCP _resources_, and create/update/reply map
onto MCP _tools_. A REST API is more universal, but it would be a second dialect for a consumer that
already speaks the first — if a non-agent integration ever needs it, the service layer underneath
can be re-exposed as HTTP then.

## Auth and scope: account-wide, revocable Sanctum token

The Agent User authenticates with a **Laravel Sanctum personal access token** guarding the MCP web
route (this adds `laravel/sanctum`, a first-party dependency, approved for #7). Scope is
**account-wide** — one Agent User reaching all of the Creator's projects — because this is a
single-operator tool with one human and one agent identity. The token being a _separately leakable_
credential is answered by revocability and by the capability boundary below (encoded as token
abilities), not by fragmenting project visibility; per-project grants are deferred to multi-tenant
or a lower-trust agent.

## The capability boundary: shape content, don't control exposure

- **Authors markdown only** — create, update, delete, reorder markdown Artifacts. Each edit appends
  an attributed Revision (#15). HTML bundles and Files stay human-uploaded: they are unversioned
  (so the agent gets no provenance or feedback on them anyway) and pushing binary payloads through
  MCP tool arguments is clunky.
- **Reads every Artifact type** and **replies** to comments on every type, so the agent has full
  project context and the conversational loop is whole.
- **Cannot Resolve** a Thread and **cannot touch project lifecycle** (visibility, status, slug,
  project create/delete). These aren't bespoke agent checks: Resolve is already Creator-only keying
  off `role` (#14), so an `Agent`-role User is excluded for free, and the same role gate covers the
  rest.

## The loop: pull-based, via a per-project Feedback digest

Feedback flows back by **pull**, not push. There is no persistent agent process to deliver to and no
agent identity to route to, so "feed it back to the originating agent" resolves to: whatever agent
the Creator next runs reads the current feedback state on connect. That state is a synthesized
per-project **Feedback digest** — unresolved Threads plus the Artifacts a human edited since the
Agent's own last Revision on them, each with its diff — computed server-side so the agent needn't
re-derive attention from raw history each session. Push-style autonomy (a comment lands, an agent
wakes unattended) is a larger, later architecture.

## Consequences

- **Hard dependencies.** #7 is buildable only after **#14** (commenting + the `role` discriminator)
  and **#15** (Revisions) land. Both are specified and ready, not yet built.
- **Provenance is markdown-only.** Because only markdown is versioned, the "a human edited this"
  half of the digest exists only for markdown Artifacts; for HTML/File the agent sees current
  content and comments but no change history. Accepted for v1, matching #15's scope.
- **A leaked account-wide token can rewrite every project's markdown.** Mitigated by revocation and
  by the capability boundary (no Resolve, no lifecycle, no non-markdown writes), not by scope
  fragmentation — revisit under multi-tenant or a less-trusted agent.
