# Implementation Plan

## 1. Purpose

This document defines the implementation sequence for the Multi-Site Facility Booking & Operations Platform.

It translates the approved discovery, requirements, design and architecture into an incremental delivery plan.

The purpose of this plan is to:

- define the order in which the application should be built;
- keep implementation aligned with approved requirements;
- prevent premature implementation of later functionality;
- provide clear boundaries for AI coding agents;
- ensure testing is performed alongside implementation rather than afterwards;
- maintain traceability between requirements, design, architecture, implementation and tests;
- deliver working vertical slices rather than disconnected technical layers;
- reduce scope creep;
- provide clear acceptance criteria for every milestone.

This document defines **how and when the approved system will be implemented**.

It does not replace:

- `docs/discovery.md`;
- `docs/requirements.md`;
- `docs/design.md`;
- `docs/architecture.md`.

Those documents remain authoritative for their respective concerns.

---

# 2. Document Hierarchy

Implementation decisions should follow this hierarchy:

1. `docs/discovery.md`
2. `docs/requirements.md`
3. `docs/design.md`
4. `docs/architecture.md`
5. `docs/implementation-plan.md`
6. Current implementation task
7. Existing implementation where it does not conflict with the documents above

The documents have different responsibilities.

## Discovery

Defines:

- historical process;
- evidence;
- business problems;
- stakeholder needs;
- uncertainties;
- product opportunities.

## Requirements

Defines:

- what the system must do;
- functional requirements;
- non-functional requirements;
- MVP scope;
- business rules.

## Design

Defines:

- user experience;
- information architecture;
- interface principles;
- interaction patterns;
- responsive behaviour;
- accessibility expectations.

## Architecture

Defines:

- application structure;
- domain boundaries;
- persistence strategy;
- authorization architecture;
- concurrency strategy;
- integration boundaries;
- deployment direction;
- AI architecture.

## Implementation Plan

Defines:

- delivery sequence;
- milestone boundaries;
- dependencies;
- implementation scope;
- testing expectations;
- acceptance criteria.

If documents appear to conflict, implementation must stop and the conflict must be surfaced rather than silently resolved by inventing behaviour.

---

# 3. Delivery Strategy

The application will be delivered through incremental milestones.

Each milestone should leave the repository in a coherent, working and tested state.

The preferred approach is:

> Vertical slices over isolated technical layers.

Where practical, a milestone should connect the relevant:

- persistence;
- domain behaviour;
- authorization;
- application services/actions;
- user interface;
- automated tests.

Avoid building large amounts of unused infrastructure for hypothetical future requirements.

---

# 4. Implementation Principles

## 4.1 Business Rules Before UI Convenience

User interfaces must use authoritative server-side business behaviour.

Vue, Filament or browser state must not become the source of truth for:

- availability;
- pricing;
- authorization;
- booking state;
- payment state;
- invoice state;
- resource conflicts.

---

## 4.2 Correctness Before Polish

Priority order should generally be:

1. domain correctness;
2. data integrity;
3. authorization;
4. automated tests;
5. usable workflow;
6. accessibility;
7. performance;
8. visual polish.

---

## 4.3 Tests Are Part of Implementation

Testing is not a final project phase.

Every milestone must include the automated tests necessary to demonstrate its behaviour.

A feature is not considered complete merely because it works manually.

---

## 4.4 Database Integrity Matters

Important invariants should be protected by the database where practical.

The application should use appropriate:

- foreign keys;
- indexes;
- transactions;
- locking;
- constraints;
- uniqueness rules.

MySQL is the authoritative production database.

Concurrency-sensitive behaviour must be tested against real MySQL semantics.

---

## 4.5 Thin Interfaces

Controllers, Inertia endpoints and Filament components should delegate business behaviour to:

- Actions;
- Services;
- Policies;
- domain-specific query capabilities.

Avoid embedding significant business logic directly inside UI components or controllers.

---

## 4.6 AI Is a Planned Capability, Not Core Infrastructure

The application must operate without an AI provider.

AI is a first-class planned Facility4Hire capability, implemented after the
deterministic domain and application services it depends upon.

AI must not become authoritative for:

- availability;
- pricing;
- authorization;
- booking state;
- payment state;
- invoices;
- refunds;
- operational closures.

The implementation sequence is:

``` text
Core domain
-> application services
-> authorised AI tools
-> AI agents
-> AI user experience
```

---

# 5. AI Coding Agent Rules

AI coding agents must follow these rules.

## 5.1 Read Before Implementing

Before material implementation work, read the relevant sections of:

- `docs/requirements.md`;
- `docs/design.md`;
- `docs/architecture.md`;
- `docs/implementation-plan.md`.

---

## 5.2 Current Milestone Only

Implement only:

- the current milestone;
- the explicitly assigned task within that milestone.

Do not implement later milestones merely because the architecture anticipates them.

---

## 5.3 Do Not Invent Requirements

If behaviour is unclear:

1. inspect the approved documentation;
2. inspect relevant existing code/tests;
3. surface the ambiguity if it remains.

Do not silently invent business rules.

---

## 5.4 Preserve Architecture

Do not introduce:

- microservices;
- a separate SPA API;
- new frameworks;
- new state-management systems;
- new authorization approaches;
- alternative payment architecture;
- alternative AI architecture

without an explicit architecture decision.

---

## 5.5 Keep Changes Focused

Avoid unrelated refactors.

Do not modify unrelated working functionality merely because an alternative implementation appears preferable.

---

## 5.6 Tests Must Accompany Behaviour

New business behaviour requires appropriate automated tests.

Bug fixes should normally include a regression test.

---

## 5.7 No Future-Milestone Leakage

If a future capability is needed only later, leave the appropriate architectural seam without implementing the future feature.

---

# 6. Milestone Structure

Each milestone uses:

- Objective
- Business Value
- Requirements Covered
- Dependencies
- Implement
- Do Not Implement Yet
- Architecture Rules
- Tests Required
- Acceptance Criteria
- Definition of Done

Requirement IDs should be refined against `requirements.md` when implementation tasks are created.

---

# Phase 1 — Foundation

# M01 — Laravel Project Foundation

## Objective

Create a reproducible Laravel development environment and frontend foundation without implementing product functionality.

## Business Value

Provides a reliable development base and reduces environment-related delivery friction.

## Requirements Covered

Primarily technical foundations supporting the non-functional requirements.

## Dependencies

None.

## Implement

- Laravel application.
- Laravel Sail.
- MySQL.
- Redis.
- Inertia.
- Vue.js.
- TypeScript.
- Vite.
- shadcn/ui foundation.
- PHP dependencies.
- JavaScript dependencies.
- environment configuration.
- automated test environment.
- local development documentation.
- basic code-quality commands.
- frontend production build.
- basic early CI verification where practical.

## Do Not Implement Yet

- Filament.
- Spatie Permission.
- Spatie Activitylog.
- Cashier.
- Stripe.
- Laravel AI SDK.
- centres.
- facilities.
- bookings.
- AWS.

## Architecture Rules

The project remains a modular Laravel monolith.

No separate frontend API architecture should be introduced.

## Tests Required

Verify:

- Laravel tests execute;
- MySQL connection;
- Redis connection;
- frontend build;
- TypeScript checking;
- Sail environment startup.

## Acceptance Criteria

A developer can clone the repository, follow the documented setup and start the application successfully.

## Definition of Done

The application starts reliably through the documented development workflow and provides a stable foundation for product development.

---

# M02 — Application Shells & Authentication

## Objective

Create the three application experiences and shared authentication foundation.

## Business Value

Provides the entry points required by customers, management and operational staff.

## Requirements Covered

Relevant USER, SEC, ACCESS, RESP and design requirements.

## Dependencies

M01.

## Implement

Public/customer:

