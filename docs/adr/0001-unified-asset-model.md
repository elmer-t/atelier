# Single Asset entity for all content types

Markdown documents, HTML bundles, and uploaded files were originally three separate entities
(`Page` and `Attachment`). We unified them into one `Asset` carrying a `type`
(`markdown` | `html` | `file`), because they share one admin flow and one manually ordered
list per project, and mixing types freely in that order (brief → mockup → spec → mockup) is a
core requirement. Type-specific behaviour — serving origin, on-disk cleanup, stage rendering —
lives in per-type handlers keyed off `type`, not in conditionals scattered through the
codebase.

The accepted cost is a table of nullable, type-specific columns (`body` for markdown,
`bundle_path`/`entry_file` for html, `stored_path`/`mime_type`/… for file), only ever
populated for their own type. We chose this over separate tables or single-table inheritance
to keep the model, the admin UI, and the ordered list unified.
