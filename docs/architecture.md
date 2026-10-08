# Facility Booking Platform --- Application Architecture

## 1. Purpose

This document defines the application architecture for the Facility
Booking Platform.

It translates the approved business discovery, software requirements and
UX/design direction into technical boundaries and implementation
principles. It defines how the system should be structured without
prematurely fixing every database column, migration, deployment choice
or implementation task.

The architecture is intended to support a production-style multi-site
facility booking and operations platform while remaining appropriate for
an incremental Laravel implementation.

The core architectural objective is:

> Maintain one authoritative source of truth for availability, bookings,
> payments and daily operations while keeping business rules explicit,
> testable and reusable across customer, management and operational
> interfaces.

This document should be read alongside:

-   `docs/discovery.md`
-   `docs/requirements.md`
-   `docs/design.md`

Where a material product requirement changes, `requirements.md` should
be updated before the architecture is silently changed to implement a
different product.

------------------------------------------------------------------------

## 2. Architectural Principles

The platform should follow these principles:

1.  Prefer a modular monolith over premature distributed architecture.
2.  Keep authoritative business rules on the Laravel server.
3.  Reuse the same application/domain capabilities across Inertia and
    Filament.
4.  Keep controllers, Filament pages, jobs and UI components thin.
5.  Use focused Actions for business operations and Services for
    reusable domain capabilities.
6.  Treat availability, pricing, authorization, booking lifecycle and
    financial calculations as deterministic application concerns.
7.  Protect booking integrity using database transactions, locking and
    revalidation.
8.  Separate booking lifecycle state from payment and invoice state.
9.  Treat external providers as integrations rather than owners of the
    core domain.
10. Prefer asynchronous processing for secondary work that should not
    block a successful core transaction.
11. Design for observability, auditability, accessibility, security and
    testability from the beginning.
12. Optimise based on measured need rather than speculative scale.
13. Keep the application portable even though AWS is the intended
    eventual production target.
14. Treat AI as a first-class application subsystem built over authorised,
    authoritative application services, while keeping core operation independent
    of provider availability.

------------------------------------------------------------------------

## 3. Overall Application Shape

The application will be a **modular Laravel monolith**.

The initial application should not use microservices and should not
introduce a separate backend application for the customer interface.

### 3.1 Customer and Public Interface

The public/customer application will use:

-   Laravel
-   Inertia
-   Vue.js
-   TypeScript
-   shadcn/ui

Laravel routes and controllers will invoke the same application Actions,
Services, Policies and query capabilities used elsewhere in the system.

### 3.2 Management Interface

Management functionality will use **Filament** as the primary interface
foundation.

Filament Resources, Pages, Tables, Forms, Actions, Widgets,
Notifications and navigation should be preferred where they fit the
workflow.

Domain-specific workflows should not be distorted merely to fit generic
CRUD screens.

### 3.3 Operations Interface

Leisure-assistant functionality will use a separate or clearly separated
**Filament operations area/panel**.

Operational workflows such as Today's Schedule should use purpose-built
pages rather than exposing a reduced version of the complete management
interface.

### 3.4 Shared Application Layer

All interfaces share:

-   authentication;
-   authorization;
-   Actions;
-   Services;
-   Policies;
-   domain rules;
-   MySQL data;
-   Redis-backed background processing;
-   audit infrastructure.

Conceptually:

``` text
Public / Customer
Vue + TypeScript + shadcn/ui
            |
          Inertia
            |
     Laravel Controllers
            |
            +---------------------------+
            |                           |
Management Filament              Operations Filament
            |                           |
            +-------------+-------------+
                          |
                  Actions / Services
                          |
                     Domain Rules
                          |
                        MySQL
```

No interface should independently reproduce availability, pricing,
booking-lifecycle or authorization logic.

------------------------------------------------------------------------

## 4. Domain Model

### 4.1 Booking as the Central Aggregate

`Booking` is the central commercial and operational record.

A booking may contain:

-   an individual customer;
-   an optional organisation;
-   a centre;
-   one or more resources;
-   equipment allocations;
-   customer-visible start/end times;
-   operational occupancy/setup/cleanup times;
-   activity/purpose;
-   a price snapshot;
-   lifecycle state;
-   financial state;
-   attendance state;
-   notes;
-   an optional recurring series;
-   payments and invoice relationships;
-   lifecycle and audit history.

A booking must not be reduced to a single `facility_id`, because a
supported booking may involve multiple resources.

### 4.2 Centres

A `Centre` represents one independently configurable venue/site.

A centre can contain:

-   facilities;
-   resources;
-   equipment;
-   opening/bookable-hour configuration;
-   availability blocks;
-   bookings;
-   authorised staff access.

### 4.3 Facilities

A `Facility` is a customer-understandable venue/area such as:

-   Sports Hall;
-   Gymnasium;
-   Astro Turf;
-   Hard Courts;
-   Conference/Meeting Space;
-   Event/Main Hall.

A facility can contain one or more bookable resources.

### 4.4 Resources

A `Resource` represents a bookable option.

Examples:

-   Whole Sports Hall;
-   Badminton Court 1;
-   Badminton Court 2;
-   Whole Astro;
-   Pitch 1;
-   Half Pitch.

Resources may represent different reservable configurations of the same
underlying physical space.

### 4.5 Atomic Allocation Units

Physical conflict detection should use **atomic allocation units**.

Example:

``` text
Sports Hall physical units:
A
B
C
D

Court 1    -> A
Court 2    -> B
Court 3    -> C
Court 4    -> D
Whole Hall -> A + B + C + D
```

A customer-facing Resource can therefore map to one or more atomic
allocation units.

This avoids hard-coding every possible parent/child conflict combination
and gives the concurrency layer a consistent physical primitive to lock
and allocate.

The conceptual relationship is:

``` text
Facility
   |
Resource
   |
Resource <-> Allocation Unit mapping
   |
Allocation Unit
```

The exact table and index structure will be finalised during physical
schema design.

### 4.6 Customer Booking Options vs Physical Allocation

A customer may request a category such as "one badminton court" without
selecting a specific physical court.

The application may select an appropriate available physical
Resource/Allocation Unit while preserving the exact allocation for
management and operational use.

This allocation behaviour must still use the authoritative availability
and conflict rules.

### 4.7 Equipment

Equipment is a separate quantity-constrained domain concept.

An equipment item belongs to an appropriate centre/facility context and
has an available quantity/status.

Bookings can allocate quantities of equipment.

Overlapping bookings must not allocate more quantity than is available.

Equipment allocation must participate in transactional concurrency
protection.

------------------------------------------------------------------------

## 5. Availability Architecture

Availability must be calculated dynamically. The system must not rely on
a persistent `available` boolean as the authoritative state.

A central `AvailabilityService` or equivalent domain capability should
be reused by:

-   public/customer availability search;
-   booking requests;
-   management booking creation;
-   recurring booking validation;
-   rescheduling;
-   alternative searches;
-   closure handling where relevant.

Conceptually:

``` text
Configured bookable hours
- existing protected bookings
- active provisional holds
- manager pre-bookings
- school/internal use
- maintenance
- health-and-safety restrictions
- weather/temporary closures
- manager blockouts
- setup/cleanup occupancy
- physical allocation conflicts
= bookable availability
```

Equipment availability is additionally checked where equipment is
requested.

### 5.1 Time Model

Availability should use time ranges such as `starts_at` and `ends_at`,
rather than assuming one universal named slot duration.

Configuration may constrain:

-   selectable increments;
-   minimum duration;
-   facility-specific slot behaviour;
-   consecutive booking periods.