- Inertia;
- Vue;
- TypeScript;
- shadcn/ui;
- registration;
- login;
- logout;
- password reset;
- email verification where appropriate;
- basic profile/account management.

Management:

- Filament Management panel.

Operations:

- Filament Operations panel.

Also implement:

- initial navigation;
- application layouts;
- responsive shell foundations.

## Do Not Implement Yet

- full roles/permissions;
- venue domain;
- booking functionality;
- availability;
- Stripe;
- reports;
- AI.

Temporary development access may be used only until M03 replaces it with real authorization.

## Architecture Rules

All experiences use the same Laravel application and authentication system.

## Tests Required

Test:

- registration;
- login/logout;
- password reset;
- email verification where applicable;
- guest route protection;
- authenticated route protection;
- basic panel access;
- account isolation foundations.

## Acceptance Criteria

A customer can register and sign in.

Management and Operations Filament shells exist and are ready for authorization.

## Definition of Done

All three application experiences exist with functioning shared authentication.

---

# M03 — Authorization & Audit Foundation

## Objective

Establish server-side authorization and auditing before significant business functionality is added.

## Business Value

Protects customer and business data and creates accountability for staff actions.

## Requirements Covered

Relevant USER, AUDIT, SEC and DATA requirements.

## Dependencies

M02.

## Implement

- Spatie Laravel Permission.
- Spatie Activitylog.
- broad system roles/capabilities.
- Customer role/access concept.
- Manager role/access concept.
- Leisure Assistant role/access concept.
- Filament panel authorization.
- initial Laravel Policies.
- centre-access foundation.
- audit configuration.
- patterns for auditable Actions.

## Do Not Implement Yet

- sophisticated organisation permissions;
- permission-per-button systems;
- enterprise-configurable roles;
- booking-specific authorization;
- advanced audit dashboards.

## Architecture Rules

Authorization stack:

Laravel Authentication  
→ Spatie Permission  
→ Laravel Policies  
→ Actions/Services  
→ Audit

Organisation membership permissions remain separate from system roles.

## Tests Required

Verify:

- customer cannot access staff panels;
- leisure assistant cannot access Management;
- manager can access Management;
- unauthorized direct URLs fail server-side;
- role/permission changes work;
- representative audited operations create activity records.

## Acceptance Criteria

Role boundaries are enforced by the server rather than hidden navigation.

## Definition of Done

The application has a reliable authorization and audit foundation.

---

# M04 — Venue Domain Foundation

## Objective

Model the physical venue structure required by the booking engine.

## Business Value

Allows management to configure the real physical resources that customers will eventually book.

## Requirements Covered

Relevant CENTRE, FAC, EQUIP and HOURS requirements.

## Dependencies

M03.

## Implement

- Centre.
- Facility.
- Resource.
- atomic Allocation Units.
- Resource-to-Allocation-Unit mapping.
- Equipment.
- equipment quantities.
- basic centre opening hours.
- facility bookable hours.
- setup/cleanup configuration foundations.
- active/inactive states where required.
- staff centre access assignment.
- Filament management configuration.
- fictional development seed data.

Example:

Whole Sports Hall → A+B+C+D  
Court 1 → A  
Court 2 → B

## Do Not Implement Yet

- calculated availability;
- customer booking;
- pricing;
- payments;
- recurring bookings;
- reporting;
- AI;
- sophisticated equipment fault management.

## Architecture Rules

Facility is the customer-facing facility concept.

Resource represents a bookable option.

Allocation Units represent the atomic physical capacity used for conflict detection.

## Tests Required

Test:

- relationships;
- resource/allocation mapping;
- invalid cross-centre relationships;
- equipment quantity validation;
- management authorization;
- staff centre access;
- configuration CRUD.

## Acceptance Criteria

A manager can configure a fictional centre containing facilities, divisible resources, physical allocation units, equipment and bookable hours.

## Definition of Done

The physical venue model required by the booking engine exists and is tested.

---

# Phase 2 — Booking Engine

# M05 — Availability & Physical Allocation Engine

## Objective

Create the authoritative capability for determining whether requested physical capacity and equipment are available.

## Business Value

Enables reliable self-service availability while reducing double-booking risk.

## Requirements Covered

Relevant AVAIL, FAC, EQUIP, HOURS and DATA requirements.

## Dependencies

M04.

## Implement

Create the central `AvailabilityService`.

Availability should consider:

- facility bookable hours;
- requested period;
- physical Allocation Units;
- existing protected occupancy;
- active provisional protection where applicable;
- basic Availability Blocks/blockouts;
- setup/cleanup occupancy;
- equipment quantities;
- active/inactive resources.

Introduce the minimum persistence required to represent occupied capacity for availability testing without exposing customer booking workflow yet.

Introduce structured availability results.

Example reasons:

- outside bookable hours;
- resource conflict;
- blockout;
- equipment unavailable;
- inactive resource.

Create the first customer-facing availability selection experience.

A focused JSON endpoint may be used where appropriate.

## Do Not Implement Yet

- customer booking submission;
- manager approval;
- Stripe;
- invoices;
- recurring series;
- waiting lists;
- AI alternatives.

## Architecture Rules

Vue must not calculate authoritative availability.

Availability remains dynamically calculated.

No authoritative `available` boolean should be persisted.

Basic Availability Blocks introduced here support calculation; the complete closure impact workflow belongs to M15.

## Tests Required

Test:

- inside bookable hours;
- outside hours;
- existing occupancy;
- adjacent non-overlap;
- setup/cleanup overlap;
- active protection;
- expired protection behaviour;
- blockout;
- parent/child conflict;
- sibling resources;
- equipment available;
- equipment exhausted;
- time boundaries;
- inactive resources.

## Acceptance Criteria

Given a centre, resource, date, time and duration, the system can reliably determine and explain whether the request is available.

## Definition of Done

The application has one authoritative availability capability reusable by all later booking workflows.

---

# M06 — Pricing & Quotes

## Objective

Calculate an explainable price before a booking is created.

## Business Value

Allows customers and management to understand booking cost consistently while supporting centre/resource pricing differences.

## Requirements Covered

Relevant PRICE requirements.

## Dependencies

M05.

## Implement

Create a dedicated `PricingService`.

Support the initial required pricing dimensions:

- centre;
- resource;
- duration;
- effective date;
- customer classification where implemented;
- equipment;
- discount;
- authorised manager override.

Create structured `PriceQuote` behaviour.

Use a safe money representation.

Add sufficient Filament configuration for realistic fictional rates.

Support effective dating where appropriate.

## Do Not Implement Yet

- promotional engine;
- coupon platform;
- dynamic AI pricing;
- sophisticated event quotation system.

## Architecture Rules

The pricing service is authoritative.

Browser-submitted prices must never be trusted.

## Tests Required

Test:

- resource price;
- centre variation;
- duration calculation;
- equipment;
- effective date;
- customer classification where implemented;
- discount;
- authorised override;
- override reason;
- rounding;
- missing configuration.

## Acceptance Criteria

An available booking selection can produce a deterministic and explainable quote.

## Definition of Done

Pricing is centralised, testable and reusable by customer and management workflows.

---

# M07 — Booking Request & Provisional Protection

## Objective

Allow customers to convert an available selection into a protected booking request.

## Business Value

Creates the first major end-to-end customer transaction while preventing competing customers from reserving the same physical capacity.

## Requirements Covered

Relevant BOOK, HOLD, AVAIL, DATA, SEC and CUSTOMER requirements.

## Dependencies

M05 and M06.

## Implement

Create the central Booking aggregate and required allocation relationships.

Customer booking journey:

1. Facility & Time
2. Booking Details
3. Equipment/Add-ons
4. Customer context
5. Price/Summary
6. Review
7. Submit

On submission:

