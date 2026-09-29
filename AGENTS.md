# AGENTS.md

## Purpose

This file defines the rules that AI coding agents must follow when working on the Facility Booking Platform.

The project is being developed as a production-quality portfolio project based on a real-world multi-site facility booking and operations workflow.

Agents must prioritise:

1. Business correctness
2. Data integrity
3. Security
4. Maintainability
5. Testability
6. Accessibility
7. Performance
8. Developer productivity
9. Visual polish

Do not optimise for speed of code generation at the expense of correctness.

---

# 1. Read the Project Documentation First

Before making material changes, read the relevant project documentation.

The primary documentation is:

```text
docs/
├── discovery.md
├── requirements.md
├── design.md
├── visual-design.md
├── architecture.md
└── implementation-plan.md
```

---

# 2. Runtime AI Product Boundaries

AI functionality is an intentional Facility4Hire product capability.

Before implementing runtime AI functionality, agents must follow the approved
scope and behaviour in:

- `docs/requirements.md`;
- `docs/design.md`;
- `docs/visual-design.md`;
- `docs/architecture.md`;
- `docs/implementation-plan.md`.

Agents must not invent AI features outside the documented scope or move
deterministic domain rules into prompts, agent instructions or model output.

AI tools must reuse authorised application/domain services. Model output must
not bypass validation, Policies, permissions, authorisation, transaction
boundaries or booking lifecycle controls.

Provider- and model-specific integration code must remain appropriately
isolated behind the documented Laravel AI SDK boundary.

Core booking, availability, pricing, payment, reporting and operational
functionality must remain usable when an AI provider is unavailable.