### 5.2 Operating Time vs Customer Time

The architecture must distinguish:

-   centre/staff operating hours;
-   facility customer-bookable hours;
-   customer booking period;
-   operational setup/cleanup occupancy.

Setup and cleanup may extend the period that blocks an allocation while
remaining invisible or separately presented to the customer.

### 5.3 Availability Is Not a Reservation Guarantee

Displayed availability is informational.

When a reservation is committed, the server must:

1.  start an appropriate transaction;
2.  lock relevant physical allocation/equipment state;
3.  revalidate availability;
4.  reject the request if a conflict now exists;
5.  otherwise create the protected booking/allocation/hold state;
6.  commit.

A stale UI or cache must never be able to bypass this final
authoritative check.

------------------------------------------------------------------------

## 6. Booking Lifecycle Architecture

Booking lifecycle state and financial state are separate concerns.

Example booking lifecycle states may include:

-   Requested;
-   Awaiting Approval;
-   Approved;
-   Confirmed;
-   Rejected;
-   Cancelled;
-   Expired;
-   Completed;
-   No-show.

Exact enum names may be refined during implementation.

Example payment/financial states may include:

-   Not Required;
-   Not Due;
-   Awaiting Payment;
-   Processing;
-   Paid;
-   Failed;
-   Partially Refunded;
-   Refunded.

### 6.1 Standard Card Flow

The standard card workflow is conceptually:

``` text
Request
-> Provisional Protection
-> Manager Approval
-> Awaiting Payment
-> Payment Successful
-> Confirmed
```

Management approval must not automatically mean a standard card-paying
booking is confirmed when payment is still required.

### 6.2 Invoice Flow

An authorised invoice customer may follow:

``` text
Request
-> Provisional Protection
-> Manager Approval
-> Confirmed under invoice terms
-> Invoice
-> Settlement later
```

An unpaid invoice does not automatically release an otherwise valid
confirmed booking merely because payment has not yet occurred.

### 6.3 Exceptional/Event Flow

Later event workflows may support:

``` text
Request
-> Management Review
-> Agreed Price
-> Deposit
-> Confirmed
-> Balance
```

This is not required to be fully implemented in the initial MVP.

### 6.4 Explicit Business Actions

Important transitions should occur through explicit application Actions
rather than arbitrary status dropdown changes.

Examples:

-   `CreateBookingRequest`
-   `ApproveBooking`
-   `RejectBooking`
-   `CancelBooking`
-   `AmendBooking`
-   `RecordArrival`
-   `RecordNoShow`
-   `CompleteBooking`

Actions should validate authorization, current state and business rules
before changing state.

------------------------------------------------------------------------

## 7. Provisional Holds and Expiry

The system must support time-bound reservation protection.

The architecture requires:

-   explicit expiry time;
-   participation in availability while valid;
-   immediate logical inactivity after expiry;
-   release on decline/cancellation/expiry where no other restriction
    applies;
-   configurable policy values.

A cleanup/scheduler delay must not make an already expired hold
authoritative.

The exact physical representation is intentionally deferred. A hold may
ultimately be represented through a dedicated hold record or appropriate
booking/allocation fields.

The architecture therefore requires the **behaviour and invariant**, not
a prematurely fixed table.

Candidate demonstration values such as a 48-hour management-review hold
and a 24-hour post-approval payment deadline remain configurable product
decisions rather than historical facts.

------------------------------------------------------------------------

## 8. Recurring Bookings

Recurring bookings should use a `BookingSeries` plus individually
persisted `Booking` occurrences.

``` text
BookingSeries
   |
   +-- Booking occurrence
   +-- Booking occurrence
   +-- Booking occurrence
   +-- Booking occurrence
```

The series stores recurring intent/common configuration.

Each occurrence is a real booking with its own:

-   date/time;
-   resource allocation;
-   equipment allocation;
-   lifecycle state;
-   financial state;
-   attendance;
-   cancellation/amendment history;
-   audit trail;
-   price snapshot where applicable.

Every generated occurrence must be validated through the same
availability engine.

Conflicts must be shown explicitly and must not be silently dropped.

Individual occurrences can be amended or cancelled independently where
the applicable rules permit.

Multi-occurrence operations should explicitly identify scope such as one
occurrence, this-and-future, or the whole series where supported.

------------------------------------------------------------------------

## 9. Pricing Architecture

Pricing should be a dedicated domain capability rather than a single
price field on a facility.

A `PricingService` should support configuration that may vary by:

-   centre;
-   resource/bookable option;
-   duration or unit;
-   customer/booking classification;
-   equipment;
-   effective date;
-   negotiated discount;
-   authorised manager override;
-   manually agreed event price.

Conceptually:

``` text
Pricing configuration
        |
    PricingService
        |
       Quote
        |
Booking price snapshot
```

### 9.1 Historical Price Integrity

A booking must retain its agreed commercial price independently of
future rate changes.

The snapshot should preserve enough breakdown to explain the total, not
merely one opaque number.

Example:

``` text
Sports Hall          £40
Equipment             £5
Subtotal             £45
Regular discount      -£5
Final total           £40
```

### 9.2 Overrides

An authorised price override should record:

-   calculated/original amount;
-   final amount;
-   responsible user;
-   timestamp;
-   reason.

Price overrides must be auditable.

The initial architecture should not introduce a sophisticated
promotional-pricing engine.

------------------------------------------------------------------------

## 10. Payments, Stripe and Laravel Cashier

The application will use **Laravel Cashier with Stripe** as the
Laravel-side Stripe integration.

Cashier/Stripe must not define the booking domain.

Conceptually:

``` text
Booking / Pricing / Invoice Domain
              |
         PaymentService
              |
      Laravel Cashier
              |
            Stripe
```

The application remains authoritative for its own Booking, Payment and
Invoice state.

### 10.1 Payment Records

Payments should be first-class application records.

The architecture should support more than one financial transaction
where necessary, including future:

-   deposit;
-   balance;
-   refund;
-   partial refund;
-   manual/offline payment.

The MVP may commonly use one payment for a standard booking, but the
data model should not assume that is universally true.

### 10.2 Card Data

Raw card details must never be stored by the application.

Stripe handles payment-card information.

### 10.3 Webhooks

Stripe provider outcomes must be reconciled through
authenticated/verified webhooks.

A browser success redirect is not authoritative proof that payment
succeeded.

Webhook processing must be idempotent.

Duplicate or retried provider events must not create duplicate payments,
confirmations, refunds or other financial effects.

### 10.4 Payment Uncertainty

Where provider state is temporarily uncertain, the application should
represent an honest pending/processing state rather than incorrectly
claiming success or failure.

------------------------------------------------------------------------

## 11. Invoicing

Invoices are application-owned business records.

The architecture should support:

``` text
Invoice
   |
Invoice Line
   |
Booking / charge context
```

An invoice may contain charges for multiple booking occurrences.

A booking should therefore not be architecturally constrained to a
single `invoice_id`.

Invoices should support:

-   customer/organisation ownership;
-   issue date;
-   due date;
-   status;
-   lines;
-   payment allocation;
-   generated document representation.

The application owns the invoice state even if an accounting integration
is introduced later.

Generated invoice PDFs are representations of authoritative invoice
data.

------------------------------------------------------------------------

## 12. Users, Authentication and Authorization

The platform uses one primary `User` identity model across customers,
managers and leisure assistants.

Laravel session/cookie authentication is appropriate for the
Inertia-based monolith.

A JWT/OAuth architecture is not required merely for the application's
own web frontend.

### 12.1 Authorization Stack