1. begin database transaction;
2. lock relevant allocation/equipment state;
3. revalidate availability;
4. validate equipment;
5. validate/recalculate authoritative pricing;
6. create Booking;
7. create allocations;
8. create equipment allocations;
9. create immutable price snapshot;
10. create provisional protection/expiry;
11. commit.

The customer should receive a booking reference and clear `Requested / Awaiting Management Approval` state.

## Do Not Implement Yet

- manager approval;
- Stripe;
- invoices;
- confirmation;
- recurring series;
- cancellation workflow;
- AI.

## Architecture Rules

The browser cannot submit authoritative:

- price;
- availability;
- booking status.

Submission must revalidate inside the transaction.

Expired protection must stop affecting availability based on its expiry state, not merely after a cleanup job executes.

## Tests Required

Test:

- valid request;
- unavailable resource;
- equipment unavailable;
- price snapshot;
- resource allocation;
- protection;
- ownership;
- cross-customer protection;
- price tampering;
- expiry;
- transaction rollback;
- concurrent request conflict.

Real MySQL concurrency verification begins here.

## Acceptance Criteria

A customer can discover availability and safely submit a protected booking request.

Two simultaneous conflicting requests cannot both succeed.

## Definition of Done

The platform supports safe transactional booking requests with provisional resource protection.

---

# M08 — Management Review & Booking Lifecycle

## Objective

Allow authorised management to review and decide booking requests through explicit domain actions.

## Business Value

Preserves management control while replacing manual booking approval administration.

## Requirements Covered

Relevant APPROVAL, BOOK, AUDIT and SEC requirements.

## Dependencies

M07.

## Implement

Management:

- pending request list;
- booking review screen;
- customer context;
- centre;
- activity;
- date/time;
- resources;
- equipment;
- price;
- warnings;
- relevant history.

Explicit Actions such as:

- `ApproveBooking`;
- `RejectBooking`.

Actions must validate:

- authorization;
- booking state;
- continued validity;
- applicable business rules.

Approval of a standard future card booking moves toward `Awaiting Payment`.

It must not automatically mean `Confirmed`.

Rejection should:

- record reason;
- update lifecycle;
- release protection where appropriate;
- create history/audit.

## Do Not Implement Yet

- Stripe;
- refunds;
- invoice settlement;
- sophisticated cancellation;
- AI approval.

## Architecture Rules

Do not expose generic direct booking-status editing through Filament.

Lifecycle changes occur through explicit Actions.

## Tests Required

Test:

- authorised review;
- customer cannot approve;
- leisure assistant cannot approve;
- valid approval;
- valid rejection;
- invalid transitions;
- duplicate action safety;
- released protection;
- lifecycle history;
- audit.

## Acceptance Criteria

Management can safely approve or reject booking requests.

## Definition of Done

The first controlled booking lifecycle exists and is protected by authorization and business rules.

---

# M09 — Recurring Bookings

## Objective

Support recurring/block bookings while representing each occurrence as a genuine booking.

## Business Value

Supports clubs and organisations that repeatedly hire facilities while maintaining accurate availability.

## Requirements Covered

RECUR requirements and related AVAIL/BOOK requirements.

## Dependencies

M08.

## Implement

Introduce:

- `BookingSeries`;
- persisted Booking occurrences.

Each occurrence must use:

- AvailabilityService;
- PricingService;
- allocation rules;
- equipment rules.

Validate every requested occurrence.

Explicitly return conflicting dates.

Never silently omit conflicting occurrences.

Support individual occurrence identity and later amendment/cancellation capability.

Where multi-occurrence operations exist, scope must be explicit.

Examples:

- this occurrence;
- this and future occurrences;
- entire series.

## Do Not Implement Yet

- every possible recurrence rule;
- external calendar integrations;
- AI scheduling;
- automated waiting lists.

## Architecture Rules

Recurring bookings must reuse the one-off booking engine.

Do not build a second recurring availability system.

## Tests Required

Test:

- series creation;
- occurrence generation;
- persisted occurrences;
- per-occurrence availability;
- pricing;
- equipment;
- explicit conflicts;
- no silent dropping;
- occurrence independence;
- authorization.

## Acceptance Criteria

A realistic recurring club/block request can be represented without compromising one-off booking integrity.

## Definition of Done

The booking engine supports both one-off and recurring bookings.

---

# Phase 3 — Commercial Workflow

# M10 — Stripe / Laravel Cashier Payments

## Objective

Allow approved card-paying customers to pay securely and confirm bookings only after authoritative payment success.

## Business Value

Introduces online payment and removes major manual payment friction.

## Requirements Covered

Relevant PAY requirements.

## Dependencies

M08.

## Implement

- Laravel Cashier for Stripe.
- application-owned Payment records.
- PaymentService.
- payment initiation.
- provider references.
- payment status.
- amount/currency.
- booking relationship.
- verified Stripe webhooks.
- webhook idempotency.
- payment deadline.
- failed/processing states where applicable.

Standard card flow:

Request  
→ Hold  
→ Approval  
→ Awaiting Payment  
→ Payment Successful  
→ Confirmed

The server derives payment amount from the authoritative booking price snapshot.

## Do Not Implement Yet

- subscriptions;
- saved-card product;
- complex deposit workflows;
- accounting integrations;
- promotional payment features.

## Architecture Rules

Cashier does not own the booking domain.

Browser redirects are not authoritative payment confirmation.

Verified provider events are authoritative.

## Tests Required

Test:

- eligible payment initiation;
- unapproved booking cannot pay;
- server-derived amount;
- successful provider event;
- booking confirmation;
- redirect alone does not confirm;
- failed payment;
- duplicate webhook;
- invalid webhook;
- ownership;
- expiry;
- separate payment/booking states;
- audit/history.

## Acceptance Criteria

A standard card customer can complete:

Request → Approval → Payment → Confirmed.

## Definition of Done

Online payment is safely integrated without making Stripe responsible for booking lifecycle rules.

---

# M11 — Invoice & Manual Payment Workflow

## Objective

Support authorised invoice customers and legitimate offline settlement.

## Business Value

Supports realistic business customers that operate under agreed payment terms.

## Requirements Covered

Relevant INV, PAY and DEP requirements where applicable.

## Dependencies

M08 and M10 payment architecture.

## Implement

Application-owned:

- Invoice;
- InvoiceLine.

Support:

- customer/organisation;
- issue date;
- due date;
- status;
- line descriptions;
- amount;
- booking relationship;
- totals;
- financial state.

One invoice must be capable of containing multiple booking charges.

Invoice terms must be explicitly authorised.

Support audited management recording of legitimate offline/manual payments where appropriate.

## Do Not Implement Yet

- accounting-system integration;
- direct debit;
- sophisticated account-credit ledger;
- advanced monthly statements.

## Architecture Rules

Booking status and invoice/payment status remain separate.

A confirmed invoice booking may legitimately remain financially outstanding.

## Tests Required

Test:

- authorised invoice flow;
- ordinary customer cannot bypass card payment;
- confirmed but unpaid invoice booking;
- invoice lines;
- totals;
- multiple booking structure;
- due dates;
- manual payment authorization;
- financial updates;
- audit;
- ownership isolation.

## Acceptance Criteria

Both card and invoice customers can follow appropriate commercial workflows.

## Definition of Done

The platform supports online card payment and authorised invoice/manual-payment business models.

---

# M12 — Notifications & Booking Confirmation

## Objective

Provide reliable transactional communication for the commercial booking lifecycle.

## Business Value

Reduces uncertainty and manual customer communication.

## Requirements Covered

Relevant NOTIFY requirements.

## Dependencies

M10 and M11.

## Implement

Laravel Notifications using:

- database/in-app notifications;
- email;
- Redis-backed queues.

Initial events include:

- booking request received;
- approved/payment required;
- confirmed;
- rejected;
- payment received;
- invoice issued;
- cancellation where implemented.

Create customer notification experience.

Use Mailtrap during development for email inspection.

Confirmation must only occur after genuine confirmation.

For standard card bookings:

Payment reconciled  
→ Booking Confirmed  
→ Confirmation notification

For authorised invoice bookings:

Approval under invoice terms  
→ Confirmed  
→ Confirmation notification

## Do Not Implement Yet

- SMS;
- marketing email;
- sophisticated communication preferences;
- AI-generated transactional messages.

## Architecture Rules

Business transaction commits before secondary notification delivery.

Email failure must not roll back a valid booking.

Consequential notifications require duplicate protection.

## Tests Required

Test:

- request notification;
- payment-required notification;
- confirmation;
- invoice confirmation;
- rejection;
- payment receipt;
- recipient;
- notification isolation;
- queued delivery;
- provider failure isolation;
- duplicate-event protection.

## Acceptance Criteria

Customers receive appropriate in-app/email communication throughout the core booking journey.

## Definition of Done

The first complete commercial vertical slice operates from discovery through confirmation and communication.

---

# Phase 4 — Venue Operations

# M13 — Today’s Schedule

## Objective

Provide leisure assistants with a live centre-specific operational schedule.

## Business Value

Replaces static timetable sheets and fragmented last-minute operational communication.

## Requirements Covered

Relevant OPS requirements.

## Dependencies

Confirmed bookings from Phase 3.

## Implement

Create `TodayScheduleService` or equivalent.

Inputs include:

- authenticated staff member;
- authorised centre;
- date.

Create purpose-built Operations Filament page.

Prioritise:

- Now;
- Next;
- full day.

Expose appropriate:

- booking reference;
- customer/organisation where required;
- activity;
- time;
- setup/cleanup;
- resources;
- equipment;
- operational notes;
- attendance state;
- important changes.

Use simple refresh/polling initially.

## Do Not Implement Yet

- WebSocket infrastructure unless justified;
- operational analytics;
- AI operations assistant.

## Architecture Rules

There is no separate timetable database.

The schedule is generated from authoritative booking data.

Use least-privilege information exposure.

## Tests Required

Test:

- centre filtering;
- staff authorization;
- date boundaries;
- relevant bookings;
- cancelled bookings;
- resources;
- equipment;
- setup periods;
- least-privilege data.

## Acceptance Criteria

An authorised leisure assistant can see what is happening now, what happens next and what preparation is required.

## Definition of Done

Operational staff have a reliable live schedule generated from booking data.

---

# M14 — Attendance & Session Operations

## Objective

Record what actually happens during booked sessions.

## Business Value

Improves operational records and provides useful data for no-shows, utilisation and follow-up.

## Requirements Covered

ATTEND and OVERRUN requirements where applicable.

## Dependencies

M13.

## Implement

Explicit Actions such as:

- `RecordArrival`;
- `RecordNoShow`;
- `CompleteBooking`.

Validate:

- authorization;
- centre access;
- booking state;
- timing/business rules.

Expose actions from the Operations interface.

Make interactions suitable for phone/tablet use.

## Do Not Implement Yet

- automatic financial consequences from no-shows;
- sophisticated overrun billing.

## Architecture Rules

Attendance is operational state.

It does not automatically determine financial treatment.

## Tests Required

Test:

- arrival;
- no-show;
- completion;
- invalid transitions;
- centre access;
- customer restrictions;
- history;
- audit;
- repeated actions.

## Acceptance Criteria

Staff can accurately record whether customers arrived, did not attend and completed sessions.

## Definition of Done

The platform records operational outcomes as well as scheduled bookings.

---

# M15 — Closures & Operational Exceptions

## Objective

Allow management to block availability and resolve bookings affected by operational disruption.

## Business Value

Provides a structured response to weather, maintenance, school use and other venue closures.

## Requirements Covered

CLOSE requirements and related AVAIL/NOTIFY requirements.

## Dependencies

M05 Availability Blocks foundation and Phase 3 booking workflows.

## Implement

Expand `AvailabilityBlock`.

Support appropriate block types:

- school use;
- exam;
- maintenance;
- health and safety;
- weather;
- internal/private use;
- manager blockout;
- other.

Scope may include:

- centre;
- facility;
- resource.

Planned blocks immediately affect future availability.

For existing bookings:

- identify affected bookings;
- track unresolved/resolved impact;
- support explicit management resolution.

Potential outcomes include:

- reschedule;
- alternative resource;
- alternative centre;
- cancel;
- refund;
- credit;
- invoice adjustment.

Only implement financial actions supported by existing capabilities.

Support reopening/ending temporary blocks.

## Do Not Implement Yet

- fully automated customer rescheduling;
- advanced recommendation engine;
- waiting-list automation.

## Architecture Rules

Closures are Availability Blocks, not fake bookings.

Do not silently mutate confirmed bookings.

## Tests Required

Test:

- centre block;
- facility block;
- resource block;
- availability impact;
- affected-booking detection;
- unaffected bookings;
- authorization;
- resolution tracking;
- reopening;
- audit.

## Acceptance Criteria

Management can create a closure, prevent new conflicting bookings and identify existing bookings requiring action.

## Definition of Done

Operational disruption is managed through a traceable workflow rather than manual memory and messaging.

---

# M16 — Incidents & Damage

## Objective

Provide structured operational recording of incidents and damage.

## Business Value

Improves operational accountability and follow-up.

## Requirements Covered

Relevant INCIDENT and DAMAGE requirements.

## Dependencies

M13 and M14.

## Implement

Incident reporting from booking/session context.

Prefill:

- centre;
- booking;
- date/time;
- resource;
- reporting staff member.

Damage reporting should support:

- resource/equipment;
- description;
- observed/reported time;
- booking relationship;
- reporter;
- management follow-up.

Provide management review of open issues.

## Do Not Implement Yet

- complex case management;
- automatic customer charging;
- advanced attachment workflows unless explicitly required.

## Architecture Rules

Incident/damage reporting does not automatically determine customer responsibility.

Financial consequences require explicit authorised decisions.

## Tests Required

Test:

- authorised creation;
- booking context;
- centre scope;
- internal privacy;
- management review;
- audit;
- data isolation.

## Acceptance Criteria

Staff can record incidents/damage and management can review the resulting operational record.

## Definition of Done

Operational issues are structured and traceable.

---

# Phase 5 — Complete Product Experience

# M17 — Customer Booking Management

## Objective

Provide customer self-service for understanding and managing existing bookings.

## Business Value

Reduces routine customer-service administration.

## Requirements Covered

Relevant CUSTOMER, CANCEL and RECUR requirements.

## Dependencies

Phases 2–4.

## Implement

Customer booking views for:

- requested;
- awaiting payment;
- confirmed;
- recurring;
- completed;
- cancelled;
- rejected;
- expired.

Present customer-friendly lifecycle history.

Implement cancellation/amendment only where current policy permits.

Use explicit Actions such as:

- `CancelBooking`;
- `AmendBooking`.

Availability must be revalidated for amendments.

Recurring scope must be explicit.

## Do Not Implement Yet

- invented universal cancellation rules;
- sophisticated refund policy automation beyond approved requirements.

## Architecture Rules

Cancellation policy should be centralised/configurable rather than scattered through controllers.

## Tests Required

Test:

- ownership;
- cross-customer access;
- lifecycle display;
- allowed cancellation;
- forbidden cancellation;
- amendment revalidation;
- recurring occurrence behaviour;
- financial follow-up;
- audit/history.

## Acceptance Criteria

Customers can understand and manage routine aspects of their bookings without contacting management.

## Definition of Done

Customer self-service continues beyond initial booking creation.

