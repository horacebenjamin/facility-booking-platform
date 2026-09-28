# Facility Booking Platform — Software Requirements Specification

## 1. Purpose

This document defines the functional and non-functional requirements for the Facility Booking Platform.

The requirements are derived from the business discovery documented in `docs/discovery.md`.

This document defines what the system must do. It does not define the final user interface, database schema, framework architecture or implementation approach unless a technical constraint is itself a requirement.

---

## 2. Requirement Terminology

The following terminology is used throughout this document:

- **MUST** — required for the capability when that capability is included in the implementation phase being delivered.
- **SHOULD** — strongly recommended but may be deferred where justified.
- **MAY** — optional capability.
- **TBD** — requires a later product/configuration decision.

A `MUST` requirement does not automatically mean that the capability belongs in the initial MVP.

MVP scope is defined separately by the MVP requirements and acceptance outcome in this document.

Requirements use stable identifiers so they can be referenced from design documents, architecture decisions, implementation tasks and automated tests.

Example:

`BOOK-001`

---

## 3. Product Scope

The platform MUST support the management and commercial hire of facilities across multiple centres.

The core workflow MUST support:

1. customer discovery of facilities;
2. availability checking;
3. booking requests;
4. provisional reservation;
5. management approval;
6. payment or invoice arrangements;
7. booking confirmation;
8. operational delivery by on-site staff;
9. booking completion.

The system MUST provide a single authoritative source of truth for facility availability and bookings.

---

# 4. Users and Access

## USER-001 — Customer Account

The system MUST allow a customer to create and authenticate an individual account.

## USER-002 — Customer Profile

An authenticated customer MUST be able to maintain relevant profile and contact information.

## USER-003 — Manager Account

The system MUST support users with management permissions.

## USER-004 — Leisure Assistant Account

The system MUST support users with leisure-assistant permissions.

## USER-005 — Role-Based Authorization

The system MUST restrict actions according to the authenticated user's permissions.

A customer MUST NOT be able to perform management or leisure-assistant operations unless separately authorised.

## USER-006 — Multi-Centre Management

An authorised manager MUST be able to manage multiple centres from the same system.

## USER-007 — Staff Centre Access

The system MUST support associating operational staff with one or more centres.

## USER-008 — Organisation Membership

The system SHOULD support customers belonging to an organisation.

## USER-009 — Organisation Administration

An authorised organisation user SHOULD be able to manage users associated with their organisation.

Detailed organisation roles and permissions are TBD.

---

# 5. Centres

## CENTRE-001 — Centre Records

Managers MUST be able to create, view and update centre records.

## CENTRE-002 — Centre Status

Managers MUST be able to make a centre unavailable for booking without deleting historical information.

## CENTRE-003 — Independent Configuration

Each centre MUST be independently configurable.

Configuration may include:

- facilities;
- resources;
- equipment;
- opening hours;
- pricing;
- availability restrictions.

## CENTRE-004 — Centre Information

The system MUST support customer-facing information about each centre.

The exact public fields will be defined during UX/design.

---

# 6. Facilities and Resources

## FAC-001 — Facility Management

Managers MUST be able to create and maintain facilities belonging to a centre.

## FAC-002 — Resource Management

A facility MUST be capable of containing independently bookable resources.

Examples include:

- individual badminton courts;
- Astro Turf sections;
- tennis courts.

## FAC-003 — Whole-Facility Booking

A facility MUST be capable of being reserved as a whole where configured.

## FAC-004 — Parent/Child Conflict

An exclusive booking of a parent facility MUST prevent overlapping bookings of its child resources.

## FAC-005 — Child/Parent Conflict

An active booking of a child resource MUST prevent an overlapping exclusive booking of its parent facility.

## FAC-006 — Sibling Resources

Where permitted, separate child resources within the same parent facility MUST be independently bookable during overlapping periods.

## FAC-007 — Facility Capacity

The system SHOULD support maximum capacity values for facilities/resources.

## FAC-008 — Resource Status

Managers MUST be able to make a facility/resource temporarily or permanently unavailable for new bookings without deleting historical bookings.