The agreed authorization architecture is:

``` text
Laravel Authentication
        |
Spatie Laravel Permission
(system roles + broad capabilities)
        |
Laravel Policies
(record ownership + centre/org scope)
        |
Actions / Services
(business validity)
        |
Spatie Activitylog
(audit)
```

**Spatie Laravel Permission and Laravel Policies are complementary.**

Spatie handles broad system roles/capabilities.

Policies handle instance-specific authorization such as:

-   whether a customer owns a booking;
-   whether a user belongs to the relevant organisation;
-   whether staff are authorised for the centre;
-   whether the user may access a particular invoice/document.

Actions/Services then enforce whether the requested operation is valid
in the current business state.

### 12.2 Broad Permissions

Potential permissions include:

-   `bookings.view`
-   `bookings.create`
-   `bookings.approve`
-   `bookings.amend`
-   `bookings.cancel`
-   `attendance.manage`
-   `payments.view`
-   `payments.record`
-   `payments.refund`
-   `invoices.view`
-   `invoices.manage`
-   `facilities.manage`
-   `closures.manage`
-   `incidents.manage`
-   `reports.view`

Exact permission naming will be refined during implementation.

Avoid permission-per-field/button complexity unless a genuine
requirement emerges.

### 12.3 Organisation Membership

Organisation membership is separate from system-level Spatie roles.

Potential organisation roles may later include:

-   Owner;
-   Admin;
-   Booking Manager;
-   Finance;
-   Member.

A user being a Finance member of one organisation does not make them a
platform-wide finance administrator.

### 12.4 Staff Centre Access

Staff centre access should be explicit and many-to-many capable rather
than permanently storing one centre on the user.

This supports staff who normally work at one centre but temporarily
cover another.

The exact need for effective dates can be decided during implementation.

### 12.5 Filament Panels

Management and Operations panels must enforce explicit panel access and
server-side authorization.

Hiding a menu item or button is never sufficient authorization.

### 12.6 M23 Authorization Hardening

Protected operations require the relevant system role and capability, followed
by a record policy or scoped query. Customer organisation access comes from the
current membership and its organisation role; the initiating `customer_id` does
not grant access to an organisation-owned booking or invoice. Staff access to
centre records uses current assignments, including during Filament actions and
document downloads. Centre creation assigns its management creator so the new
venue can be configured without a global edit exception.
Facility and equipment form actions validate submitted parent IDs against the
current venue hierarchy before saving. Price overrides require the manager role,
pricing capability and a live assignment to the resource's centre.

Reporting requires `reports.view` as well as management booking visibility.
Private Filament exports retain the centre IDs authorised at creation and are
downloadable only by their creator while that manager still has the reporting
capability and every recorded centre assignment. Older exports without scope
metadata must be regenerated. The operations payment capability does not grant
management invoice settlement.

Broader booking and payment concurrency proofs remain in M24. CSP, HSTS,
reverse-proxy settings, production cookie configuration and secret rotation
remain production-readiness work.

------------------------------------------------------------------------

## 13. Database and Concurrency

**MySQL** is the authoritative relational database.

The architecture must protect booking integrity under concurrent
requests.

### 13.1 Reservation Transaction

A critical reservation operation should conceptually perform:

``` text
BEGIN
  lock relevant allocation state
  lock relevant equipment state where required
  revalidate availability
  reject safely if conflict exists
  create/update booking
  create allocations
  create reservation protection
  create required commercial snapshot
COMMIT
```

The implemented reservation boundary is `CentreReservationLock`: a locking
read of the owning centre inside the transaction. Independent centres remain
independent. Creation, recurring creation, assisted creation, amendment,
approval, cancellation, rejection, closure mutations and reservation-sensitive
configuration changes use this boundary. Resource, facility, allocation-unit
and equipment rows are then read under locks; multi-row selections use primary
key order. Equipment moves acquire both centre locks in ascending ID order.

Authoritative availability explicitly requests current locking reads of
physical protection, equipment allocations and closures. A centre lock alone
does not refresh a MySQL REPEATABLE READ snapshot established by an enclosing
transaction. Preview checks remain read-only. Closure impact detection also
uses current reads, including persisted physical units and buffers, so a booking
committed while a closure waits receives an impact rather than being cancelled.
Hierarchy scope queries also use current reads: a newly committed facility must
not hide its bookings from a centre closure under an older transaction snapshot.

### 13.2 Friendly Conflict Handling

A competing request that loses the race should receive a
domain/application outcome such as "the selected time is no longer
available", not a raw SQL/lock exception.

### 13.3 Equipment Concurrency

Equipment quantities must receive equivalent transactional protection so
concurrent bookings cannot collectively allocate more than the available
quantity.

Equipment edits use `UpdateEquipment` under the reservation boundary. Active
future/current allocations prevent location changes and quantity reductions
below peak simultaneous usage. Adjacent intervals are half-open; expired holds
and completed allocations do not consume current capacity. Allocation-unit
mapping changes use `ResourceAllocationConfiguration` and are refused while the
resource has active booking protection. Unchanged mappings are safe to repeat;
historical occupancy mappings are preserved.

### 13.4 Financial, Organisation and Side-Effect Boundaries (M24)

- Invoice issue locks bookings in ascending ID order. Settlement locks those
  bookings before the invoice, its lines and payments. The unique booking/charge
  key prevents duplicate invoice charges; issued amounts remain snapshots.
- Checkout first commits a durable payment attempt, then serialises provider
  creation under booking/payment locks. Persisted parameters and the payment
  reference produce the same Stripe idempotency key on retries. The bounded
  provider call remains inside the second transaction to prevent concurrent
  local checkout creation; it cannot be atomic with Stripe.
- Webhooks lock centre, booking and payment, then deduplicate provider event
  IDs. Terminal success cannot be undone by a later failure; late success after
  cancellation is recorded for review without restoring the booking. Browser
  redirects never establish payment success.
- Existing organisation rows are locked before customer/user rows across
  booking ownership, approval, invoice terms, membership addition and assisted
  onboarding. New organisation creation locks its owner before creating a new,
  previously inaccessible organisation. Membership uniqueness and last-owner
  guards remain in force.
- Attendance and issue reviews lock their canonical record before checking
  transitions. Incident/damage creation locks the centre and current context,
  then rechecks authorization and relationships before saving the record/audit.
  Assisted booking rechecks the manager's role, capability and assignment after
  acquiring its centre lock. All M23 policies remain authoritative.
- Booking, financial and operational audit writes share their mutation's
  transaction. Lifecycle events and queued welcome messages dispatch after
  commit. Communication semantic keys and channel row locks deduplicate local
  delivery. SMTP acceptance followed by a crash before the delivery marker can
  still produce a retry email; distributed exactly-once delivery is not claimed.

M24 adds no schema migration: existing composite hierarchy foreign keys,
booking protection/snapshot/occurrence uniqueness, organisation membership and
billing-owner constraints, invoice charge uniqueness, provider identifier/event
uniqueness and communication semantic keys supply the SQL-enforceable guards.
Arbitrary interval conflicts and aggregate equipment capacity still require
application locking. Deadlocks roll back safely; no blanket automatic retry
around external effects is introduced. Refund/void execution is not currently
implemented and is not inferred from capability names. Lock throughput tuning,
provider retention/recovery limits and SMTP acceptance ambiguity remain explicit
operational concerns for later production/performance work.

------------------------------------------------------------------------

## 14. Core Conceptual Data Model

The conceptual relational model includes:

``` text
User
├── Spatie roles/permissions
├── Organisation Memberships
└── Staff Centre Access

Organisation
└── Organisation Memberships

Centre
├── Facilities
├── Equipment
├── Hours/Availability Configuration
├── Availability Blocks
└── Bookings

Facility
└── Resources

Resource
└── Allocation Unit mappings

BookingSeries
└── Bookings

Booking
├── Resource Allocations
├── Equipment Allocations
├── Reservation Protection / Hold
├── Price Snapshot / Price Lines
├── Payments
├── Invoice Lines
├── Incidents / Damage
└── Lifecycle History

Invoice
└── Invoice Lines

AvailabilityBlock
└── Affected Booking Resolution records
```

Exact tables, columns, indexes, foreign keys, deletion rules and pivot
structures belong to the physical data-model/migration stage.

------------------------------------------------------------------------

## 15. Opening Hours and Operational Time

The data model must support configurable:

-   centre operating hours;
-   facility/resource customer-bookable hours;
-   weekday patterns;
-   exceptional hours;
-   closures/blockouts;
-   operational setup/cleanup periods.

Historical examples may inform realistic demo configuration but must not
become universal hard-coded defaults.

------------------------------------------------------------------------

## 16. Availability Blocks and Closures

Closures and operational blockouts must not be represented as fake
customer bookings.

Use an explicit `AvailabilityBlock`-style concept.

A block should be capable of representing:

-   centre scope;
-   facility scope;
-   resource scope;
-   start/end;
-   type;
-   internal reason;
-   customer-facing message where appropriate;
-   responsible user/audit information.

Possible block types include:

-   school/internal use;
-   examination;
-   maintenance;
-   health and safety;
-   weather;
-   private/internal use;
-   manager blockout;
-   other.

A higher-level block affects relevant subordinate resources.

### 16.1 Existing Bookings

When a new closure affects existing bookings, the system should identify
them and create an explicit management resolution workflow.

Potential resolutions include:

-   reschedule;
-   alternative resource;
-   alternative centre;
-   cancel;
-   refund;
-   account credit;
-   invoice adjustment.

The system must not silently assume the financial outcome.

Reopening/removing a closure should restore otherwise valid availability
and be auditable.

------------------------------------------------------------------------

## 17. Today's Schedule and Operations

Today's Schedule is a purpose-built operational capability, not a
duplicated timetable database.

A `TodayScheduleService` or focused query layer should derive the
operational view from authoritative booking/resource/equipment data.

It should support centre/date context and expose appropriate:

-   current sessions;
-   upcoming sessions;
-   completed sessions;
-   resource information;
-   activity;
-   equipment/setup requirements;
-   booking changes;
-   arrival/no-show/completion state;
-   operational notes.

### 17.1 Now/Next

The operational interface should make Now/Next information prominent,
particularly on phone/tablet.

### 17.2 Attendance Actions

Attendance changes should use explicit Actions such as:

-   `RecordArrival`
-   `RecordNoShow`
-   `CompleteBooking`

These changes should be authorised and audited.

### 17.3 Live Updates

The MVP should favour simple, reliable refresh/polling/Livewire-style
behaviour rather than prematurely requiring WebSockets.

Laravel Reverb or another real-time approach may be introduced later if
demonstrated operational need justifies it.

------------------------------------------------------------------------

## 18. Incidents and Damage

Incidents and damage should be first-class records rather than
unstructured text buried inside a booking.

An incident may contain:

-   booking where relevant;
-   centre;
-   resource where relevant;
-   reporting user;
-   occurrence time;
-   description;
-   status/follow-up.

Damage may additionally record:

-   equipment/resource;
-   responsibility where determined;
-   management outcome;
-   later financial follow-up.

The exact decision to share or separate physical tables should be made
during schema design rather than prematurely merging the concepts.

------------------------------------------------------------------------

## 19. Notifications and Communication

Laravel Notifications should be the primary application abstraction for
transactional communication.

Initial channels:

-   database/in-application notifications;
-   email.

SMS remains deferred.

### 19.1 Transactional Flow

Business Actions should not directly construct provider-specific emails.

Preferred flow:

``` text
Business Action
     |
successful transaction commit
     |
Domain/Application Event
     |
Listener
     |
Queued Laravel Notification
```

Notifications describing committed state should normally be dispatched
after commit.

### 19.2 Notification Types

Meaningful notification types may include:

-   request received;
-   approved;
-   payment required;
-   confirmed;
-   rejected;
-   amended;
-   cancelled;
-   payment received;
-   refund;
-   invoice issued;
-   invoice overdue;
-   booking reminder;
-   closure/reschedule information.

Action-required notifications should include the relevant deadline and
direct application link where appropriate.

### 19.3 Email

Laravel Mail/Notifications should keep the mail provider replaceable.

**Mailtrap Email Testing** will be used for local/development human
inspection.

The production transactional provider will be selected later; AWS SES is
a candidate.

A booking confirmation must only be sent when the booking is genuinely
confirmed, not merely when a request was submitted or approved while
payment is still required.

------------------------------------------------------------------------

## 20. Queues, Scheduler and Redis

**Redis is part of the architecture.**

Its initial primary responsibility is the Laravel queue backend.

Potential later caching may also use Redis where measurement
demonstrates value.

### 20.1 Queue Candidates

Suitable queued work includes:

-   email/notifications;
-   invoice/PDF generation;
-   Excel/report exports;
-   reminders;
-   closure communications;
-   suitable recurring-processing work;
-   selected payment follow-up/reconciliation work;
-   expensive AI summaries.

A critical booking transaction must not depend on a queue worker
completing successfully.

### 20.2 Scheduler

Laravel Scheduler should support tasks such as:

-   hold expiry/reconciliation;
-   payment-deadline processing;
-   reminders;
-   overdue invoice processing;
-   stale pre-booking checks;
-   scheduled operational/reporting work.

Jobs and scheduled processes should be idempotent where repeated
execution could otherwise create incorrect effects.

### 20.3 Redis Is Not the Booking Authority

MySQL remains authoritative.

Redis must not become the source of truth for availability, booking
state, payment state or invoice state.

------------------------------------------------------------------------

## 21. Reporting and Management Dashboard

The initial reporting architecture should use:

-   MySQL;
-   focused reporting/query Services;
-   Filament widgets/tables;
-   Filament's native export system (OpenSpout-backed) for management
    XLSX/CSV exports.

Do not introduce a data warehouse, Elasticsearch or separate analytics
platform without measured need.

### 21.1 Operational Dashboard

The management dashboard should focus on action and attention, including
appropriate:

-   pending approvals;
-   bookings awaiting payment;
-   overdue invoices;
-   closure-affected unresolved bookings;
-   incidents;
-   today/upcoming operations.

### 21.2 Reports

Potential report services include:

-   revenue;
-   utilisation;
-   booking volumes/statuses;
-   cancellations/no-shows;
-   outstanding financials;
-   operational metrics.

Metric definitions must be centralised.

For example, "revenue" must not mean paid cash in one screen and
confirmed booking value in another without explicit distinction.

### 21.3 Exports

Management exports use Filament's native exporter infrastructure, with
OpenSpout providing spreadsheet generation underneath. Exporters consume
the same scoped reporting/query logic as the on-screen reports.

This avoids adding another dependency for the current reporting
requirements. Laravel Excel may be introduced later only if requirements
expand to advanced workbook features such as complex styling, formulas,
multiple worksheets or advanced imports.

Large exports should be queued and stored privately.

------------------------------------------------------------------------

## 22. Audit Trail, Domain History and Technical Logging

Three separate concerns must remain distinct.

### 22.1 Domain/Lifecycle History