---

# M18 — Manual & Staff-Assisted Booking

## Objective

Allow authorised staff to create bookings on behalf of customers.

## Business Value

Supports phone, walk-in and exceptional business workflows.

## Requirements Covered

MANUAL requirements.

## Dependencies

M17 and core booking engine.

## Implement

Management-assisted flow:

- find/create customer;
- choose centre;
- check availability;
- select resource/equipment;
- calculate pricing;
- create booking.

Support appropriate walk-in/staff-assisted workflows.

Capture:

- booking owner;
- staff actor.

Allow authorised overrides only where justified, reasoned and audited.

## Do Not Implement Yet

- unrestricted conflict bypass;
- separate admin booking engine.

## Architecture Rules

Reuse:

- AvailabilityService;
- PricingService;
- booking Actions;
- payment/invoice services;
- Policies.

Physical impossibilities remain protected.

## Tests Required

Test:

- staff authorization;
- customer assignment;
- centre scope;
- shared availability;
- shared pricing;
- actor audit;
- manual payment compatibility;
- conflict rejection.

## Acceptance Criteria

Staff can create valid bookings without bypassing the normal domain rules.

## Definition of Done

Customer self-service and staff-assisted booking use the same authoritative engine.

---

# M19 — Organisation Accounts

## Objective

Support clubs, businesses and other organisational customers.

## Business Value

Enables realistic repeat-customer workflows and shared account responsibility.

## Requirements Covered

Relevant USER and organisation requirements.

## Dependencies

M17 and invoice infrastructure.

## Implement

- Organisation.
- Membership.
- invitations where appropriate.
- organisation booking ownership.
- organisation invoice access.
- appropriate member roles.

Potential roles:

- Owner;
- Admin;
- Booking Manager;
- Finance;
- Member.

Preserve:

- organisation responsible for booking;
- user who performed action.

## Do Not Implement Yet

- enterprise RBAC;
- highly configurable permission builders.

## Architecture Rules

Organisation roles remain separate from Spatie system roles.

## Tests Required

Test:

- organisation creation;
- membership;
- invitations where implemented;
- member permissions;
- booking ownership;
- invoice access;
- cross-organisation isolation;
- removed member access;
- actor audit.

## Acceptance Criteria

Organisations can safely collaborate on bookings and finances without sharing one user's credentials.

## Definition of Done

The platform supports both individual and organisational customers.

---

# M20 — Management Dashboard

## Objective

Provide management with an action-oriented operational starting point.

## Business Value

Reduces time spent searching across the system for work requiring attention.

## Requirements Covered

Relevant REPORT/management UX requirements.

## Dependencies

Phases 2–5 data.

## Implement

Useful dashboard information such as:

- pending booking requests;
- awaiting payment;
- overdue invoices;
- today's bookings;
- operational issues;
- closure-affected bookings;
- open incidents.

Provide direct links to the relevant workflow.

Use focused dashboard/query services.

## Do Not Implement Yet

- decorative analytics;
- advanced BI;
- AI-generated dashboard.

## Architecture Rules

Do not embed complex business queries directly inside Filament widgets.

## Tests Required

Test:

- counts;
- query results;
- centre scope;
- authorization;
- navigation;
- empty states.

## Acceptance Criteria

A manager can sign in and immediately understand what requires attention.

## Definition of Done

The management dashboard improves decision and response speed rather than merely displaying statistics.

---

# M21 — Reporting & Excel Exports

## Objective

Provide useful management reporting and export capability.

## Business Value

Enables the business to understand revenue, utilisation, booking behaviour and financial exposure.

## Requirements Covered

REPORT requirements.

## Dependencies

M20.

## Implement

Initial reports:

### Revenue

- by centre;
- facility/resource;
- period;
- customer/organisation.

### Utilisation

- centre;
- facility;
- day/time;
- booked vs available capacity.

### Booking

- confirmed;
- cancelled;
- rejected;
- no-show.

### Finance

- paid;
- outstanding;
- overdue.

Define metrics centrally.

Use focused report services/query objects.

Use Filament's native export system (OpenSpout-backed) for management
XLSX/CSV exports. Introduce Laravel Excel only if advanced spreadsheet
features are later required.

Queue larger exports.

Store generated files privately.

## Do Not Implement Yet

- data warehouse;
- Elasticsearch;
- external BI platform;
- AI-generated calculations.

## Architecture Rules

Reports, exports and future AI tools should reuse the same metric definitions.

## Tests Required

Test:

- metric definitions;
- date filters;
- centre filters;
- financial interpretation;
- authorization;
- exports;
- private download access.

## Acceptance Criteria

Management can answer core commercial questions and export supporting data.

## Definition of Done

The application turns operational data into useful management information.

---

# M22 — Documents, PDFs & Statements

## Objective

Generate professional business documents from authoritative application records.

## Business Value

Removes manual document creation and gives customers reliable downloadable records.

## Requirements Covered

Relevant invoice/document requirements.

## Dependencies

M11, M17, M19 and M21.

## Implement

Initial documents:

- Invoice PDF;
- Booking Confirmation;
- Receipt.

Where justified:

- customer/organisation statement;
- credit/refund document.

Select an actively maintained Laravel-compatible PDF solution at implementation time.

Use private filesystem storage.

Production later maps to S3.

Support authorised downloads.

## Do Not Implement Yet

- unnecessary document types;
- public document storage.

## Architecture Rules

The database record is authoritative.

PDFs are representations of application data.

## Tests Required

Test:

- document data;
- totals;
- booking details;
- ownership;
- cross-customer access;
- private storage;
- regeneration where appropriate;
- authorization.

## Acceptance Criteria

Customers can securely retrieve professional booking and financial documents.

## Definition of Done

Manual Word/PDF document generation is no longer required for core records.

---

# Phase 6 — Production Hardening

# M23 — Security & Authorization Hardening

## Objective

Perform an adversarial review of security, authorization and data isolation.

## Business Value

Reduces risk of customer-data exposure, financial manipulation and unauthorized staff actions.

## Requirements Covered

SEC, DATA, AUDIT and relevant authorization requirements.

## Dependencies

Core product functionality.

## Implement

Systematically review:

- bookings;
- payments;
- invoices;
- documents;
- organisations;
- reports;
- centres;
- facilities;
- equipment;
- closures;
- incidents;
- operations.

Attack IDOR/data-isolation risks.

Review:

- panel access;
- centre scope;
- organisation scope;
- server-side pricing;
- status manipulation;
- CSRF;
- sessions/cookies;
- throttling;
- reset flows;
- verification;
- mass assignment;
- file access;
- uploads where applicable;
- secrets;
- logging;
- dependency security;
- production error exposure;
- Stripe security.

Add regression tests for findings.

## Do Not Implement Yet

Unrelated feature development.

## Architecture Rules

UI visibility is never authorization.

## Tests Required

Adversarial authorization/security tests across important domains.

## Acceptance Criteria

Attempts to bypass important security boundaries fail safely.

## Definition of Done

Important security assumptions have been deliberately challenged and protected by regression tests.

---

# M24 — Concurrency & Data Integrity Hardening

## Objective

Prove the system remains correct under simultaneous operations.

## Business Value

Prevents impossible bookings, over-allocation and duplicate financial effects.

## Requirements Covered

AVAIL, DATA, REL and financial integrity requirements.

## Dependencies

Complete booking/payment engine.

## Implement

Concurrency scenarios including:

- same resource race;
- whole/part resource collision;
- sibling resources;
- equipment exhaustion;
- expiring hold race;
- recurring vs one-off booking;
- duplicate Stripe events;
- financial retries.

Review:

- transactions;
- locks;
- unique constraints;
- foreign keys;
- indexes;
- database-enforceable invariants.

## Do Not Implement Yet