---

# 7. Equipment

## EQUIP-001 — Equipment Records

Managers MUST be able to maintain equipment associated with centres and/or facilities.

## EQUIP-002 — Equipment Quantity

The system MUST support equipment quantities.

## EQUIP-003 — Equipment Booking

A booking MUST be capable of requesting equipment.

## EQUIP-004 — Equipment Availability

The system MUST prevent overlapping bookings from allocating more units of equipment than are available for the relevant period.

## EQUIP-005 — Equipment Setup Information

Equipment requirements MUST be visible to authorised operational staff.

## EQUIP-006 — Equipment Pricing

The pricing model SHOULD support equipment that is either:

- included in the facility price; or
- separately chargeable.

---

# 8. Opening and Bookable Hours

## HOURS-001 — Centre Operating Hours

Managers MUST be able to configure operating hours for centres.

## HOURS-002 — Facility Bookable Hours

The system MUST support facility/resource bookable hours that differ from the wider centre operating hours.

## HOURS-003 — Day-Specific Hours

Opening/bookable hours MUST support differences by day of week.

## HOURS-004 — Exceptional Hours

Managers MUST be able to define exceptional hours for specific dates.

## HOURS-005 — Closures Override Hours

A closure or availability block MUST override otherwise valid bookable hours.

## HOURS-006 — Staff Time vs Customer Booking Time

The system MUST NOT assume that staff operating hours and customer-bookable hours are identical.

For example, historical evening operations could involve leisure assistants starting at approximately 18:00, while the first indoor bookings began at approximately 18:15 to allow time to open and prepare the centre and equipment.

An outdoor Astro Turf booking could begin at approximately 18:00 because staff could open the relevant gates without requiring the same preparation period.

These times are historical examples rather than universal system defaults.

## HOURS-007 — Setup and Cleanup

The system SHOULD support setup and cleanup periods that extend operational occupancy beyond the customer-visible booking period.

---

# 9. Availability

## AVAIL-001 — Availability Calculation

The system MUST determine availability using the configured resource schedule and existing restrictions.

Availability MUST account for relevant:

- confirmed bookings;
- provisional holds;
- parent/child resource conflicts;
- centre/facility/resource closures;
- school use;
- maintenance blocks;
- management blocks;
- applicable setup/cleanup occupancy.

## AVAIL-002 — Public Availability

Customers MUST be able to view available booking periods.

## AVAIL-003 — Booking Privacy

Public availability MUST NOT expose the identity or private information of another customer or organisation.

## AVAIL-004 — Real-Time Validation

Availability MUST be revalidated before a reservation is successfully created.

## AVAIL-005 — Conflict Prevention

The system MUST prevent incompatible bookings from occupying the same exclusive resource during overlapping periods.

## AVAIL-006 — Concurrent Booking Protection

The system MUST prevent two concurrent requests from successfully reserving the same exclusive resource for an overlapping period.

## AVAIL-007 — Consecutive Slots

The system MUST support bookings spanning consecutive valid booking periods where permitted.

## AVAIL-008 — Variable Booking Durations

The system MUST NOT assume a universal slot duration across every facility/resource.

## AVAIL-009 — Minimum Duration

A resource SHOULD support a configurable minimum booking duration.

## AVAIL-010 — Availability Search

Customers MUST be able to search availability by relevant criteria including date and facility/resource.

Activity and centre filtering SHOULD also be supported.

---

# 10. Booking Requests

## BOOK-001 — Booking Request

An authenticated customer MUST be able to request an available resource for a supported date and time.

## BOOK-002 — Booking Owner

Every customer booking MUST belong to either an individual customer or, where supported, an organisation acting through an authorised user.

## BOOK-003 — Multiple Resources

A booking MUST be capable of containing more than one facility/resource where the booking type permits it.

## BOOK-004 — Activity

A booking MUST record the purpose/activity where required.

## BOOK-005 — Booking Contact

A booking MUST retain sufficient contact information for operational communication.

## BOOK-006 — Equipment Request

A customer MUST be able to request available equipment where the resource supports it.

## BOOK-007 — Price Snapshot