Answers:

> What happened to this booking?

This may provide a clean booking timeline for customers/managers.

### 22.2 Audit Trail

Answers:

> Who changed what, when, and why?

Use **Spatie Laravel Activitylog** as the preferred general audit
infrastructure.

Significant audited actions include:

-   booking approval/rejection;
-   cancellation/amendment;
-   price overrides;
-   manual payments;
-   refunds;
-   invoice changes;
-   closures/blockouts;
-   facility/resource/equipment configuration;
-   attendance/no-show/completion;
-   staff permission/centre-access changes.

Financial actions should receive particularly explicit auditing.

Audit history should be append-oriented and not casually editable by
ordinary application users.

### 22.3 Technical Logs

Technical logging covers:

-   exceptions;
-   queue failures;
-   Stripe/webhook failures;
-   email-provider failures;
-   scheduler failures;
-   AI-provider failures;
-   infrastructure/application diagnostics.

Do not log secrets, raw card data or unnecessary personal data.

The application does not require event sourcing.

------------------------------------------------------------------------

## 23. File Storage, Documents and Exports

Generated documents may include:

-   invoice PDFs;
-   booking confirmation PDFs;
-   receipts;
-   statements;
-   credit/refund documents;
-   reports/Excel exports.

Underlying business data is authoritative. Generated files are
representations.

### 23.1 Storage

Use Laravel Filesystem abstraction.

Development may use local/private storage.

AWS production is expected to use S3 or an appropriate S3-backed private
storage configuration.

### 23.2 Private Access

Customer/financial/compliance documents must not rely on publicly
predictable URLs.

Downloads must be authenticated and authorised.

### 23.3 Future Uploads

Future conditional uploads may include:

-   risk assessments;
-   insurance evidence;
-   PAT/electrical documentation;
-   other compliance material.

These should be private by default.

### 23.4 PDF Library

M22 uses `barryvdh/laravel-dompdf` 3.1 with Dompdf 3.1. PDFs are rendered
on demand from Blade views and returned directly to authorised callers;
no public or permanent document copy is written. Remote resources, embedded
PHP and JavaScript are disabled in the renderer.

Document builders use persisted invoice lines and totals, successful payment
records, and booking price snapshots. They never reprice historical bookings.
Invoice statements show invoice charges and successful invoice settlements
with opening and closing invoice balances. Direct booking card payments are
listed separately because they do not belong to the invoice balance. Personal
and organisation ownership are queried separately. London-local calendar
periods are converted to UTC instants for payment queries; user-facing times
use `config('booking.local_timezone')`. Customer policies, live organisation
finance membership, and staff invoice/booking policies enforce downloads.

------------------------------------------------------------------------

## 24. Laravel Application Structure

Use recognisable Laravel structure supplemented with focused application
Actions and Services.

A possible organisation is:

``` text
app/
├── Actions/
│   ├── Bookings/
│   ├── Payments/
│   ├── Invoices/
│   ├── Operations/
│   └── Closures/
├── Ai/
│   ├── Agents/
│   └── Tools/
├── Services/
│   ├── Availability/
│   ├── Pricing/
│   ├── Bookings/
│   ├── Payments/
│   ├── Invoicing/
│   ├── Reporting/
│   └── Operations/
├── Models/
├── Policies/
├── Events/
├── Listeners/
├── Jobs/
├── Notifications/
├── Mail/
├── Filament/
│   ├── Management/
│   └── Operations/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
└── Integrations/
    └── Stripe/
```

Exact folders may be refined as the codebase grows.

### 24.1 Models

Eloquent models should primarily represent persistence, relationships,
casts and appropriate scopes.

Avoid giant models containing cross-domain workflow orchestration.

### 24.2 Actions

Actions represent discrete business operations.

Examples:

-   `CreateBookingRequest`
-   `ApproveBooking`
-   `RejectBooking`
-   `CancelBooking`
-   `CompleteBooking`
-   `RecordNoShow`

### 24.3 Services

Services represent reusable capabilities.

Examples:

-   `AvailabilityService`
-   `BookingConflictService`
-   `RecurringBookingService`
-   `PricingService`
-   `PaymentService`
-   `InvoiceService`
-   `EquipmentAvailabilityService`
-   `TodayScheduleService`

Avoid creating one giant `BookingService`.

### 24.4 Controllers and Filament

Controllers and Filament Pages/Actions should validate/adapt input,
enforce appropriate authorization boundaries and delegate business work.

The same underlying Actions/Services should be callable from both
customer and staff interfaces.

### 24.5 Validation

Laravel Form Requests may handle structural request validation.

Domain/application services must still validate authoritative business
truth such as availability, lifecycle validity and pricing rules.

### 24.6 DTOs and Value Objects

Introduce DTOs/value objects selectively where they make boundaries
clearer.

Potential examples:

-   Money;
-   DateRange;
-   BookingPeriod;
-   PriceBreakdown;
-   AvailabilityResult.

Avoid ceremonial abstractions with no practical value.

### 24.7 Enums

PHP enums are suitable for controlled domain states such as:

-   BookingStatus;
-   PaymentStatus;
-   InvoiceStatus;
-   AttendanceStatus;
-   AvailabilityBlockType.

System roles should be managed through Spatie Laravel Permission rather
than requiring a duplicate `SystemRole` enum as an architectural
requirement.

------------------------------------------------------------------------

## 25. API and Integration Boundaries

The initial application should **not** introduce a separate internal
REST API.

Inertia and Filament execute against the same Laravel application layer.

Focused JSON endpoints are acceptable where browser interactivity
requires them, for example availability queries.

Returning JSON does not require creating a duplicated API architecture.

### 25.1 Future Public API

A versioned public API may be introduced later if a real consumer
requires it, such as:

-   native/mobile application;
-   partner booking system;
-   school system;
-   external marketplace.

Such an API should reuse existing Actions, Services, Policies and domain
rules.

It should not create a parallel booking implementation.

### 25.2 External Integrations

Provider-specific behaviour should be isolated behind appropriate
application/integration boundaries.

Potential integrations include:

-   Stripe;
-   transactional email;
-   future SMS;
-   future accounting;
-   future calendars;
-   AWS infrastructure services.

The domain should describe business meaning rather than
provider-specific operations.

------------------------------------------------------------------------

## 26. Laravel AI SDK and AI Architecture

Facility4Hire includes planned AI-assisted capabilities using the **Laravel AI
SDK**. Runtime AI is a genuine application subsystem, implemented after the
deterministic services it depends upon. It is not a dependency of the core MVP
booking engine.

The intended flow is:

``` text
User
  |
AI Interface
  |
Laravel AI SDK
  |
AI Agent
  |
Authorised Application Tools
  |
Application / Domain Services
  |
Database / approved external services
```

The model never becomes a parallel application layer or an independent source
of business truth.

### 26.1 Provider Model

Development can use **Ollama** locally.

Production should remain provider-configurable.

Conceptually:

``` text
Development:
Laravel
   |
Laravel AI SDK
   |
Ollama
   |
Local model

Production:
Laravel
   |
Laravel AI SDK
   |
Configured production provider
```

Application business logic should not become Ollama-specific.

The Laravel AI SDK is the provider abstraction and orchestration boundary.
Provider/model selection and credentials belong in environment configuration
and provider-specific integration code, not in domain services or prompts.

### 26.2 AI Use Cases

Planned AI capabilities include:

-   summarising operational issues;
-   producing management and operational briefings;
-   summarising incidents;
-   asking natural-language questions about authorised application data;
-   explaining reporting results;
-   summarising booking/customer history;
-   analysing utilisation, booking patterns, cancellations and no-shows from
    authoritative reporting results;