Unrelated optimisation.

## Architecture Rules

Critical concurrency verification uses real MySQL behaviour.

## Tests Required

Automated concurrency/integrity tests against MySQL.

## Acceptance Criteria

Concurrent operations cannot produce invalid physical or financial state.

## Definition of Done

The application's most important integrity guarantees have automated concurrency evidence.

---

# M25 — Accessibility & Responsive UX Hardening

## Objective

Validate core workflows against responsive requirements and the WCAG 2.2 AA target.

## Business Value

Makes the product usable by a wider range of customers and staff while reducing accessibility risk.

## Requirements Covered

ACCESS and RESP requirements.

## Dependencies

Complete primary interfaces.

## Implement

Review customer journeys across:

- mobile;
- tablet;
- desktop.

Review Operations heavily for mobile/tablet use.

Review Management for sensible responsive degradation.

Validate:

- keyboard navigation;
- focus;
- headings;
- labels;
- validation;
- contrast;
- non-colour status communication;
- dialogs;
- tables;
- dynamic updates;
- touch targets;
- reduced motion where applicable.

Review error/empty/loading states.

## Do Not Implement Yet

Unrelated visual redesign.

## Tests Required

Automated accessibility checks where useful plus deliberate manual workflow testing.

## Acceptance Criteria

Primary workflows are usable across intended devices and meet the accessibility target to an appropriate production standard.

## Definition of Done

Accessibility and responsive behaviour have been deliberately tested rather than assumed.

---

# M26 — Performance & Observability

## Objective

Measure important workflows, resolve meaningful bottlenecks and improve diagnosability.

## Business Value

Improves reliability and user experience while reducing operational troubleshooting time.

## Requirements Covered

PERF, OBS and REL requirements.

## Dependencies

Working product.

## Implement

Measure:

- availability;
- Today’s Schedule;
- management booking lists;
- customer history;
- dashboard;
- reports;
- invoices.

Review:

- N+1 queries;
- query counts;
- indexes;
- eager loading;
- pagination;
- payload size;
- queue performance.

Use Redis caching only where measurements justify it.

Add appropriate visibility for:

- exceptions;
- failed jobs;
- Stripe failures;
- email failures;
- scheduler failures;
- exports;
- application/database health.

## Do Not Implement Yet

Caching merely for architectural appearance.

## Architecture Rules

MySQL remains authoritative.

Cached availability must always be revalidated transactionally before booking.

## Tests Required

Performance regression checks where practical and health/observability verification.

## Acceptance Criteria

Important workflows perform appropriately and significant failures are diagnosable.

## Definition of Done

The application has measured performance and useful operational visibility.

---

# M27 — End-to-End Regression & Production Readiness

## Objective

Verify that major business processes work across the entire application.

## Business Value

Reduces the risk that individually working components fail when used as complete workflows.

## Requirements Covered

Cross-cutting MVP acceptance and reliability requirements.

## Dependencies

M23–M26.

## Implement / Verify

### Card Customer

Register  
→ availability  
→ request  
→ approval  
→ payment  
→ webhook  
→ confirmation  
→ notification  
→ Today’s Schedule  
→ arrival  
→ completion.

### Invoice Organisation

Organisation  
→ request  
→ approval  
→ invoice confirmation  
→ invoice  
→ operational schedule  
→ settlement.

### Recurring Booking

Series  
→ occurrences  
→ conflict  
→ resolution  
→ approval  
→ operations.

### Closure

Confirmed bookings  
→ closure  
→ affected bookings  
→ management resolution  
→ communication.

### Failure Scenarios

- queue failure;
- email failure;
- duplicate webhook;
- payment failure;
- expired hold;
- booking race;
- equipment shortage.

Update development documentation to reflect the real application.

## Do Not Implement Yet

AI functionality.

## Architecture Rules

Failures in secondary systems must not corrupt authoritative booking state.

## Tests Required

Selective automated E2E tests plus integration/regression suites.

## Acceptance Criteria

The major business journeys operate successfully from beginning to end.

## Definition of Done

The deterministic product is production-ready enough to begin the planned AI capability.

---

# Phase 7 — AI Capability

# M28 — Laravel AI SDK Foundation

## Objective

Establish a provider-independent and testable AI integration boundary.

## Business Value

Creates a foundation for useful AI assistance without making the business dependent on AI availability.

## Requirements Covered

AI-001, AI-002, AI-008, AI-013, AI-016, AI-017.

## Dependencies

M27.

## Implement

At implementation time verify the current supported Laravel AI SDK package and provider capabilities.

Introduce appropriate:

- Agents;
- Tools;
- AI support/integration code.

Configure:

- Ollama for local development;
- environment-driven provider/model configuration;
- production-provider configuration through the Laravel AI SDK;
- bounded timeouts and provider-failure handling;
- structured-output validation support.

Create testing boundaries so normal test runs do not require live model calls.

## Do Not Implement Yet

- management chatbot;
- autonomous actions;
- vector database;
- unnecessary RAG;
- agent swarm.

## Architecture Rules

AI failure must not affect:

- booking;
- availability;
- pricing;
- payments;
- invoices;
- operations;
- reporting.

## Tests Required

Test provider abstraction/configuration, timeouts, failure handling, structured
output validation and fake AI interactions.

Perform limited real Ollama integration verification separately.

## Acceptance Criteria

The application can communicate through its AI abstraction while remaining fully operational without it.

## Definition of Done

A clean AI integration boundary exists.

---

# M29 — Authorised AI Tooling

## Objective

Expose selected authoritative application capabilities to AI through permission-aware read-only tools.

## Business Value

Allows AI to answer useful business questions without inventing or recalculating authoritative data.

## Requirements Covered

AI-003, AI-005–AI-008, AI-010–AI-012, AI-015–AI-017 and relevant SEC requirements.

## Dependencies

M28 and mature reporting/domain services.

## Implement

Initial tools may include:

- booking search;
- booking details;
- today's operations;
- revenue reporting;
- utilisation reporting;
- cancellation and no-show reporting;
- facility and availability discovery;
- outstanding invoices;
- closure-affected bookings;
- incident summaries.

Each tool must use existing authoritative services/query capabilities.

Tools inherit current authenticated-user authorization.

Minimise data passed to models.

Define validated tool inputs and structured outputs suitable for independent
testing and consistent UI presentation.

Record safe, proportionate tool-use audit/diagnostic context where appropriate.

## Do Not Implement Yet

AI actions that:

- approve;
- reject;
- cancel;
- refund;
- change price;
- mark paid;
- create closures;
- modify permissions.

## Architecture Rules

AI does not receive unrestricted database access.

AI does not calculate authoritative business metrics itself.

## Tests Required

Test:

- authorised tool access;
- denied access;
- centre scope;
- organisation scope where applicable;
- service reuse;
- data minimisation;
- structured tool results;
- validation and error mapping;
- audit/diagnostic context where required.

## Acceptance Criteria

AI can retrieve trustworthy authorised application information through controlled tools.

## Definition of Done

The application has a safe tool layer between AI and business data.

---

# M30 — Management & Operations AI Assistant

## Objective

Provide authorised managers, and leisure assistants where appropriate, with
natural-language querying and summarisation of relevant operational/business
information.

## Business Value

Reduces the time required to inspect reports and operational data and demonstrates commercially relevant AI augmentation.

## Requirements Covered

AI-001, AI-003, AI-004, AI-008–AI-018.

## Dependencies

M29.

## Implement

Purpose-built assistant experiences integrated into relevant management and
operations workflows rather than a generic site-wide chatbot.

Initial capabilities:

- summarise items needing attention;
- summarise incidents;
- summarise outstanding invoices;
- summarise upcoming operations;
- summarise cancellation and no-show patterns;
- answer revenue questions;
- answer utilisation questions;
- assist facility and availability discovery through the authoritative service;
- identify closure-affected bookings;
- search bookings.

