# Agent Task Template

This file is a reusable template only.
Do not implement directly from this file unless all placeholders have been replaced.

Use this template for bounded implementation tasks inside the project.

The purpose of this template is to make AI-assisted implementation faster, safer, more consistent, and easier to review.

The task-specific sections should be filled in before sending the implementation prompt to a coding agent.

---

## 1. Task Identity

### Milestone
[Example: M07 — Booking Request & Provisional Protection]

### Batch
[Example: M07-A — Booking Domain & Persistence]

### Objective
[Describe exactly what this batch should achieve in 1–3 sentences.]

### Business Value
[Explain why this work matters to the customer, operator, manager, or business.]

---

## 2. Current Project State

### Current Branch
`main`

### Relevant Completed Milestones
- [Milestone]
- [Milestone]
- [Milestone]

### Relevant Recent Commits
- `[hash]` — [commit message]
- `[hash]` — [commit message]

### Expected Starting State

```text
clean working tree
```

---

## 3. Read Before Coding

Inspect all relevant documentation and existing codebase implementation before making any code modifications.

### Relevant Documentation
- `requirements.md`
- `architecture.md`
- `implementation-plan.md`
- `design.md`
- `visual-design.md`
- `discovery.md`
- `AGENTS.md`
- [other relevant project documentation]

### Relevant Files, Services, Models, Actions, Controllers, and Tests
- Models: `[app/Models/...]`
- Actions/Services: `[app/Actions/... / app/Services/...]`
- Controllers/Resources: `[app/Http/Controllers/... / app/Filament/... / app/Data/...]`
- Frontend Components: `[resources/js/...]`
- Tests: `[tests/Feature/... / tests/Unit/...]`

### Rules of Discovery
- Business rules must not be invented under any circumstances.
- Material documentation or implementation conflicts must be reported before making speculative changes.

---

## 4. Implementation Scope

1. [Task 1 Placeholder]
2. [Task 2 Placeholder]
3. [Task 3 Placeholder]
4. [Task 4 Placeholder]

### Scope Principles
- Implementation must be small, cohesive, and directly tied to the assigned task.
- Unnecessary abstractions must not be introduced.
- Unrelated refactors must not be performed unless strictly necessary for the immediate task.

---

## 5. Permanent Architecture Rules

### Application Architecture
- Laravel is the authoritative backend.
- MySQL is the authoritative datastore.
- Vue/Inertia is the customer-facing frontend architecture.
- Filament is used for relevant staff/management interfaces.
- Keep controllers thin.
- Important business behavior belongs in explicit Actions, Services, domain objects, or appropriate testable application-layer components.
- Prefer explicit, maintainable solutions over clever abstractions.
- Reuse existing project patterns before introducing new architectural styles.
- Avoid unnecessary packages, framework, or infrastructure changes.

### Availability
- `AvailabilityService` is authoritative.
- Recalculate availability server-side for authoritative actions.
- Browser availability state is never authoritative.
- Physical conflicts respect Allocation Units.
- Setup/cleanup remain part of operational occupancy where applicable.
- Expired temporary protection is logically inactive even if cleanup has not run.

### Pricing
- `PricingService` is authoritative.
- Browser-submitted price, totals, rates, discounts, currency, or overrides are not authoritative.
- Money uses integer minor units.
- Pricing remains deterministic and explainable.
- Accepted booking pricing should use immutable snapshots where required.
- Quote calculation remains side-effect-free unless explicitly changed later.

### Authorization
Use layered authorization:
- Authentication;
- Broad Spatie permission/capability;
- Panel or policy instance scope;
- Action/Service enforcement where appropriate;
- Audit relevant authoritative state changes.

Additionally:
- No blanket manager bypass.
- Centre assignment alone must not grant staff capabilities.

### Security and Data Integrity
- Validate authoritative server input.
- Prevent cross-customer access.
- Prevent cross-centre access where applicable.
- Use appropriate foreign keys and database constraints.
- Apply restrictive deletion where required.
- Never trust browser-controlled status, ownership, availability, pricing, payment, or permissions.
- Do not expose unnecessary internal configuration or diagnostics publicly.
- No secrets, credentials, PII, or private historical company data in code or logs.
- Preserve transactional integrity.
- Use database constraints for reliable, database-enforceable invariants.

### AI Constraints
AI must not be authoritative for:
- Availability;
- Pricing;
- Booking status;
- Permissions;
- Payments;
- Financial state;
- Transactional integrity.

AI should only be introduced when explicitly required by the current milestone.

---