Once a booking reaches the appropriate confirmed commercial state, the system MUST retain the agreed price independently of later changes to standard pricing.

## BOOK-008 — Booking Reference

Every booking MUST have a unique customer/administrative reference.

## BOOK-009 — Booking Notes

The system SHOULD support customer-visible and internal booking notes with appropriate access controls.

---

# 11. Provisional Reservations and Pre-Bookings

## HOLD-001 — Provisional Reservation

The system MUST support temporarily reserving availability before final booking confirmation.

## HOLD-002 — Conflict Participation

A valid provisional reservation MUST prevent conflicting reservations/bookings for the held resource while the hold remains active.

## HOLD-003 — Expiry

Provisional reservations MUST support an expiry or release mechanism.

## HOLD-004 — Configurable Expiry

The default provisional reservation period MUST be configurable.

The initial demonstration value is TBD.

## HOLD-005 — Manager Pre-Booking

Managers MUST be able to provisionally reserve future dates for a customer or organisation.

## HOLD-006 — Pre-Booking Deadline

A manager-created future pre-booking SHOULD support a customer confirmation deadline.

## HOLD-007 — Release

Expired, declined or manually released pre-bookings MUST return the affected capacity to availability unless another restriction applies.

---

# 12. Management Approval

## APPROVAL-001 — Approval Required

New customer booking requests MUST require management approval before final confirmation.

## APPROVAL-002 — Manager Review

Managers MUST be able to review pending booking requests.

## APPROVAL-003 — Approve

An authorised manager MUST be able to approve an eligible booking request.

## APPROVAL-004 — Reject

An authorised manager MUST be able to reject a booking request.

## APPROVAL-005 — Rejection Reason

A rejection MUST support recording a reason.

## APPROVAL-006 — Revalidation

Availability and relevant resource constraints MUST be revalidated when management approval could transition a booking into a stronger reservation state.

## APPROVAL-007 — Approval Audit

The system MUST record who approved or rejected a booking and when.

## APPROVAL-008 — Management Discretion

The system MUST allow management to reject an otherwise available request for legitimate operational reasons.

---

# 13. Recurring and Block Bookings

## RECUR-001 — Recurring Request

Customers MUST be able to request a recurring series of bookings.

## RECUR-002 — Occurrence Generation

The system MUST represent the individual dates/occurrences belonging to a recurring booking series.

## RECUR-003 — Per-Occurrence Validation

Availability MUST be validated for every occurrence.

## RECUR-004 — Conflict Identification

The system MUST identify occurrences that conflict with existing availability restrictions.

## RECUR-005 — Partial Conflict Handling

A conflict affecting one occurrence MUST NOT automatically require rejection of every other valid occurrence.

## RECUR-006 — Individual Occurrence Changes

The system MUST support amendment or cancellation of an individual occurrence without necessarily changing the entire series.

Which user roles may perform these actions, and whether management approval is required following an amendment, will be defined by the applicable booking and authorization rules.

## RECUR-007 — Series Changes

Authorised users SHOULD be able to amend future occurrences in a series subject to availability revalidation.

---

# 14. Manual and Staff-Created Bookings

## MANUAL-001 — Manager Booking

Managers MUST be able to create bookings on behalf of customers.

## MANUAL-002 — Availability Validation

Manual bookings MUST use the same conflict-prevention rules as customer-created bookings.

## MANUAL-003 — Override Control

Where an authorised override is supported, the reason and responsible user MUST be recorded.

## MANUAL-004 — Walk-In Booking

The system MAY support authorised staff recording walk-in facility use.

Walk-in behaviour requires further requirements definition before implementation.

---

# 15. Pricing

## PRICE-001 — Resource Pricing

Managers MUST be able to configure prices for bookable resources.

## PRICE-002 — Centre Variation

Pricing MUST be capable of varying between centres.

## PRICE-003 — Resource Variation

Pricing MUST be capable of varying between facilities/resources.

## PRICE-004 — Customer/Booking Variation

The pricing model SHOULD support different rates according to configured customer or booking categories.

## PRICE-005 — Discounts