AI flow:

``` text
User question
-> AI Interface
-> Laravel AI SDK
-> Agent
-> authorised Tool
-> authoritative Service
-> structured result
-> AI explanation
```

Provide links back to underlying application records/reports where useful.

Handle insufficient data explicitly.

Distinguish generated explanation from authoritative tool-derived results and
provide recovery links to normal application workflows.

Provide clear loading, tool-progress, timeout, error and provider-unavailable
states. Core management and operations workflows remain available throughout.

Implement appropriate AI operational logging without unnecessarily retaining sensitive information.

## Do Not Implement Yet

Consequential actions. If later approved, these require an explicit preview and
confirmation before the normal authorised application Action is invoked.

## Architecture Rules

AI may:

- search;
- retrieve;
- summarise;
- explain;
- compare authorised information.

AI may not independently mutate consequential business state.

## Tests Required

Test:

- manager access;
- authorised leisure-assistant access where implemented;
- restricted role access;
- tool authorization;
- centre scope;
- report-service reuse;
- insufficient-data handling;
- provider failure;
- timeout and invalid structured-output handling;
- graceful fallback to normal application workflows;
- prompt-based authorization bypass attempts;
- sensitive-data minimisation.

## Acceptance Criteria

Management can ask useful natural-language questions while Laravel services remain authoritative.

## Definition of Done

The product demonstrates safe, grounded, permission-aware AI augmentation.

---

# Phase 8 — Demo, CI/CD, AWS & Portfolio

# M31 — Realistic Demo Data & Portfolio Experience

## Objective

Create a believable fictional operating environment for demonstrations.

## Business Value

Allows prospective clients and employers to understand the product without manually creating data.

## Requirements Covered

DEMO requirements.

## Dependencies

Complete application.

## Implement

Create an original fictional facility operator.

Do not use historical Active8/Interserve/school branding or personal data.

Seed approximately four fictional centres with realistic variation.

Seed:

- facilities;
- resources;
- equipment;
- customers;
- organisations;
- staff;
- recurring bookings;
- pending requests;
- awaiting-payment bookings;
- confirmed bookings;
- completed bookings;
- no-shows;
- invoices;
- payments;
- closures;
- incidents.

Use relative dates where useful so Today's Schedule remains populated.

Provide clearly fictional demo personas.

Create appropriate demo reset/protection strategy where needed.

## Do Not Implement Yet

Production deployment-specific infrastructure.

## Architecture Rules

Demo data must respect the real domain model and invariants.

## Tests Required

Factories and seeders must generate internally valid relationships and states.

## Acceptance Criteria

A new visitor can immediately explore a believable multi-site facility business.

## Definition of Done

The application presents well without relying on manually prepared demo data.

---

# M32 — CI/CD

## Objective

Mature the project's automated verification and deployment pipeline.

## Business Value

Reduces deployment risk and demonstrates professional engineering delivery.

## Requirements Covered

Deployment/reliability architecture requirements.

## Dependencies

Earlier basic CI foundations and complete application.

## Implement

GitHub Actions pipeline covering the final project toolchain.

Likely categories:

- PHP dependency installation;
- JS dependency installation;
- PHP formatting/style;
- static analysis where selected;
- frontend linting;
- TypeScript;
- frontend production build;
- Laravel automated tests;
- MySQL integration/concurrency tests.

Build the deployment pipeline required for staging/production.

Keep CI and deployment logically separable.

## Do Not Implement Yet

AWS production deployment itself.

## Architecture Rules

Critical MySQL behaviour must not be validated exclusively using SQLite.

Secrets must remain outside Git.

## Tests Required

The CI pipeline itself must successfully execute representative application verification.

## Acceptance Criteria

A push/PR automatically proves whether the application builds and passes its required checks.

## Definition of Done

Automated quality verification is mature and ready to gate deployment.

---

# M33 — AWS Staging

## Objective

Deploy the complete application into an isolated production-style AWS staging environment.

## Business Value

Provides realistic deployment validation before public production exposure.

## Requirements Covered

Deployment, reliability, storage and observability architecture requirements.

## Dependencies

M32.

## Implement

First make the documented AWS compute decision based on the completed application.

Evaluate suitable options such as:

- EC2;
- ECS/Fargate;
- other appropriate AWS compute.

Evaluate:

- cost;
- complexity;
- queue workers;
- scheduler;
- scaling;
- maintenance;
- learning value.

Expected supporting services include:

- RDS MySQL;
- ElastiCache Redis;
- S3;
- CloudWatch;
- Secrets Manager or Parameter Store;
- HTTPS/certificates;
- transactional email provider.

Configure isolated staging:

- database;
- secrets;
- Stripe test environment;
- storage;
- AI configuration;
- environment variables.

Verify:

- migrations;
- workers;
- queues;
- scheduler;
- email;
- exports;
- PDFs;
- storage;
- logging;
- backups.

## Do Not Implement Yet

Live production payment activation merely for demonstration.

## Architecture Rules

Staging must not accidentally interact with live payment/customer infrastructure.

## Tests Required

Deployment smoke tests and staging E2E verification.

## Acceptance Criteria

The application operates successfully using production-style AWS infrastructure.

## Definition of Done

A realistic staging environment proves the deployment architecture.

---

# M34 — AWS Production Deployment

## Objective

Deploy the hardened application into a secure public production/demo environment.

## Business Value

Provides a live demonstration environment and validates complete software delivery capability.

## Requirements Covered

Production deployment and security requirements.

## Dependencies

M33.

## Implement

Verify:

- production environment;
- debug disabled;
- HTTPS;
- secure cookies;
- RDS;
- Redis;
- S3;
- queue workers;
- scheduler;
- backups;
- logging;
- monitoring;
- health checks;
- secrets;
- rate limiting;
- safe error handling.

Document backup and restoration strategy.

Decide deliberately whether the public portfolio environment uses:

- Stripe test/demo payments;
- or live payments only if there is a genuine commercial reason.

Choose/limit production AI provider appropriately.

Protect the public demo against:

- spam;
- AI abuse;
- destructive demo behaviour;
- uncontrolled email generation;
- excessive requests.

Run deployment smoke tests.

## Do Not Implement Yet

Features added solely because the application is now deployed.

## Architecture Rules

Production configuration must not rely on local development assumptions.

## Tests Required

Production smoke test covering the core customer-to-operations journey.

## Acceptance Criteria

The application is securely and reliably available online.

## Definition of Done

The project demonstrates development through production deployment.

---

# M35 — Final Portfolio Case Study

## Objective

Present the project as evidence of consulting, product, architecture and engineering capability.

## Business Value

Turns the completed technical work into an asset for client acquisition and employment.

## Requirements Covered

Portfolio positioning established during discovery/design.

## Dependencies

M34.

## Implement

Create case study covering:

1. Business Context
2. Existing Process
3. Problems Identified
4. Discovery & Requirements
5. Proposed Solution
6. Customer Experience
7. Management Workflow
8. Venue Operations
9. Architecture
10. Key Engineering Challenges
11. Payments & Financial Workflow
12. Security & Concurrency
13. AI Integration
14. Testing & Quality
15. AWS Deployment
16. Business Impact / Expected Outcomes
17. Lessons & Future Development

Show the process transformation.

### Before

Customer  
→ phone/email  
→ manual availability  
→ paper/Word form  
→ manager  
→ spreadsheet/timetable  
→ accept/reject  
→ static staff schedule  
→ cash/cheque  
→ manual reconciliation.

### After

Customer Portal  
→ calculated availability  
→ booking request  
→ conflict protection  
→ manager approval  
→ payment/invoice  
→ confirmation  
→ live operational schedule  
→ attendance/incidents  
→ reporting.