## 6. Task-Specific Architecture Rules

1. [Task-Specific Rule 1 Placeholder]
2. [Task-Specific Rule 2 Placeholder]
3. [Task-Specific Rule 3 Placeholder]

---

## 7. Explicitly Out of Scope

1. [Future Feature Placeholder 1]
2. [Future Feature Placeholder 2]
3. [Future Feature Placeholder 3]

### Scope Enforcement
- Do not helpfully implement future milestone functionality.
- Do not assume functionality from future milestones exists.
- Report dependencies instead of silently expanding scope.

---

## 8. Transaction and Concurrency Rules

This section applies when tasks involve authoritative state changes, booking creation, availability protection, equipment allocation, money, or concurrency.

- Use explicit database transactions where relevant.
- Lock the minimum authoritative state required.
- Revalidate authoritative conditions inside the transaction.
- Failures must roll back completely.
- Do not rely on previous browser availability checks.
- Do not rely on client-side pricing.
- Simultaneous conflicting operations must not both succeed.
- Use real MySQL behavior for concurrency-sensitive tests.
- Keep lock scopes narrow.
- Avoid read-then-write race conditions outside transactions.
- Do not claim concurrency safety unless it is actually tested.

---

## 9. Tests Required

Consider testing the following where applicable:
- Happy path;
- Validation;
- Authorization;
- Ownership / isolation;
- Database constraints;
- Business-rule failures;
- Edge cases;
- Regressions;
- State transitions;
- Read-only behavior;
- Rollback;
- Concurrency.

### Task-Specific Test Placeholders
1. [Test Case 1 Placeholder]
2. [Test Case 2 Placeholder]
3. [Test Case 3 Placeholder]
4. [Test Case 4 Placeholder]

### Test Rules
- Do not mock away important domain behavior.
- Prefer externally meaningful tests.
- Use real MySQL where MySQL locking, transaction, or query semantics matter.

---

## 10. Manual Acceptance

### Scenario A
[Steps and expected result]

### Scenario B
[Steps and expected result]

### Scenario C
[Steps and expected result]

### Verification Rules
- Never claim manual acceptance unless actually performed.
- State explicitly when manual verification is not applicable.

---

## 11. Validation Workflow

First, run the focused validation example:

`./vendor/bin/sail artisan test --compact tests/Feature/RelevantTest.php`

Then, run full validation:

`./vendor/bin/sail composer ci:check`

Finally, inspect working tree state:

`git diff --check`
`git status --short`
`git diff --stat`

### Workflow Rules
- Where frontend assets or types were changed, ensure the existing project CI covers formatting, linting, TypeScript, and build requirements.
- Report unavailable Docker, Sail, or local environment tools explicitly.
- Never claim commands passed if they were not run.

---

## 12. Self-Review Before Finishing

Before reporting completion, review the complete diff and verify:

- requested scope matches implementation;
- no future milestone leakage;
- no unrelated refactors;
- no accidental generated-file churn;
- no secrets or private data;
- authorization remains correct;
- ownership/isolation remains correct;
- browser input has not become authoritative;
- no unnecessary business-logic duplication;
- architecture remains intact;
- tests genuinely exercise behavior;
- deliberate error handling;
- database integrity;
- clear naming;
- useful comments;
- safe migrations;
- no accidental broad schema or framework changes;
- no unnecessary public API fields;
- no hidden future-milestone dependencies.

If anything is uncertain, report it.

---

## 13. Git Safety Rules

Do not:

- commit;
- push;
- reset;
- rebase;
- rewrite history;
- run broad destructive cleanup commands;
- restore unrelated user changes;
- remove files outside current task scope;
- stage unrelated generated files.

Leave implementation changes in working tree for human review.

Preserve unrelated existing changes.

Unless the human explicitly instructs you to do so, do not execute broad destructive commands such as:

`git clean -fd`
`git reset --hard`

---

## 14. Final Report Format

Finish with a concise implementation report containing:

Implemented:
[Summary]

Files Created:
[File]

Files Changed:
[File]

Architecture Decisions:
[Decision]

Tests Added or Updated:
[Test]

Focused Validation:
[Result]

Full CI:
[Result]

git diff --check:
[Result]

git status --short:
[Result]

git diff --stat:
[Result]

Manual Verification:
[Completed / Not completed / Not applicable]
[Result]

Deferred / Out of Scope:
[Item]

Risks or Uncertainty:
[Item]

Do not simply report "done". Be precise about what was implemented, what was verified, what was not verified, and what remains outside the task.