-   assisting facility and availability discovery;
-   identifying information requiring management attention.

Example:

``` text
Manager:
"What needs my attention today?"
        |
AI Agent
        |
Authorised application tools
        |
Booking / Finance / Operations query services
        |
Authoritative application data
        |
AI explanation
```

### 26.3 Tool-Based Grounding

AI agents access application capabilities through controlled tools. A tool is
an adapter over an authorised application/domain service or query capability;
it is not a second implementation of the business rule.

Examples:

-   get today's operational summary;
-   search authorised bookings;
-   retrieve booking details;
-   retrieve revenue report;
-   retrieve utilisation report;
-   retrieve outstanding invoices;
-   retrieve closure-affected bookings;
-   retrieve incident summaries.

The LLM should not independently reproduce availability, pricing,
payment or reporting calculations from arbitrary raw data when an
authoritative service already exists.

Tools should:

-   define bounded, validated inputs;
-   resolve the authenticated actor and relevant centre/organisation scope;
-   enforce Policies and permissions on every call;
-   call the existing service/query capability;
-   return the minimum structured result needed by the agent;
-   expose safe domain errors without leaking internal details.

Agents are responsible for interpreting user intent, selecting permitted tools
and composing a useful response. They are not responsible for persistence,
authorisation decisions, transactional changes or authoritative calculations.

Application services remain responsible for use-case orchestration and
validation. Domain services remain responsible for deterministic business
rules. Policies and permission checks determine access. Eloquent/persistence
remains behind those boundaries rather than being exposed directly to the
model.

### 26.4 Deterministic Boundaries

AI must **not** replace deterministic logic for:

-   availability;
-   resource conflicts;
-   resource allocation;
-   equipment allocation;
-   pricing;
-   booking lifecycle;
-   payments;
-   invoice calculations;
-   authorization;
-   financial calculations.

Alternative availability must not depend on AI.

Prompts and agent instructions must not contain the only implementation of a
business rule. Any proposed consequential action must be revalidated and
executed through the normal authorised Action/Service, with explicit user
confirmation where required. Model output must never be written directly as an
authoritative state transition.

### 26.5 Authorization and Privacy

AI access must respect the permissions and data scope of the
authenticated user.

AI must not become a backdoor around:

``` text
Spatie Permission
-> Policies
-> authorised Services/Queries
```

Only necessary data should be supplied to external model providers.

Secrets, raw payment data and unnecessary personal information must not
be exposed.

Tools should return a denial or appropriately scoped empty result without
revealing the existence of records the user cannot access. Prompt instructions
are not an authorisation boundary.

### 26.6 Read-Only First

Initial AI functionality should be read-only assistance.

The AI may retrieve, summarise, explain or propose navigation/actions.

It should not initially autonomously:

-   approve bookings;
-   cancel bookings;
-   issue refunds;
-   change prices;
-   create closures;
-   mark invoices paid.

Consequential operations remain explicit deterministic user-triggered
Actions.

### 26.7 Structured Outputs and Presentation

Use structured outputs where they improve validation, reliable presentation or
tool orchestration. Structured response schemas should be versioned and tested
like other application boundaries.

The interface may render tool-derived results using ordinary application cards,
tables and links, alongside an AI-generated explanation. It should distinguish
generated interpretation from authoritative record/report data where confusion
could affect a decision.

Malformed or incomplete structured output must fail validation and produce a
safe retry/error path rather than being silently accepted.

### 26.8 Auditability and Observability

Record sufficient safe metadata to diagnose and audit material AI activity,
which may include:

-   authenticated user and scope;
-   agent/workflow and tool name;
-   safe input identifiers or correlation ID;
-   tool outcome and duration;
-   provider/model identifier where operationally useful;
-   timeout, refusal and validation failures.

Do not indiscriminately retain full prompts, responses, tool payloads or
sensitive customer data. Business audit records for consequential actions remain
separate from technical AI logs.

### 26.9 Failure Handling and Graceful Degradation

If Ollama or a production AI provider is unavailable:

-   bookings continue;
-   availability continues;
-   payments continue;
-   invoicing continues;
-   Today's Schedule continues;
-   reporting continues.

AI-generated interpretations should be distinguishable from
authoritative application records.

AI calls require bounded timeouts and explicit handling for provider errors,
tool errors, invalid structured output and partial completion. Retrying must not
duplicate consequential effects. Failure of an AI summary or briefing is a
secondary failure and must not roll back committed application state.

### 26.10 Testing

Test AI tools independently of the model, including validation, permission and
centre/organisation scope, service reuse, structured results, data minimisation
and error mapping.

Agent/workflow tests should use faked model responses for normal automated test
runs and cover tool selection, structured-output validation, provider failure,
timeouts and attempts to bypass authorisation. A small separate integration
suite may verify Ollama and configured production-provider compatibility.

Deterministic domain tests remain the proof of availability, conflict,
allocation, pricing, payment, permission and lifecycle correctness. Model tests
must not replace them.

------------------------------------------------------------------------

## 27. Security Architecture

Security is a cross-cutting architectural concern.

### 27.1 Authentication

Use Laravel's secure session/cookie authentication model with
appropriate:

-   password hashing;
-   CSRF protection;
-   session configuration;
-   login throttling;
-   email verification where appropriate;
-   password reset.

### 27.2 Server-Side Authorization

Every protected operation must enforce server-side authorization.

UI visibility is not authorization.

Customer ownership, organisation isolation and staff centre access must
be enforced on the server.

### 27.3 Financial Security

Financial Actions require explicit authorization and auditing.

Do not trust client-supplied:

-   price;
-   payment status;
-   approval state;
-   booking status;
-   refund state.

### 27.4 Stripe

Use Cashier/Stripe without storing raw card data.

Verify webhook authenticity and process events idempotently.

Browser redirects are not authoritative payment confirmation.

### 27.5 Secrets

Secrets must not be committed to Git.

`.env.example` should contain safe placeholders only.

Production secrets/configuration should use appropriate secure
environment/AWS mechanisms.

### 27.6 Public Repository and Demo Data

The repository is public.

Do not commit:

-   historical customer PII;
-   historical operational documents containing private information;
-   real credentials;
-   historical branding assets where reuse would imply affiliation.

Use fictional/anonymised demo data.

### 27.7 Private Files

Private documents require authenticated/authorised download.

Uploads must be validated where upload functionality exists.

### 27.8 Logging

Logs must avoid secrets, raw card data and unnecessary sensitive
information.

### 27.9 Production Hardening

Production should use:

-   HTTPS/TLS;
-   secure cookie configuration;
-   appropriate browser security headers;
-   supported dependencies;
-   lockfiles;
-   vulnerability/dependency checks.

CSP and other detailed browser policies should be defined based on the
actual frontend and Stripe integration.

------------------------------------------------------------------------

## 28. Error Handling, Resilience and Recovery

Failures should be classified by business impact.

### 28.1 Critical Transactional Failures

Examples:

-   resource allocation;
-   availability revalidation;
-   equipment allocation;
-   critical booking transition;
-   payment reconciliation;
-   financial state changes.

These must fail safely and preserve a consistent state.

### 28.2 Expected Business Conflicts

Examples:

-   resource became unavailable;
-   hold expired;
-   booking cannot be cancelled;
-   equipment is unavailable;
-   lifecycle transition is invalid.

These should produce explicit application/domain outcomes and useful
user messages rather than generic 500 errors.

### 28.3 Secondary Failures

Examples:

