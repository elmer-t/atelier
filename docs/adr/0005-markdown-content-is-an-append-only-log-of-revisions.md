# Markdown content is an append-only log of Revisions

ADR-0001 stored a markdown Artifact's content in an `artifacts.body` column. To give markdown
editing a version history (#6), we move that content out of the Artifact row into an append-only
`artifact_revisions` table: each editor save appends an immutable Revision (`body`, `user_id`,
`created_at`), and the Artifact references the live one via `current_revision_id` instead of
carrying `body` itself. Rendering and the admin editor read through the current Revision (a `body`
accessor delegating to `currentRevision` keeps call-sites unchanged). A byte-identical save is a
no-op. Restoring an older Revision appends a new Revision equal to it and repoints
`current_revision_id`, so history is never rewritten or pruned; a diff is a pure read over two
bodies.

We chose the pointer model over keeping `artifacts.body` as a denormalized current copy. The
denormalized option leaves the fast render path untouched but forces a "body must equal the latest
Revision" invariant; the pointer model keeps a single source of truth at the cost of a join on the
markdown render path and a one-time backfill migration (each existing markdown Artifact becomes its
own Revision 1 before the `body` column is dropped). This makes markdown the one Artifact type
whose payload lives outside the Artifact row — a deliberate deviation from ADR-0001's unified,
type-specific-columns design, justified by the versioning requirement. HTML and File artifacts
remain replace-in-place and unversioned; only markdown content evolves in place.

## Consequences

- Attribution is per-Revision via `user_id`. An editing agent authenticates as its own dedicated
  User, which broadened the `User` definition to any authenticated principal (human or automated).
- Only the body is versioned. An Artifact's `title` stays unversioned metadata on the row; renaming
  never appends a Revision.
- Revision history is Creator-only — never surfaced to Clients, who always see the latest Revision.
- When Comments (ADR-0003/0004) are built, an Anchor on a markdown Artifact will be scoped to the
  Revision it was placed against, so feedback keeps pointing at the exact text it referenced after
  later edits.