The system SHOULD support discounts or negotiated pricing.

## PRICE-006 — Manager Price Override

An authorised manager MUST be able to override a calculated price where permitted.

## PRICE-007 — Override Reason

A manual price override MUST record:

- the original price;
- the adjusted price;
- the reason;
- the responsible user;
- the time of adjustment.

## PRICE-008 — Event Pricing

The system SHOULD support manually agreed pricing for exceptional/large events.

## PRICE-009 — Demonstration Prices

Initial seed/demo prices MAY use fictional realistic values.

They MUST NOT be represented as verified historical prices.

---

# 16. Payments

## PAY-001 — Online Card Payment

The system MUST support online payment for eligible bookings.

## PAY-002 — Payment Provider

Online payment MUST be handled through an external payment provider rather than storing raw card details within the application.

## PAY-003 — Payment Association

Every payment MUST be associated with the relevant booking, invoice or financial obligation.

## PAY-004 — Automatic Recording

Successful online payments MUST automatically update the corresponding financial record.

## PAY-005 — Payment Status

The system MUST track payment status independently from booking status.

## PAY-006 — Manager Approval Before Standard Payment

For the standard booking workflow, the customer SHOULD normally be asked to pay after management approval rather than before it.

## PAY-006A — Approval Does Not Always Equal Confirmation

Management approval MUST NOT automatically imply that a standard card-paying booking is fully confirmed where payment is still required.

For the standard card workflow:

Request  
→ Provisional Hold  
→ Manager Approval  
→ Awaiting Payment  
→ Payment Successful  
→ Confirmed

Invoice-approved customers may follow a different confirmation workflow according to their authorised billing arrangement.

## PAY-007 — Payment Deadline

Approved bookings requiring card payment MUST support a configurable payment deadline.

## PAY-008 — Payment Expiry

Where configured policy permits, failure to pay before the deadline MUST allow the associated reservation to be released.

## PAY-009 — Offline Payment Recording

Managers SHOULD be able to record authorised offline payments for operational/admin purposes.

## PAY-010 — Refund Record

Refunds MUST be recorded against the relevant payment/booking.

---

# 17. Invoicing

## INV-001 — Invoice Billing

The system MUST support invoice-based payment arrangements for authorised customers/organisations.

## INV-002 — Billing Eligibility

Invoice billing MUST only be available where the customer/organisation is authorised for it.

## INV-003 — Multiple Bookings

An invoice SHOULD be capable of covering multiple booking occurrences.

## INV-004 — Invoice Due Date

Invoices MUST support a due date.

## INV-005 — Invoice Status

The system MUST track invoice status.

## INV-006 — Invoice Payment

Payments MUST be capable of being allocated to the appropriate invoice.

## INV-007 — Documents

Downloadable invoice documents SHOULD be supported.

## INV-008 — Statements

Monthly/customer account statements MAY be introduced after the MVP.

---

# 18. Deposits

## DEP-001 — Booking Deposit

The system SHOULD support deposits for booking types that require them.

## DEP-002 — Configurable Deposit

The deposit amount or calculation MUST be configurable where deposits are enabled.

## DEP-003 — Deposit Status

Deposit payment status MUST be tracked separately from any remaining balance.

## DEP-004 — Event Confirmation

A large/event booking MAY require a deposit before becoming fully confirmed.

---

# 19. Cancellations

## CANCEL-001 — Customer Cancellation

Customers MUST be able to request/carry out cancellation according to the applicable booking policy.

## CANCEL-002 — Manager Cancellation

Managers MUST be able to cancel bookings.

## CANCEL-003 — Configurable Policy

Cancellation notice periods and financial consequences MUST NOT be hard-coded globally.

## CANCEL-004 — Late Cancellation

The system MUST support policies where a late cancellation remains chargeable.

## CANCEL-005 — Waiver

An authorised manager MUST be able to waive a cancellation charge where policy allows.

## CANCEL-006 — Waiver Audit

A cancellation-charge waiver MUST record the reason and responsible manager.

## CANCEL-007 — Recurring Occurrence

An individual occurrence within a recurring series MUST be cancellable independently where policy permits.

