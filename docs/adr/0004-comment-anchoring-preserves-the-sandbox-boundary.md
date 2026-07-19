# Comment anchoring preserves the sandbox boundary

Comments (#3) anchor to a specific spot on an Artifact, but HTML Artifacts are served
cross-origin from the PHP-less sandbox and, per ADR-0002, never call the app back. Rather than
breach that isolation to get element-level anchors, anchoring is done in an **app-layer overlay
drawn over the iframe**, storing an anchor shaped to each Artifact's origin: markdown → a text
range, image/PDF → image-relative coordinates, HTML → viewport coordinates. The sandbox is
never injected with app code and never messages the app; ADR-0002 stands unchanged.

## Considered options

- **Inject an anchor script into HTML bundles that `postMessage`s the clicked element to the
  app** — rejected. It runs app-controlled code inside untrusted bundles and makes the sandbox
  talk to the app, reopening exactly the isolation ADR-0002 exists to protect, for the payoff of
  prettier anchors on one Artifact type.
- **Uniform viewport coordinates for all three types** — rejected. Markdown reflows and images
  scale, so viewport coordinates drift there too; deliberately inflicting the sandbox's
  unavoidable limitation on Artifacts that can anchor robustly is choosing the worst property
  everywhere.

## Consequences

- The HTML anchor is a viewport coordinate, so a pin can **drift** if the bundle scrolls
  internally or is responsive — an accepted, documented limitation, not a bug.
- "Anchor" is a small polymorphic family (three forms behind one discriminated shape), not a
  single representation.
