# Security notes for this delivery

See `ARCHITECTURE.md` for authentication, authorization, CSRF, AI privacy, and prompt-injection boundaries.

## Local environment tracking correction

During the final review, `.env.testing` was found in an earlier repository commit. It contained generated local application/test database credentials and a local Laravel encryption key. The local database password and Laravel keys were rotated, and `.env.testing` was removed from tracking. `.env.*` is now ignored with `.env.example` explicitly allowed. No live AI API key was supplied or present in that test file.

Existing Git history was retained, without force-pushing or changing previously published commits. The historical local values are retired; they must never be reused. For any redistribution requiring complete removal of historical values, coordinate a separate history-cleanup operation with the repository owner. The current source tree tracks no real environment file.

The demo users and password in the seeder/README are intentionally public fictional credentials. They must be removed before a production deployment.