---

# 20. No-Shows and Attendance

## ATTEND-001 — Arrival

Authorised operational staff MUST be able to record that a customer has arrived.

## ATTEND-002 — No-Show

Authorised operational staff MUST be able to record a no-show.

## ATTEND-003 — Completion

Authorised operational staff MUST be able to mark a booking as operationally completed.

## ATTEND-004 — No-Show Charging

The system MUST support booking policies where a no-show remains chargeable.

## ATTEND-005 — Financial Separation

Recording a no-show MUST NOT automatically imply a refund or cancellation of the financial obligation.

---

# 21. Booking Overruns

## OVERRUN-001 — Report Overrun

Authorised staff SHOULD be able to record that a customer exceeded their booked time.

## OVERRUN-002 — Overrun Duration

The system SHOULD support recording the actual or estimated overrun duration.

## OVERRUN-003 — Manager Review

An overrun SHOULD be reviewable by management.

## OVERRUN-004 — Additional Charge

Management MAY apply an additional charge for an overrun.

The system SHOULD NOT automatically charge a customer solely because an overrun was reported.

---

# 22. Closures and Availability Blocks

## CLOSE-001 — Centre Closure

Managers MUST be able to block an entire centre for a defined period.

## CLOSE-002 — Facility Closure

Managers MUST be able to block an individual facility.

## CLOSE-003 — Resource Closure

Managers MUST be able to block an individual resource.

## CLOSE-004 — Closure Reason

A closure/block MUST support recording a reason.

## CLOSE-005 — Affected Bookings

The system MUST identify existing bookings affected by a newly created closure.

## CLOSE-006 — Customer Communication

Managers MUST be able to determine which customers require notification because of a closure.

## CLOSE-007 — Rescheduling

Affected bookings SHOULD be capable of being rescheduled subject to availability.

## CLOSE-008 — Alternative Centre/Resource

Managers SHOULD be able to move an affected booking to another suitable centre/resource subject to availability and customer agreement.

## CLOSE-009 — Financial Adjustment

The system MUST allow management to identify the financial action required for a booking affected by a closure.

Where the relevant financial capability is implemented, the system SHOULD support appropriate refund, credit or invoice adjustment workflows.

## CLOSE-010 — Reopening

Removing/ending a closure MUST make otherwise valid availability bookable again.

---

# 23. Operational Staff Schedule

## OPS-001 — Today's Schedule

Leisure assistants MUST have access to a current centre-specific schedule.

## OPS-002 — Schedule Information

The operational schedule MUST provide sufficient information to prepare and deliver each booking.

This should include relevant:

- booking time;
- facility/resource;
- activity;
- equipment;
- setup information;
- status;
- operational notes.

## OPS-003 — Current Information

Changes to bookings MUST be reflected in the operational schedule without requiring a separately maintained paper timetable.

## OPS-004 — Staff Privacy

Operational staff MUST only be shown customer information necessary to perform their duties.

## OPS-005 — Multi-Centre Cover

Authorised staff MUST be able to access the operational schedule for another centre when assigned/authorised to cover it.

---

# 24. Incidents and Damage

## INCIDENT-001 — Incident Recording

Authorised staff SHOULD be able to record an operational incident associated with a booking.

## INCIDENT-002 — Incident Context

An incident SHOULD be associated with relevant:

- centre;
- booking;
- date/time;
- reporting staff member.

## DAMAGE-001 — Damage Recording

Authorised staff SHOULD be able to record equipment/facility damage associated with a booking.

## DAMAGE-002 — Responsibility

Management SHOULD be able to record responsibility where determined.

## DAMAGE-003 — Financial Follow-Up

Damage records SHOULD support later management action where a customer may be charged.

Detailed safeguarding/compliance requirements remain outside the initial MVP.

---

# 25. Notifications

## NOTIFY-001 — Booking Request

The customer MUST receive confirmation that a booking request has been received.

## NOTIFY-002 — Approval

The customer MUST be notified when a booking is approved.

## NOTIFY-003 — Rejection

The customer MUST be notified when a booking is rejected.

