# PHP Engineering Rules

These rules define the default engineering standards.
They must be followed when planning, implementing, reviewing, or refactoring code.

---

## 1. Core Principles

### SOLID

Follow SOLID principles when designing classes and services.

* **Single Responsibility:** each class should have one clear reason to change.
* **Open/Closed:** code should be extendable without modifying stable existing behavior.
* **Liskov Substitution:** subclasses must be safely replaceable by their parent types.
* **Interface Segregation:** prefer small, focused interfaces.
* **Dependency Inversion:** depend on abstractions, not concrete implementations.

---

### DRY

Avoid unnecessary duplication.
Reuse existing abstractions, services, value objects, helpers, or framework features when appropriate.

---

### KISS

Prefer simple, readable solutions over clever or overly generic implementations.

---

### YAGNI

Do not implement functionality that is not currently required.

---

## 2. PHP Standards

* Use modern PHP features when supported by the project version.
* Use strict typing where possible:

```php
declare(strict_types=1);
```

* Add parameter and return types.
* Prefer immutable value objects where appropriate.
* Avoid dynamic properties.
* Prefer constructor dependency injection.

---

## 3. Naming Conventions

* Classes: `PascalCase` (e.g. `UserRepository`, `CreateOrderService`)
* Methods and variables: `camelCase` (e.g. `findById`, `$orderId`)
* Database columns and table names: `snake_case` (e.g. `created_at`, `user_orders`)
* Constants: `UPPER_SNAKE_CASE` (e.g. `MAX_RETRY_COUNT`)
* Interfaces: suffix with `Interface` or use a contract-style name (e.g. `UserRepositoryInterface`)
* Exceptions: suffix with `Exception` (e.g. `InsufficientStockException`)
* Form Requests: suffix with `Request` (e.g. `StoreArtworkRequest`)
* Services: suffix with `Service` (e.g. `ArtworkPublishingService`)

---

## 4. Architecture Rules

* Keep controllers thin.
* Keep business logic out of controllers.
* Keep business logic out of entities/models when it belongs to application services.
* Use services, actions, commands, handlers, or domain objects depending on the project architecture.
* Separate domain logic from infrastructure concerns.
* Avoid mixing persistence, validation, authorization, and business decisions in the same method.

---

## 5. Testing Rules

Add or update tests for meaningful behavior changes.
Prefer testing behavior over implementation details.
Test types may include:

* Unit tests
* Integration tests

Tests should cover:

* Happy paths
* Edge cases
* Invalid input

Avoid brittle tests that depend too much on internal implementation details.

---

## 6. AI-Generated Code Rules

AI-generated code must be treated as untrusted until reviewed.

Before accepting AI-generated code, check:

* Correctness
* Security
* Business rules
* Edge cases
* Test coverage
* Performance
* Architecture consistency
* Maintainability

Do not merge AI-generated code without human review and validation.
Do not paste secrets, private customer data, credentials, or sensitive business data into external AI tools.

---

## 7. Review Checklist

Before finalizing changes, verify:

* [ ] Code follows project conventions.
* [ ] Business logic is in the correct layer.
* [ ] Input validation is present.
* [ ] Authorization is handled.
* [ ] Errors are handled safely.
* [ ] Tests were added or updated.
* [ ] No obvious performance regression exists.
* [ ] No secrets or sensitive data were introduced.
* [ ] Database changes include migrations.
* [ ] Documentation was updated if needed.

---
