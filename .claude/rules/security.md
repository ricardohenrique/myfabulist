# Security Rules

Security must be considered by default.

* Never expose secrets.
* Never commit credentials, tokens, private keys, or production configuration.
* Validate and sanitize user input.
* Escape output to prevent XSS.
* Use parameterized queries or ORM query builders to prevent SQL injection.
* Avoid mass assignment vulnerabilities.
* Do not log passwords, tokens, personal data, or confidential payloads.
* Use secure password hashing APIs.