## NOTIFY-004 — Payment Required

A customer MUST be notified when payment is required.

## NOTIFY-005 — Payment Confirmation

The customer SHOULD receive confirmation following successful payment.

## NOTIFY-006 — Booking Confirmation

The customer MUST receive confirmation when the booking becomes confirmed.

## NOTIFY-007 — Cancellation

Relevant users MUST be notified when a booking is cancelled.

## NOTIFY-008 — Closure

Customers MUST be notified when a closure materially affects their booking.

## NOTIFY-009 — Reminder

The system SHOULD support booking reminders.

## NOTIFY-010 — Channels

The MVP SHOULD support:

- email;
- in-application notifications.

SMS is not required for the MVP.

---

# 26. Customer Booking Management

## CUSTOMER-001 — View Bookings

Customers MUST be able to view their bookings.

## CUSTOMER-002 — Booking Details

Customers MUST be able to view relevant booking details and status.

## CUSTOMER-003 — Financial Status

Customers MUST be able to see whether payment or another financial action is required.

## CUSTOMER-004 — Cancellation

Customers MUST be able to initiate eligible cancellations.

## CUSTOMER-005 — Recurring Series

Customers SHOULD be able to view the occurrences belonging to their recurring booking.

## CUSTOMER-006 — Documents

Customers SHOULD be able to access relevant booking/invoice documents.

---

# 27. Search and Alternatives

## SEARCH-001 — Centre Search

Customers MUST be able to browse/search facilities by centre.

## SEARCH-002 — Activity Search

Customers SHOULD be able to discover facilities suitable for an activity.

## SEARCH-003 — Date Availability

Customers MUST be able to search for availability for a date/time.

## SEARCH-004 — Alternative Availability

The system SHOULD eventually suggest suitable alternative availability when the requested period cannot be booked.

## SEARCH-005 — Deterministic Alternatives

Alternative availability MUST NOT require artificial intelligence.

---

# 28. Reporting

## REPORT-001 — Booking Reporting

Managers SHOULD have access to booking-volume reporting.

## REPORT-002 — Revenue Reporting

Managers SHOULD be able to report revenue by relevant dimensions such as centre and facility.

## REPORT-003 — Utilisation

Managers SHOULD be able to analyse facility utilisation.

## REPORT-004 — Cancellation and No-Show Reporting

Managers SHOULD be able to report on cancellations and no-shows.

## REPORT-005 — Outstanding Financials

Managers SHOULD be able to identify outstanding invoices/payments.

Advanced analytics are not required for the initial MVP.

---

# 29. Audit Trail

## AUDIT-001 — Booking Changes

Important booking changes MUST be auditable.

## AUDIT-002 — Approval Actions

Approval and rejection actions MUST record the responsible user and timestamp.

## AUDIT-003 — Financial Adjustments

Manual financial adjustments MUST be auditable.

## AUDIT-004 — Price Overrides

Price overrides MUST be auditable.

## AUDIT-005 — Cancellation Waivers

Cancellation-charge waivers MUST be auditable.

## AUDIT-006 — Closure Actions

Important closure/block actions SHOULD be auditable.

## AUDIT-007 — Audit Integrity

Ordinary application users MUST NOT be able to alter historical audit records.

---

# 30. Security and Privacy

## SEC-001 — Authentication

Protected functionality MUST require authentication.

## SEC-002 — Authorization

Every protected operation MUST enforce server-side authorization.

## SEC-003 — Ownership Protection

Customers MUST NOT be able to access another customer's private bookings, invoices, payments or documents by manipulating identifiers or URLs.

## SEC-004 — Organisation Isolation

Organisation data MUST only be accessible to authorised members and relevant staff.

## SEC-005 — Payment Data

The application MUST NOT store raw payment-card details.

## SEC-006 — Sensitive Data Exposure

Public availability responses MUST NOT reveal private booking/customer information.

## SEC-007 — Validation

All externally supplied data MUST be validated before being trusted.

## SEC-008 — CSRF/Session Protection

The application MUST use appropriate protections for authenticated web interactions.

## SEC-009 — Secrets

