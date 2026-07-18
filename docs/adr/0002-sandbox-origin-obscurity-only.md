# HTML bundles served from an isolated, obscurity-only sandbox origin

Uploaded HTML/CSS/JS showcases are served as static files from a separate, PHP-less subdomain
(the sandbox) and embedded via iframe, never from the app's own origin. Serving arbitrary
uploaded scripts from `atelier.redheadit.nl` would let them reach the app's cookies and
session — a stored-XSS and phishing surface. The cross-origin boundary is the primary
protection.

The deliberately accepted consequence: the sandbox has **no password gate**. A private
project's HTML bundle is protected only by an unguessable URL, so anyone who obtains that URL
can view the bundle regardless of the project's visibility. Therefore confidential material
must live in markdown or file assets (which are genuinely app-gated by the project session),
never in HTML bundles. Truly gated HTML — via short-lived signed/expiring iframe URLs
validated at the sandbox — is deferred until a requirement demands it.
