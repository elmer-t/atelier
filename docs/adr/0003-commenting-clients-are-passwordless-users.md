# Commenting Clients are passwordless Users

To attribute comments (#3) we need a persistent identity for Clients, who today never log in.
We model a commenting Client as a **`User` with the Client role, created passwordless on their
first comment** — not as a separate commenter entity — so the identity is upgradeable later
(by simply adding a credential) with no migration and no re-parenting of comments. This keeps
the existing model's promise that "User is a pure authentication concept; roles layer on top,"
and gives #4 (organizations) a single identity to attach membership to.

Identity is captured at **comment time, not view time**: pure viewers stay anonymous and the
shared slug / public index are untouched; only someone who chooses to comment is asked for a
**name and email**, and the comment posts immediately with no verification round-trip. The
email is the stable anchor that re-binds the same person across sessions and devices and seeds
a future upgrade; verification is deferred to that upgrade.

## Considered options

- **Separate commenter entity, migrated into a User on upgrade** — rejected. Migrating rows and
  re-parenting every comment, notification, and mention is the one genuinely hard-to-reverse,
  painful operation in the whole design, and it forces every downstream feature (#4 visibility,
  permissions) to branch forever on "commenter or User?".
- **Email-verified magic link before the first comment** — rejected for the entry path. The
  inbox round-trip loses feedback at the exact moment a Client has something to say, defeating
  the frictionless-participation goal that motivates the feature. Verification rides the upgrade
  path instead.

## Consequences

- A `User` can now exist without a credential or a verified email; code that assumed
  "User ⇒ has credentials / verified email" must soften.
- **Email squatting** is possible (a Client can type an address that isn't theirs). Unverified
  Users are treated as un-authoritative — `email_verified_at` stays null and verification is
  required before any upgrade or org-level trust.
- Remembered identity is cookie-scoped **per-install**, which equals per-Tenant under today's
  single-tenant reality. A multi-tenant Atelier MUST narrow this to per-Tenant and never ship a
  global cookie.
- Creator notifications go to the Creator's verified email; Client reply-notifications are
  deferred until a Client verifies their address via upgrade.