-   email;
-   PDF generation;
-   Excel export;
-   non-critical notifications;
-   AI summaries.

A secondary failure should not roll back an otherwise valid committed
booking.

### 28.4 After-Commit Side Effects

Notifications/events that describe committed state should normally
execute after the database transaction has committed.

This prevents a customer receiving a success message for state that
subsequently rolled back.

### 28.5 Retry Safety

Queued jobs and integration handlers must be designed for retry safety
where duplicate execution could create incorrect effects.

Financial operations require stronger explicit idempotency controls than
ordinary background tasks.

### 28.6 User-Safe Errors

Production users should never receive raw:

-   SQL errors;
-   stack traces;
-   filesystem paths;
-   credentials;
-   internal exception details.

Technical diagnostics belong in authorised logging/monitoring.

### 28.7 Operational Visibility

Business-impacting unresolved failures should be visible where
appropriate, for example:

-   failed customer communication;
-   payment awaiting reconciliation;
-   failed report generation.

Not every technical exception belongs on the management dashboard.

------------------------------------------------------------------------

## 29. Performance, Caching and Scalability

Performance optimisation should begin with:

-   appropriate relational modelling;
-   indexes;
-   efficient SQL/Eloquent queries;
-   eager loading;
-   pagination;
-   bounded payloads;
-   measurement.

Do not begin by caching everything.

### 29.1 N+1 Prevention

Filament tables, customer booking history and Today's Schedule must
deliberately avoid N+1 query behaviour.

### 29.2 Pagination

Large booking, customer, invoice and audit collections should be
paginated/bounded.

### 29.3 Availability Performance

Availability is correctness-critical.

Optimisation order should generally be:

``` text
Correct implementation
-> measure
-> improve query/index strategy
-> consider selective caching if justified
```

### 29.4 Availability Caching

Any cached availability is advisory only.

The final reservation transaction must always revalidate against
authoritative MySQL state.

### 29.5 Redis Caching

Redis may later cache relatively stable/read-heavy information where
useful.

Frequently changing booking, financial, operational and availability
state should not be cached indiscriminately.

### 29.6 Reporting

Start with relational queries.

Only introduce aggregates/caching or specialised analytics
infrastructure when measured performance requires it.

### 29.7 Frontend

Avoid unnecessarily large Inertia payloads and dependencies.

Facility images should be appropriately sized/optimised for responsive
delivery.

------------------------------------------------------------------------

## 30. Testing Architecture

Testing should be risk-based rather than driven by an arbitrary coverage
percentage.

Business behaviour is more important than merely proving routes return
HTTP 200.

### 30.1 Test Layers

Use an appropriate combination of:

-   focused unit tests;
-   Laravel feature/domain tests;
-   HTTP/Inertia tests;
-   Filament tests;
-   integration tests;
-   a small number of browser/E2E tests.

### 30.2 Highest-Risk Areas

The strongest automated coverage should focus on:

-   availability;
-   physical allocation;
-   concurrency;
-   authorization/data isolation;
-   booking lifecycle;
-   payments/webhooks;
-   recurring bookings.

### 30.3 Availability Tests

Include scenarios for:

-   existing bookings;
-   active holds;
-   expired holds;
-   closures/blockouts;
-   setup/cleanup overlap;
-   boundary times;
-   parent/child physical conflicts;
-   sibling resources;
-   multi-resource bookings;
-   equipment quantities.

### 30.4 Concurrency Tests

Concurrency protection must be tested against real MySQL behaviour
sufficiently to prove that two simultaneous conflicting reservations
cannot both succeed.

Equivalent equipment-concurrency behaviour should also be tested.

### 30.5 Lifecycle Tests

Actions should test permitted and forbidden transitions.

Examples include:

-   approval does not confirm a standard card booking while payment is
    still required;
-   successful reconciled payment can confirm an eligible booking;
-   expired/cancelled/rejected states release protection according to
    policy.

### 30.6 Payment Tests

Test:

-   successful payment;
-   failed payment;
-   processing/pending state;
-   duplicate webhook;
-   browser redirect vs webhook authority;
-   refund;
-   manual/offline payment;
-   invoice workflow;
-   idempotency.

### 30.7 Authorization Tests

Test cross-user and cross-organisation isolation for:

-   bookings;
-   invoices;
-   payments;
-   documents;
-   organisation data.

Test staff least privilege and centre access.

### 30.8 Recurring Tests

Test:

-   occurrence generation;
-   explicit conflicts;
-   no silent drops;
-   individual occurrence cancellation;
-   individual occurrence movement without corrupting the rest of the
    series.

### 30.9 Notifications and Email

Use Laravel fakes where appropriate in automated tests.

Mailtrap is for human development inspection, not a replacement for
assertions.

### 30.10 Audit Tests

Significant actions should assert expected audit/history behaviour.

### 30.11 Selective E2E

High-value end-to-end scenarios include:

1.  customer discovery -\> request -\> approval -\> payment -\>
    confirmation;
2.  manager request review -\> approval;
3.  operational schedule -\> arrival -\> completion/no-show.

A feature is not complete merely because its UI exists; appropriate
automated tests and existing test suites should remain green.

------------------------------------------------------------------------

## 31. Deployment and Environment Architecture

Use distinct environments:

``` text
Local Development
-> CI
-> Staging
-> Production
```

Do not share production databases, credentials or live Stripe keys with
development/staging.

### 31.1 Local Development

Local development should use Laravel Sail/Docker under WSL.

Expected local services include:

-   Laravel/PHP;
-   MySQL;
-   Redis;
-   Vite/frontend tooling;
-   queue worker;
-   scheduler as required;
-   Mailtrap Email Testing;
-   Stripe test mode;
-   Ollama for local AI development.

### 31.2 CI

GitHub Actions is the intended CI platform.

A typical pipeline should eventually include:

``` text
Install PHP dependencies
Install JavaScript dependencies
Run style/lint checks
Run static/type checks
Build frontend
Run Laravel tests
Run required MySQL/integration tests
```

Exact lint/static-analysis tooling can be selected during implementation
planning.

### 31.3 Database Changes

Use Laravel migrations.

Do not rely on undocumented manual production schema changes.

Destructive/large migrations require deliberate review and deployment
planning.

### 31.4 Queue Workers

Production queue workers must be supervised and restarted/reloaded
appropriately during deployment.

### 31.5 Scheduler

Laravel scheduled tasks must run automatically in deployed environments.

### 31.6 Backups

Production architecture must include backups for:

-   MySQL;
-   required private files.

Detailed frequency, retention, encryption and restore-testing policy
should be defined before production launch.

### 31.7 Deployment Flow

A likely flow is:

``` text
Feature branch
-> tests
-> pull request
-> GitHub Actions
-> review
-> merge
-> staging
-> smoke verification
-> production deployment
-> migrations
-> worker restart/reload
-> health verification
```

Code rollback must not be assumed to automatically reverse an
incompatible database migration.

------------------------------------------------------------------------

## 32. AWS Production Target

AWS is the intended eventual production hosting target.

AWS-specific infrastructure must not leak unnecessarily into
business/domain logic.

Likely service mapping:

``` text
Application compute       -> exact service TBD
MySQL                     -> Amazon RDS for MySQL
Redis                     -> Amazon ElastiCache for Redis
Private/generated files   -> Amazon S3
Transactional email       -> Amazon SES candidate
Logs/monitoring           -> Amazon CloudWatch
Secrets/configuration     -> Secrets Manager and/or Parameter Store
HTTPS/certificates        -> appropriate AWS networking/certificate services
```

### 32.1 Compute Is Intentionally Open