Application secrets and credentials MUST NOT be committed to the public repository.

## SEC-010 — Demonstration Data

Public/demo environments MUST use fictional customer and financial data.

---

# 31. Data Integrity and Concurrency

## DATA-001 — Referential Integrity

Core booking data MUST maintain valid relationships between centres, facilities, resources, customers and bookings.

## DATA-002 — Historical Preservation

Deleting/configuring operational records MUST NOT destroy required booking/financial history.

## DATA-003 — Booking Concurrency

The booking architecture MUST protect against race conditions that could otherwise create conflicting reservations.

## DATA-004 — Transactional Consistency

Operations that change availability and booking state MUST use appropriate transactional consistency.

## DATA-005 — Idempotency

Externally retried operations, particularly payment-related operations, MUST be designed to avoid duplicate financial effects.

---

# 32. Accessibility

## ACCESS-001 — Accessibility Target

The customer-facing and staff-facing web application SHOULD target WCAG 2.2 AA.

## ACCESS-002 — Keyboard Operation

Core booking and administrative workflows SHOULD be operable using a keyboard.

## ACCESS-003 — Form Accessibility

Forms MUST provide accessible labels, validation feedback and error identification.

## ACCESS-004 — Status Communication

Important booking/payment statuses MUST NOT rely solely on colour.

---

# 33. Responsive Behaviour

## RESP-001 — Customer Mobile Support

Core customer workflows MUST be usable on common mobile screen sizes.

## RESP-002 — Staff Mobile Support

Today's Schedule and operational booking details MUST be practical to use on a phone-sized device.

## RESP-003 — Management Responsive Support

Core management workflows SHOULD remain usable on tablet and smaller laptop displays.

Detailed responsive layouts will be defined in `design.md`.

---

# 34. Performance

## PERF-001 — Availability Query Performance

Availability searches SHOULD return within a reasonable interactive response time under expected demonstration/portfolio load.

## PERF-002 — Avoid Unbounded Queries

The application MUST avoid unbounded retrieval of large booking histories in normal UI workflows.

## PERF-003 — Background Processing

Long-running non-interactive work SHOULD be processed asynchronously where appropriate.

Examples may include:

- email;
- document generation;
- reporting exports;
- external integration processing.

Specific performance targets will be defined during architecture where useful.

---

# 35. Reliability

## REL-001 — Payment Webhooks

Payment-provider events MUST be processed safely and idempotently.

## REL-002 — Notification Failure

A failed notification MUST NOT corrupt or incorrectly change the underlying booking state.

## REL-003 — External Provider Failure

Temporary failure of an external service SHOULD fail gracefully and preserve recoverable application state.

## REL-004 — Booking Integrity

Failure during a multi-step booking transition MUST NOT leave availability and booking state inconsistent.

---

# 36. Observability

## OBS-001 — Application Logging

The system MUST log unexpected application errors.

## OBS-002 — Payment Failures

Payment integration failures MUST be diagnosable through appropriate logs/provider references.

## OBS-003 — Background Job Failure

Failed asynchronous jobs SHOULD be visible to administrators/developers.

## OBS-004 — Audit vs Technical Logs

Business audit history and technical application logging MUST be treated as separate concerns.

---

# 37. Demonstration Environment

## DEMO-001 — Seed Data

The application MUST provide realistic fictional demonstration data.

## DEMO-002 — Multiple Centres

Demo data MUST demonstrate multiple centres with differing facilities/resources.

## DEMO-003 — Resource Subdivision

Demo data MUST demonstrate at least one parent facility containing independently bookable child resources.

## DEMO-004 — Booking States

Demo data SHOULD demonstrate multiple booking states.

## DEMO-005 — Demo Roles

The demonstration environment SHOULD provide a practical way to evaluate customer, manager and leisure-assistant workflows.

## DEMO-006 — No Historical PII

Historical customer or employee personally identifiable information MUST NOT be used as demo data.

---

# 38. Explicitly Deferred Capabilities

The following capabilities are not required for the initial MVP unless later promoted through an explicit requirements change:

- native mobile applications;
- SMS notifications;
- advanced AI assistants;
- AI-dependent availability recommendations;
- advanced waiting-list automation;
- sophisticated accounting integrations;
- advanced analytics;
- enterprise-grade configurable RBAC;
- complex promotional pricing;
- advanced compliance-document workflows;
- sophisticated event-management tooling;
- external calendar integrations.

---

# 39. Open Product Decisions

The following decisions remain intentionally open and must be resolved before the affected feature is implemented:

### DEC-001 — Demonstration Pricing

Determine realistic fictional seed prices or use approximate historical values where appropriate and safe.

### DEC-002 — Default Provisional Hold Duration

Determine the default management-review hold duration.

Candidate starting value: 48 hours.

### DEC-003 — Payment Deadline

Determine the default period an approved card-paying booking remains reserved while awaiting payment.

Candidate starting value: 24 hours.

### DEC-004 — Cancellation Policies

Define the initial configurable cancellation policy used by demonstration centres.

### DEC-005 — Organisation Permissions

Define the first set of organisation roles and permissions.

### DEC-006 — Event Workflow

Determine how much exceptional-event functionality belongs in MVP versus a later phase.

### DEC-007 — Opening Hours Seed Data

Define realistic demonstration opening and bookable hours for each centre/facility.

### DEC-008 — Equipment Seed Data

Define demonstration equipment inventories.

---

# 40. MVP Scope Principle

The initial MVP is intended to prove the core end-to-end business workflow rather than implement every requirement in this specification simultaneously.

The MVP should prioritise:

- customer authentication and profile;
- centre/facility/resource browsing;
- calculated availability;
- parent/child resource conflict prevention;
- one-off booking requests;
- provisional reservation;
- management approval/rejection;
- basic equipment allocation;
- online card payment;
- basic authorised invoice flow;
- booking confirmation;
- manager booking administration;
- centre-specific Today's Schedule;
- arrival, completion and no-show recording;
- essential email/in-application notifications;
- essential authorization, auditability and booking concurrency protection.

Capabilities marked `MUST` elsewhere in this specification remain requirements for the capability when implemented, but do not automatically require implementation in the first MVP milestone.

Later implementation planning will divide the MVP into smaller milestones so that the system can be developed and validated incrementally.

---

# 41. MVP Acceptance Outcome

The MVP will be considered functionally successful when the following end-to-end scenario can be demonstrated:

1. A customer registers and signs in.
2. The customer browses a centre and facility.
3. The customer views genuine calculated availability.
4. The customer selects an available resource/date/time.
5. The system validates that the resource is available.
6. The customer submits a booking request.
7. The resource becomes unavailable to conflicting requests while appropriately held.
8. A manager sees the pending request.
9. The manager reviews and approves it.
10. The customer receives the appropriate notification.
11. The customer completes the required payment, or an authorised invoice arrangement is applied.
12. The booking becomes confirmed.
13. The confirmed booking appears in the appropriate centre's operational schedule.
14. A leisure assistant can view the booking and equipment/setup requirements.
15. The leisure assistant records arrival/completion or no-show.
16. Management can subsequently view the booking and financial record.
17. Another customer cannot create a conflicting booking at any point where the resource should be unavailable.

This end-to-end workflow is more important to the MVP than implementing every secondary feature described elsewhere in this document.

---

# 42. Requirements Change Control

New ideas discovered during design or implementation MUST NOT automatically become MVP requirements.

A proposed requirement should be evaluated against:

- the business problem;
- discovery evidence;
- user value;
- MVP scope;
- implementation complexity;
- impact on existing requirements.

Material requirement changes should update this document before implementation so that the requirements remain the source of truth.

---

# 43. Traceability

Requirements should be referenced from later project documentation where useful.

Example:

`BOOK-001`
→ design flow
→ architecture/domain behaviour
→ implementation task
→ automated feature test

This provides traceability from business need through implementation and verification.

---

# 44. Next Step

Once this requirements document has been reviewed and approved, the next stage is UX/design discovery.

That stage will determine how customers, managers and leisure assistants interact with the required capabilities before implementation architecture is finalised.