Highlight engineering challenges including:

- parent/child resource conflicts;
- allocation units;
- concurrency;
- equipment allocation;
- recurring bookings;
- booking vs financial state;
- Stripe idempotency;
- organisation isolation;
- closure workflows;
- operations;
- permission-aware AI.

Use expected/measurable business outcomes rather than fabricated results.

Potential metrics include:

- administration time per booking;
- self-service booking percentage;
- request-to-confirmation time;
- online payment adoption;
- outstanding invoices;
- facility utilisation;
- booking conflicts;
- cancellation/no-show rate.

Provide:

- Live Demo;
- Case Study;
- GitHub.

## Do Not Implement Yet

Fabricated customer testimonials, invented commercial results or misleading historical branding.

## Architecture Rules

Describe what was actually implemented.

Do not exaggerate AI, AWS or production usage.

## Tests Required

Not primarily an automated software milestone, but verify:

- public links;
- demo accounts;
- screenshots;
- responsive portfolio presentation;
- no sensitive/private information;
- GitHub documentation.

## Acceptance Criteria

A prospective client or employer can understand:

business problem  
→ discovery  
→ reasoning  
→ solution  
→ architecture  
→ implementation  
→ business value.

## Definition of Done

The project is a credible portfolio case study rather than merely a public source-code repository.

---

# 7. Phase Checkpoints

## Phase 1

Foundation complete:

- Laravel;
- Sail;
- MySQL;
- Redis;
- Inertia;
- Vue;
- TypeScript;
- shadcn/ui;
- Filament Management;
- Filament Operations;
- authentication;
- Spatie Permission;
- Policies;
- Activitylog;
- venue domain.

No booking engine yet.

---

## Phase 2

Booking engine complete:

- calculated availability;
- physical allocation;
- equipment allocation;
- pricing;
- protected requests;
- lifecycle approval;
- recurring bookings;
- initial concurrency protection.

No commercial payment workflow yet.

---

## Phase 3

Commercial workflow complete:

- Stripe/Cashier;
- application Payment records;
- verified/idempotent webhooks;
- invoice workflow;
- manual payments;
- notifications;
- booking confirmation.

The first revenue-producing vertical slice is complete.

---

## Phase 4

Operations complete:

- Today's Schedule;
- arrival;
- no-show;
- completion;
- closures;
- affected bookings;
- incidents;
- damage.

---

## Phase 5

Complete product experience:

- customer booking management;
- manual bookings;
- organisations;
- management dashboard;
- reporting;
- Excel;
- PDFs;
- statements/documents.

---

## Phase 6

Production hardening:

- security review;
- authorization review;
- concurrency review;
- accessibility;
- responsive UX;
- performance;
- observability;
- E2E regression.

---

## Phase 7

AI capability:

- Laravel AI SDK;
- Ollama development;
- permission-aware tools;
- Management and Operations AI Assistant.

AI remains independent of core operation and provider failure does not block
deterministic workflows.

---

## Phase 8

Delivery:

- realistic demo;
- mature CI/CD;
- AWS staging;
- AWS production;
- portfolio case study.

---

# 8. Requirement Traceability

Implementation should preserve the following chain:

Requirement  
→ Design Flow  
→ Architecture Behaviour  
→ Milestone  
→ Implementation Task  
→ Automated Test

When beginning a milestone, identify the exact relevant requirement IDs from `docs/requirements.md`.

Do not infer that every `MUST` requirement belongs in the earliest milestone.

As established in `requirements.md`, requirement strength and delivery phase are separate concepts.

A requirement marked `MUST` means that when the capability is delivered, that behaviour is mandatory.

It does not automatically mean the capability must exist in M01 or the earliest MVP increment.

---

# 9. Task Decomposition

Milestones should not normally be implemented as one enormous AI-agent task.

Each milestone should be broken into small logical tasks.

For example, M05 may become:

1. introduce required allocation persistence;
2. implement bookable-hours evaluation;
3. implement physical allocation conflict detection;
4. implement blockout evaluation;
5. implement setup/cleanup occupancy;
6. implement equipment availability;
7. create structured availability results;
8. expose availability endpoint;
9. create customer availability UI;
10. add integration tests;
11. add MySQL conflict tests.

Each task should have a focused scope and produce a reviewable change.

---

# 10. Git and Commit Strategy

Commit at meaningful logical increments.

Prefer commits such as:

- `feat: add facility allocation units`
- `feat: calculate resource availability`
- `test: cover whole-hall allocation conflicts`
- `feat: add booking request workflow`

Avoid giant commits containing an entire phase.

Avoid commits containing unrelated refactoring.

Before committing:

- run relevant tests;
- run formatting;
- run type checks where applicable;
- verify no secrets/private historical material were added.

---

# 11. Definition of Feature Complete

A feature is not complete merely because the happy path works.

Where relevant, feature completion includes:

- approved requirement implemented;
- server-side authorization;
- business rules enforced;
- database integrity;
- useful validation/errors;
- loading/empty/error states;
- responsive behaviour;
- accessibility;
- audit behaviour;
- appropriate automated tests;
- no cross-user data leakage;
- no future-milestone scope accidentally introduced.

---

# 12. Change Control

New ideas do not automatically become implementation tasks.

Before adding a material feature, ask:

1. What business problem does it solve?
2. Is it supported by discovery?
3. Is it already represented in requirements?
4. Is it MVP, later phase or genuinely new scope?
5. Does it conflict with design?
6. Does it conflict with architecture?
7. What complexity does it introduce?
8. What existing milestone does it affect?

If a material new requirement is accepted, update the appropriate documentation before significant implementation.

Do not allow implementation to silently redefine the product.

---

# 13. Deferred Decisions

The following decisions remain intentionally deferred until the milestone where sufficient information exists:

- final fictional product name;
- final branding/logo;
- exact design tokens;
- exact fictional pricing;
- exact hold duration;
- exact payment deadline;
- final cancellation policy;
- final organisation permission detail;
- advanced event workflow;
- exact opening-hours demo seed;
- detailed equipment seed;
- exact physical hold persistence representation;
- final database schema details;
- deletion/retention strategy;
- final static-analysis/lint tools;
- PDF package;
- AWS compute option;
- production email provider;
- production AI provider/model;
- selective cache candidates;
- backup configuration;
- staff centre-access effective-date requirements;
- whether Reverb/WebSockets are justified.

Deferred does not mean forgotten.

Each decision should be made when its implementation milestone provides enough context to make an informed choice.

---

# 14. Final Delivery Outcome

The finished platform should demonstrate the transformation from a fragmented manual process into a centralised multi-site facility booking and operations system.

The final system should support the core flow:

Customer Discovery  
→ Availability  
→ Booking Request  
→ Conflict Protection  
→ Management Approval  
→ Payment or Invoice  
→ Confirmation  
→ Live Venue Operations  
→ Attendance / Incidents  
→ Reporting

while maintaining:

- authorization;
- auditability;
- data isolation;
- transactional integrity;
- concurrency protection;
- accessibility;
- responsive design;
- operational resilience.

AI should sit above this deterministic foundation:

Management Question  
→ Laravel AI SDK  
→ Permission-Aware Tool  
→ Authoritative Application Service  
→ Structured Data  
→ AI Explanation

The system should remain fully usable if AI is unavailable.

The completed project should demonstrate capability across:

- software development;
- software architecture;
- product design;
- business analysis;
- payment integration;
- operational workflow design;
- security;
- concurrency;
- testing;
- accessibility;
- AI integration;
- cloud deployment;
- consulting;
- business process modernisation.

The implementation should remain guided by the core product principle:

> Build a reliable system that simplifies a complex real-world business process, rather than adding technology merely to demonstrate technology.