Do not lock the application to EC2, ECS/Fargate or another compute model
yet.

Choose the production compute approach at the deployment milestone based
on:

-   cost;
-   operational complexity;
-   scaling requirements;
-   deployment ergonomics;
-   portfolio/learning value.

### 32.2 Portability

Use Laravel abstractions for:

-   filesystem;
-   queues;
-   cache;
-   mail;
-   configuration.

This keeps the application portable and reduces AWS coupling.

### 32.3 Horizontal Growth

Where practical, production application processes should be compatible
with externalised shared state so the web tier can later scale
horizontally.

Conceptually:

``` text
Load Balancer
     |
+----+----+
|         |
Laravel  Laravel
|         |
+----+----+
     |
Shared RDS / Redis / S3
```

This is a growth path, not an initial complexity requirement.

### 32.4 Independent Worker Scaling

Web processes and queue workers should be independently scalable where
the deployment model permits it.

------------------------------------------------------------------------

## 33. Observability

The platform should make important technical failures diagnosable.

Observe/log appropriate:

-   unexpected application exceptions;
-   slow/problematic requests;
-   failed queue jobs;
-   Stripe webhook/payment failures;
-   email failures;
-   scheduler failures;
-   database/application health;
-   AI-provider failures;
-   report/export failures.

Structured context may include safe identifiers such as:

-   booking reference;
-   internal booking ID;
-   payment ID;
-   provider event ID;
-   job ID;
-   user ID where appropriate.

Do not include secrets or unnecessary sensitive payloads.

Health checks should provide operational confidence without exposing
sensitive diagnostics publicly.

A specific monitoring vendor is not locked at this stage.

------------------------------------------------------------------------

## 34. AI-Assisted Development and Runtime AI Are Separate

The project may use ChatGPT, Claude, Gemini, Codex and IDE agents to
accelerate software development.

That development workflow is separate from the application's runtime
Laravel AI SDK capability.

AI coding agents must follow the repository's approved requirements,
design, architecture and later implementation plan rather than inventing
product behaviour.

A later `AGENTS.md` should instruct agents to:

-   read project documentation first;
-   avoid inventing requirements;
-   reuse Actions/Services;
-   avoid business logic in Vue/controllers/Filament/jobs/notifications;
-   enforce Policies;
-   avoid direct arbitrary lifecycle status edits;
-   preserve transaction boundaries;
-   avoid duplicate availability/pricing/conflict logic;
-   add appropriate automated tests;
-   flag ambiguity rather than silently deciding product policy.

------------------------------------------------------------------------

## 35. Explicitly Deferred Architecture

The following should not be introduced without a demonstrated
requirement or explicit scope change:

-   microservices;
-   Kubernetes;
-   Kafka/event-streaming infrastructure;
-   multiple application databases;
-   Elasticsearch;
-   service mesh;
-   multi-region architecture;
-   speculative internal REST API;
-   native mobile backend requirements;
-   advanced SMS infrastructure;
-   advanced accounting integration;
-   external calendar integration;
-   advanced AI automation;
-   AI-dependent booking decisions;
-   complex enterprise RBAC;
-   data warehouse/advanced analytics platform;
-   aggressive availability caching;
-   WebSockets solely for architectural appearance.

------------------------------------------------------------------------

## 36. Open Technical Decisions

The following are intentionally deferred:

1.  Exact AWS compute choice.
2.  Exact physical hold-table representation.
3.  Exact table/column/index/foreign-key layout.
4.  Exact deletion/soft-deletion strategy per entity.
5.  Exact static-analysis/lint toolchain.
6.  Exact production email provider, with SES a candidate.
7.  Exact production AI provider/model.
8.  Exact cache candidates after measurement.
9.  Detailed backup frequency/retention/restore policy.
10. Whether staff-centre assignments require effective dates.
12. Whether WebSockets/Reverb are ever justified by measured operational
    need.

These are not blockers for the current architecture.

------------------------------------------------------------------------

## 37. Architecture Traceability

Architecture decisions should remain traceable to requirements and
tests.

Examples:

``` text
AVAIL-004 / AVAIL-006 / DATA-003
-> AvailabilityService
-> atomic allocation units
-> MySQL transaction + locking + revalidation
-> concurrency feature/integration tests
```

``` text
PAY-005 / REL-001 / DATA-005
-> separate payment state
-> Cashier/Stripe integration
-> verified idempotent webhooks
-> payment reconciliation tests
```

``` text
SEC-002 / SEC-003 / SEC-004
-> Spatie Permission + Policies
-> server-side ownership/scope enforcement
-> cross-user/cross-organisation authorization tests
```

``` text
OPS-001 / OPS-003 / RESP-002
-> TodayScheduleService
-> purpose-built Operations Filament page
-> responsive operational workflow tests
```

This traceability should continue into `implementation-plan.md`.

------------------------------------------------------------------------

## 38. Architecture Acceptance Criteria

The architecture is considered implementation-ready when:

-   one authoritative Laravel application/domain layer is defined;
-   customer, management and operations interfaces have clear
    boundaries;
-   availability and concurrency have a safe authoritative design;
-   booking and financial lifecycles are separated;
-   recurring bookings have a persistent occurrence model;
-   pricing preserves historical commercial state;
-   Stripe/payment reconciliation is provider-safe and idempotent;
-   authorization distinguishes broad permissions from record-level
    scope;
-   MySQL and Redis responsibilities are clear;
-   closure/blockout behaviour is separate from customer bookings;
-   Today's Schedule derives from authoritative booking data;
-   audit history and technical logging are separate;
-   documents/private storage have an authorization model;
-   external integrations have explicit boundaries;
-   AI is a planned, provider-independent and permission-aware subsystem
    grounded in authoritative services while core operation remains independent
    of provider availability;
-   testing covers the highest-risk business rules;
-   AWS is a target without prematurely fixing compute infrastructure;
-   deferred decisions are explicitly documented rather than silently
    invented.

------------------------------------------------------------------------

## 39. Final Architecture Summary

The target architecture is:

``` text
CUSTOMER / PUBLIC
Vue + TypeScript + shadcn/ui
          |
        Inertia
          |
          v
+--------------------------------------------------+
|                    LARAVEL                       |
|                                                  |
| Controllers          Filament Management         |
|                      Filament Operations         |
|                           |                      |
|                  Actions / Services              |
|                           |                      |
| Availability | Booking | Pricing | Reporting     |
| Payments     | Invoice | Operations | AI         |
|                           |                      |
| Spatie Permission -> Laravel Policies            |
| Spatie Activitylog                               |
+--------------------+-----------------------------+
                     |
        +------------+-------------+
        |            |             |
        v            v             v
      MySQL        Redis      Private Storage
 authoritative    queues      local / S3 later
 business data    + selective
                  cache

EXTERNAL / INFRASTRUCTURE
├── Stripe <- Laravel Cashier
├── Email <- Laravel Mail / Notifications
├── AI <- Laravel AI SDK
│        ├── Ollama locally
│        └── configurable production provider
├── AWS production target
│        ├── RDS MySQL
│        ├── ElastiCache Redis
│        ├── S3
│        ├── CloudWatch
│        └── Secrets Manager / Parameter Store
└── Future integrations
         ├── SMS
         ├── Accounting
         └── Calendar
```

The architecture deliberately prioritises a reliable modular monolith,
explicit business rules, transactional booking integrity and reusable
application capabilities over unnecessary infrastructure complexity.

The next project stage is to convert this architecture and the approved
requirements/design into an incremental `implementation-plan.md` before
scaffolding the Laravel application.
