# Facility Booking Platform — Project Discovery

## 1. Document Purpose

This document records the discovery work undertaken for the Facility Booking Platform.

The project is inspired by first-hand experience of working within a multi-site community leisure facility operation in Sheffield, UK. Additional discovery has been carried out using historical operational documents and discussions with a former manager who worked within the operation.

The purpose of this document is to capture:

- the historical business process;
- the problems and operational inefficiencies identified;
- the needs of customers, managers and on-site staff;
- relevant historical business rules;
- opportunities for modernisation;
- assumptions and unresolved details;
- the initial direction of the proposed replacement system.

This document describes the business problem and discovery findings. Detailed software requirements, UX decisions and technical architecture will be maintained separately.

---

## 2. Project Context

The historical operation managed the commercial hire of facilities located across multiple school and community sites.

Facilities were made available outside periods required by the schools and could be hired by individuals, clubs, organisations and other groups.

Typical uses included:

- football;
- badminton;
- basketball;
- volleyball;
- tennis;
- hockey;
- fitness and sporting activities;
- clubs and recurring activities;
- tournaments;
- meetings;
- conferences;
- classes;
- performances;
- private events;
- weddings;
- children's holiday activities;
- other community events.

The operation therefore involved considerably more than simple sports pitch booking.

It required coordination of:

- multiple centres;
- multiple facilities within each centre;
- subdividable resources;
- equipment;
- availability;
- recurring bookings;
- customer organisations;
- pricing;
- booking approval;
- payments;
- invoicing;
- deposits;
- cancellations;
- closures;
- staff operations;
- reporting.

The proposed product is therefore described as a:

> Multi-site facility booking and operations platform.

---

## 3. Discovery Sources

Discovery has been informed by several sources.

### 3.1 First-hand operational experience

The project creator previously worked as a leisure assistant within the historical operation.

This provides direct knowledge of:

- site operations;
- customer arrival;
- equipment setup;
- facility preparation;
- booking schedules;
- payment collection;
- communication between management and leisure assistants;
- cancellations and booking changes;
- operational issues occurring during shifts.

### 3.2 Former management interview

A former manager was interviewed to understand processes that were not always visible to leisure assistants.

Topics included:

- booking administration;
- Excel usage;
- availability;
- manager approval;
- pricing;
- payments;
- invoicing;
- pre-bookings;
- recurring bookings;
- equipment;
- cancellations;
- staff communication;
- operational problems;
- desired improvements.

### 3.3 Historical documentation

Historical documents were reviewed, including:

- application-for-hire forms;
- facility timetables;
- booking confirmations;
- terms and conditions;
- weekend staff schedules;
- operational communications.

These documents provide supporting evidence for historical processes and business rules.

### 3.4 Product design discussions

Where the historical process is unsuitable for a modern application, proposed replacement behaviour is documented separately as a product decision rather than being presented as historical fact.

---

## 4. Historical Sites

The historical operation covered four primary centres:

- Fir Vale;
- Ecclesfield;
- Tapton;
- King Edward VII.

The centres followed broadly similar booking processes but did not necessarily contain identical facilities, equipment, opening hours or prices.

The replacement system should therefore treat each centre as independently configurable while allowing them to be managed within the same organisation.

---

## 5. Facilities

Facilities identified during discovery include:

- Astro Turf / 3G-style pitches;
- Sports Halls;
- Gymnasiums;
- Hard Courts;
- Classrooms;
- Conference / meeting spaces;
- performance and event spaces.

Ecclesfield also included a large hall used for activities such as performances, dance, drama and private gatherings.

Individual centres may contain different combinations of facilities.

Facilities may also have:

- different capacities;
- different opening hours;
- different prices;
- different equipment;
- different booking rules.

---

## 6. Facility Subdivision

Some facilities can be divided into smaller independently bookable resources.

Examples include:

### Astro Turf

An Astro Turf could be divided into multiple pitches/sections.

Multiple teams could therefore use different sections simultaneously.

The entire Astro Turf could also potentially be hired as a whole.

### Sports Hall

A Sports Hall could be divided into badminton courts or other sections.

Customers could hire individual courts or the entire hall depending on the activity.

### Gymnasium

A Gymnasium could contain multiple badminton courts.

### Hard Courts

Hard Courts could contain multiple tennis courts.

This creates an important availability rule for the replacement system.

A booking for an individual child resource must prevent conflicting use of that resource, while a booking for the entire parent facility must prevent bookings for all resources contained within it.

Similarly, existing child-resource bookings must prevent an exclusive whole-facility booking covering the same period.

---

## 7. Equipment

Facilities could require equipment to be prepared by leisure assistants.

Examples identified during discovery include:

- five-a-side football goals;
- Astro Turf football goals;
- hockey goals;
- badminton nets;
- tennis nets;
- volleyball nets;
- basketball equipment.

Approximate quantities varied by facility.

Examples remembered during discovery include:

- approximately two five-a-side goals;
- approximately two Astro Turf goals;
- approximately two basketball goals;
- approximately four badminton nets within larger Sports Halls;
- approximately two badminton nets within smaller Gymnasiums.

Exact historical quantities are not required for the initial product and may be represented using realistic demonstration data.

Equipment should ultimately be associated with the relevant centre and/or facility.

The replacement system should be capable of tracking quantities so that overlapping bookings cannot allocate more equipment than is available.

---

## 8. Historical Customer Booking Process

The historical booking process relied heavily on manual administration.

A typical journey was:

1. A customer contacted the operation regarding facility hire.
2. Availability was checked manually.
3. The customer completed an application-for-hire form.
4. Management received the application.
5. Management checked availability.
6. Management checked required facilities and equipment.
7. The requested slot could be reserved/pre-booked.
8. Management approved or rejected the booking.
9. Booking information was manually entered into an Excel file.
10. Booking confirmation/information was communicated to the relevant centre.
11. Leisure assistants used booking schedules to operate the facility.
12. Payment was handled according to the customer's arrangement.
13. Payment records and money were reconciled manually where required.

This created multiple manual hand-offs between customers, management and leisure assistants.

---

## 9. Booking Administration

Excel acted as a central part of the historical booking process.

The former manager confirmed that after receiving a booking form, booking details were entered into an Excel file.

The Excel file was used to:

- record bookings;
- check availability;
- determine whether another manager had already booked a slot;
- maintain operational booking information.

Manual data entry into Excel was specifically identified by the former manager as a repetitive task they would have preferred to automate.

Occasional double bookings occurred when information was missed or not correctly reflected within the process.

The replacement system should therefore use a single authoritative booking database rather than relying on manually synchronised records.

---

## 10. Availability

Availability was not simply determined by whether another customer had booked a facility.

A resource could be unavailable because of:

- an existing booking;
- a provisional/pre-booking;
- school use;
- examinations;
- school events;
- maintenance;
- health and safety restrictions;
- holidays;
- weather;
- temporary closures;
- management blocks;
- other operational reasons.

Conceptually:

> Bookable Availability = Opening Hours - Bookings - Holds - School Use - Closures - Maintenance - Management Blocks

Managers need the ability to manually block periods from customer booking.

Public users should be able to see whether a resource is available without seeing private information about the customer who has booked it.

---

## 11. Opening Hours

Opening hours varied depending on the centre and operational period.

Historical recollections indicate that hours could differ between:

- school term time;
- weekends;
- school holidays;
- exceptional bookings;
- special events.

School daytime use generally prevented normal public hire during those periods.

Exceptional bookings could occur outside normal public opening hours.

Exact historical opening hours are not required for the initial implementation.

The replacement system should therefore support configurable opening hours rather than hard-coded assumptions.

---

## 12. Booking Slots

Facilities were generally booked using defined time slots.

Historical timetables show that slot durations could vary by facility and day.

Customers could book consecutive slots where appropriate.

Different facilities may require different:

- slot durations;
- minimum booking durations;
- booking increments.

The replacement system should therefore avoid assuming that every resource uses one universal booking duration.

---

## 13. Pre-Bookings

Pre-booking historically allowed future dates to be reserved before another customer could take them.

This was particularly relevant when customers or organisations needed to secure future facility usage.

The replacement system should preserve this capability while making provisional status explicit.

### Proposed modern behaviour

A provisional booking should temporarily prevent conflicting bookings.

Different forms of provisional reservation may exist.

#### Customer booking request

When a customer submits a valid request, the resource may be temporarily reserved while management reviews the request.

#### Future/block pre-booking

Management may provisionally reserve future dates for an established customer or organisation.

A confirmation deadline may be assigned.

If the customer accepts, the booking progresses towards confirmation.

If the customer declines or the reservation expires, the dates return to general availability.

Exact expiry periods should be configurable.

---

## 14. Recurring and Block Bookings

Recurring bookings were an important part of the historical operation.

Examples include clubs or organisations hiring the same facility each week over a period of months.

Recurring bookings may contain:

- multiple dates;
- the same resource;
- the same time;
- repeated equipment requirements;
- agreed pricing arrangements.

Individual occurrences may need to be:

- cancelled;
- moved;
- rescheduled;
- excluded from the series.

If a particular recurring date is unavailable, management should be able to identify the conflict and offer an alternative rather than rejecting the entire series unnecessarily.

The replacement system should validate every occurrence within a recurring booking.

---

## 15. Multiple Resources

A single customer booking could involve multiple facilities or resources.

This is particularly relevant for:

- tournaments;
- large events;
- weddings;
- whole-centre hire;
- activities requiring multiple rooms or pitches.

The domain model must therefore avoid assuming that one booking always represents exactly one resource.

---

## 16. Management Approval

New bookings historically required management approval.

Management considered factors including:

- availability;
- equipment requirements;
- operational suitability;
- health and safety;
- customer history;
- booking type.

An available slot did not automatically mean that the booking had to be accepted.

For example, management could refuse a booking because of health and safety work within the building.

The former manager confirmed that even if customers could view availability and request/pay online, management would still need control over confirming bookings.

Manager approval is therefore a core requirement of the replacement product.

---

## 17. Trusted and Regular Customers

Long-standing regular customers could be treated differently because an established relationship and level of trust existed.

This may influence:

- booking administration;
- payment arrangements;
- invoicing;
- pre-booking;
- negotiated pricing.

The modern system should eventually support customer-specific commercial arrangements without bypassing core availability and audit controls.

---

## 18. Organisations

Customers should initially be able to register as individuals.

An individual may subsequently create or belong to an organisation.

Examples include:

- sports clubs;
- community groups;
- companies;
- charities;
- schools;
- event organisations.

Organisations should eventually be able to invite additional users.

Potential organisation permissions include:

- Owner;
- Administrator;
- Booking Manager;
- Finance;
- Member.

Exact role permissions will be defined during requirements and architecture design.

---

## 19. Pricing

Historical pricing varied.

Factors included:

- centre;
- facility;
- resource;
- activity;
- customer type;
- booking duration;
- frequency of use;
- negotiated agreements;
- event type.

Different centres could charge different amounts for similar activities.

Some activities had broadly consistent pricing while others, such as football, could vary between centres.

Block booking pricing could depend on the duration and frequency of the arrangement.

Discounts could be negotiated, including arrangements based on frequent facility use.

Large events such as weddings, tournaments and meetings could use different pricing arrangements.

### Equipment pricing

Discovery indicates that equipment could affect pricing, although whether equipment was always represented as a separate charge is unclear.

The modern pricing model should therefore be flexible enough to support included equipment and separately chargeable equipment.

### Demonstration pricing

Exact historical prices are not required for development.

Where accurate historical prices are unavailable, realistic fictional prices will be used as demonstration data.

These prices must not be presented as historical Active8 pricing.

---

## 20. Proposed Pricing Model

The modern system should eventually be capable of calculating an agreed price from components such as:

> Base Resource Price
> + Optional Equipment Charges
> - Applicable Discount
> + Manager Adjustment  
    > = Agreed Booking Price

Manual price overrides should require a reason and should be recorded in the audit history.

Detailed pricing rules will be defined in the requirements and architecture documents.

---

## 21. Historical Payments

Historical payment methods primarily included:

- cash;
- cheque;
- invoicing where permitted.

Payment timing varied depending on the customer and booking arrangement.

Payment could occur:

- in advance;
- before facility use;
- on arrival;
- after use under an agreed arrangement;
- through invoicing.

Some regular bookings involved customers collecting money from participants before paying for facility hire.

Large bookings could require deposits.

Damage/security deposits could form part of the booking deposit arrangement.

---

## 22. On-Site Cash Handling

Leisure assistants could accept cash or cheque payments from customers.

The leisure assistant would:

1. receive payment;
2. issue a receipt;
3. mark the booking/payment as paid on paper;
4. place the money in the centre safe.

Management periodically travelled to the centres to collect the money.

This created administrative work and physical cash-handling requirements.

---

## 23. Online Payments

The former manager identified the inability to take online payments as the biggest management headache.

When asked what an online replacement should provide, online payment was one of the primary requested capabilities.

Online payment therefore represents a core business improvement rather than simply a technical modernisation.

The proposed system should support online card payment through a payment provider such as Stripe.

This can reduce:

- cash handling;
- physical money collection;
- manual payment recording;
- manual receipt administration;
- reconciliation effort.

---

## 24. Invoicing

Invoices were used historically.

An invoice could cover multiple sessions or bookings.

Historical documentation indicates that invoicing could be permitted at management discretion.

The modern system should therefore distinguish between customers who must pay directly and organisations approved for invoice-based billing.

Potential future capabilities include:

- multiple bookings per invoice;
- invoice due dates;
- payment status;
- downloadable invoices;
- monthly statements;
- overdue balances;
- account credit.

---

## 25. Proposed Payment Workflow

Payment behaviour should depend on the booking/customer type.

### Standard card booking

Proposed flow:

1. Customer requests booking.
2. Resource is provisionally held.
3. Manager approves booking.
4. Customer is asked to pay.
5. Payment succeeds.
6. Booking becomes confirmed.

This avoids taking payment for a booking management may subsequently reject.

### Invoice-approved organisation

Proposed flow:

1. Organisation requests booking.
2. Manager approves booking.
3. Booking is confirmed under agreed invoice terms.
4. Invoice is issued according to the organisation's billing arrangement.

### Large event

Proposed flow:

1. Customer submits event request.
2. Management reviews requirements.
3. Price is agreed.
4. Deposit is requested.
5. Deposit is paid.
6. Booking is confirmed.
7. Remaining balance is due according to agreed terms.

---

## 26. Payment and Approval Deadlines

Resources should not remain reserved indefinitely.

### Proposed modern rules

A normal booking request may be held for a configurable management review period.

An initial demonstration value may be approximately 48 hours.

Once approved, a card-paying customer may receive a configurable payment deadline.

An initial demonstration value may be approximately 24 hours.

If required action is not completed, the system should be capable of releasing the resource according to configured policy.

Large events and special arrangements may use manager-defined deadlines.

These values are product decisions and are not historical business rules.

---

## 27. Cancellations

Historical cancellation rules varied across documentation and recollection.

Historical documents contain different notice periods in different contexts.

Late cancellations could still be charged.

No-shows could also be charged.

Managers could waive charges depending on circumstances.

Because historical rules were not completely uniform, the replacement system should not hard-code one universal cancellation period.

Cancellation policy should instead be configurable.

---

## 28. Organisation-Initiated Cancellations

If the facility operator or school had to cancel a booking, management would contact the customer and attempt to rearrange it.

Possible outcomes included:

- moving to another date;
- moving to another centre;
- refunding money where appropriate;
- adjusting an invoice.

The modern system should help management identify all bookings affected by a closure and manage the resulting customer communication and financial adjustment.

---

## 29. No-Shows

Customers who failed to attend could still be charged.

Leisure assistants should be able to record attendance status.

Potential operational statuses include:

- expected;
- arrived;
- completed;
- no-show.

Financial treatment of a no-show should remain subject to the applicable booking policy and management discretion.

---

## 30. Booking Overruns

Customers occasionally remained beyond their allocated booking period.

Historically, the leisure assistant would ask the customer to leave.

An additional charge could potentially be applied.

### Proposed modern behaviour

Leisure assistants should be able to report an overrun.

Management can then decide whether an additional charge should be applied.

Automatic charging is not proposed initially because management historically exercised discretion.

---

## 31. Large and Exceptional Events

The operation supported bookings beyond ordinary sports sessions.

Examples include:

- weddings;
- tournaments;
- meetings;
- performances;
- conferences;
- whole-centre bookings;
- large private events.

These bookings may require:

- multiple facilities;
- custom pricing;
- deposits;
- longer durations;
- setup time;
- cleanup time;
- capacity checks;
- additional approval;
- additional documentation.

Large events should therefore not be forced through exactly the same workflow as a simple badminton-court booking.

---

## 32. Setup and Cleanup Time

Some bookings require operational time before or after the customer-facing event.

Examples include:

- setting up sports equipment;
- preparing seating;
- event preparation;
- post-event cleanup.

The system should eventually distinguish between:

- customer booking time;
- operational occupancy time.

This prevents another booking from being scheduled during required setup or cleanup periods.

---

## 33. Leisure Assistant Operations

Leisure assistants primarily needed to know:

- what booking was taking place;
- where it was taking place;
- the booking time;
- the required equipment;
- relevant changes or cancellations.

They prepared equipment before customer use.

They also handled operational situations including:

- customer arrival;
- no-shows;
- equipment setup;
- damage;
- overruns;
- payments;
- incidents.

---

## 34. Historical Staff Communication

Leisure assistants relied on operational booking information such as printed schedules/timetable folders.

Last-minute bookings and cancellations could be communicated by text.

Booking confirmations or updated information could also be faxed to the relevant location/school.

This created a risk that the information available at a centre was not always the latest version.

---

## 35. Proposed Live Staff Schedule

A core operational improvement is a live centre-specific schedule.

A leisure assistant should be able to see today's bookings for the centre they are working at.

Information may include:

- booking time;
- customer or organisation;
- activity;
- facility/resource;
- equipment/setup requirements;
- booking status;
- payment status where appropriate;
- amendments;
- cancellations;
- operational notes.

The assistant should eventually be able to record:

- arrival;
- no-show;
- completion;
- incident;
- equipment damage;
- overrun.

This replaces static schedules and last-minute text updates with a single current source of truth.

---

## 36. Walk-In / Manual Bookings

Historically, if a customer arrived without appearing on the timetable, they might have to leave.

If capacity was genuinely available, there were situations where facility/equipment use could still be possible.

The replacement system should consider allowing authorised staff to create a manual/walk-in booking so that all facility usage remains within the central system rather than occurring off-system.

This is a proposed product capability and requires further requirements definition.

---

## 37. Closures

Facilities could become unavailable because of:

- weather;
- maintenance;
- school requirements;
- examinations;
- health and safety;
- unexpected operational issues.

Management needs the ability to close:

- an entire centre;
- an individual facility;
- an individual resource;
- a defined period.

The system should identify affected bookings.

Potential actions include:

- notifying customers;
- rescheduling;
- offering another centre;
- issuing refunds;
- adjusting invoices;
- reopening availability when the closure ends.

---

## 38. Equipment Damage

If equipment was already damaged before customer use, responsibility remained with the facility operator.

If a customer caused damage, the customer could be charged.

The modern system should eventually allow staff to record:

- damaged equipment;
- booking involved;
- description;
- responsibility where known;
- supporting notes/evidence;
- manager follow-up.

---

## 39. Incidents

Leisure assistants may need to record operational incidents associated with a booking.

Incident reporting should be linked to the relevant:

- centre;
- booking;
- customer/organisation;
- date/time.

Detailed incident and safeguarding requirements will require further research before implementation.

---

## 40. Compliance and Supporting Documents

Historical requirements varied depending on the booking.

Examples identified include:

- risk assessments in some circumstances;
- additional requirements for organisations working with children;
- evidence of insurance where management required it;
- PAT evidence for customer-supplied electrical equipment in relevant circumstances.

These requirements were not universal.

The modern system should therefore support conditional document requirements rather than requiring every customer to provide every possible document.

Advanced compliance workflows are not considered part of the initial MVP.

---

## 41. Search and Discovery

Customers should be able to discover suitable facilities without needing to telephone management.

Potential search dimensions include:

- centre;
- facility;
- activity;
- date;
- time;
- availability.

The public should only see availability information and not private booking/customer details.

---

## 42. Alternative Availability

When a requested slot is unavailable, the system should eventually help identify alternatives.

Alternatives could include:

- another time;
- another date;
- another resource;
- another facility;
- another centre.

Initial alternative matching can be deterministic.

Artificial intelligence is not required for this capability.

---

## 43. Waiting Lists

A future waiting-list capability may allow customers to register interest in unavailable periods.

If availability becomes available, eligible customers could be notified.

This is not considered essential to the first MVP.

---

## 44. Notifications

Customers and staff may require notifications for events such as:

- booking requested;
- booking approved;
- booking rejected;
- payment required;
- payment received;
- booking confirmed;
- booking reminder;
- booking amended;
- booking cancelled;
- refund issued;
- facility closure;
- booking affected by closure;
- pre-booking approaching expiry.

Initial notification channels should focus on:

- email;
- in-application notifications.

SMS may be considered later.

---

## 45. Reporting

Management should eventually be able to report on:

- revenue by centre;
- revenue by facility;
- revenue by customer/organisation;
- facility utilisation;
- busy periods;
- quiet periods;
- booking volume;
- cancellations;
- no-shows;
- outstanding invoices;
- payment status;
- equipment usage.

The purpose of reporting is to support operational and commercial decisions rather than simply provide dashboard charts.

---

## 46. Auditability

The replacement system should maintain an audit trail for important business actions.

Examples include:

- booking creation;
- booking approval;
- rejection;
- cancellation;
- resource changes;
- price overrides;
- payment adjustments;
- refunds;
- closure creation;
- manual booking changes.

This is particularly important because multiple managers or staff members may interact with the same booking.

---

## 47. Identified Business Problems

Discovery identified the following primary problems.

### Manual booking administration

Booking details had to be manually entered into Excel.

### No customer self-service availability

Customers could not independently view current availability.

### No online booking workflow

Customers relied on communication with management and manual forms.

### Limited online payment capability

Cash and cheque handling created administrative and operational overhead.

### Physical cash collection

Management periodically travelled to centres to collect money stored in safes.

### Fragmented communication

Booking changes could be communicated through paper schedules, fax or text messages.

### Risk of stale information

Centre staff could potentially work from information that was no longer current.

### Booking conflicts

Manual processes could result in missed information and occasional double bookings.

### Repetitive management work

Management repeatedly entered and checked booking information manually.

### Limited customer visibility

Customers depended heavily on staff to determine available dates and times.

---

## 48. Former Manager's Desired Improvements

When asked what they would have wanted from an online replacement system, the former manager identified three primary capabilities:

1. online payments;
2. booking/reserving dates in advance;
3. visible availability.

The inability to take online payments was identified as the biggest management headache.

Manual Excel data entry was identified as the repetitive task they most wanted automated.

Management confirmation of bookings would still have been required even with customer self-service.

These findings strongly influence the initial product direction.

---

## 49. Proposed Modernisation

The proposed system transforms the historical process from:

Customer enquiry  
→ manual availability check  
→ application form  
→ manager review  
→ Excel entry  
→ confirmation  
→ static staff schedule  
→ cash/cheque/invoice  
→ manual reconciliation

into:

Customer Portal  
→ Live Availability  
→ Booking Request / Pre-Booking  
→ Automatic Conflict Validation  
→ Manager Approval  
→ Online Payment / Invoice  
→ Confirmed Booking  
→ Live Staff Schedule  
→ Central Reporting

The objective is not merely to digitise the existing spreadsheet.

The objective is to remove unnecessary manual work while preserving management control where operational judgement is still valuable.

---

## 50. Initial User Types

The system is expected to support at least the following user groups.

### Customer

Can potentially:

- manage profile;
- browse centres;
- browse facilities;
- view availability;
- request bookings;
- create recurring requests;
- request equipment;
- make payments;
- view invoices;
- manage existing bookings.

### Organisation User

Can potentially perform customer actions on behalf of an organisation subject to organisation permissions.

### Manager

Can potentially:

- manage all centres;
- manage facilities/resources;
- manage equipment;
- manage opening hours;
- manage availability blocks;
- review booking requests;
- approve/reject bookings;
- create manual bookings;
- manage pre-bookings;
- manage pricing;
- manage payments;
- manage invoices;
- manage closures;
- manage exceptional events;
- view reports.

### Leisure Assistant

Can potentially:

- view the live schedule for their centre;
- view booking details;
- view setup/equipment requirements;
- record arrival;
- record no-show;
- mark completion;
- report incidents;
- report equipment damage;
- report booking overruns.

Detailed permissions will be defined later.

---

## 51. Initial Booking Lifecycle

The final state machine will be defined during requirements and architecture work.

An initial conceptual lifecycle is:

Available  
→ Requested / Provisionally Reserved  
→ Manager Review  
→ Approved  
→ Awaiting Payment or Account Billing  
→ Confirmed  
→ Completed

Alternative outcomes include:

- Rejected;
- Cancelled;
- Expired;
- No-show.

Financial status should be modelled separately from booking status.

For example, a booking may be confirmed while payment is handled through approved invoice terms.

---

## 52. Initial MVP Direction

The first MVP should demonstrate the complete core business workflow rather than every possible feature.

### Customer MVP

- registration and authentication;
- customer profile;
- browse centres;
- browse facilities/resources;
- view availability;
- submit one-off booking request;
- submit recurring booking request;
- request relevant equipment;
- view booking status;
- receive booking approval/rejection;
- online payment;
- invoice-based flow where applicable;
- view/manage bookings.

### Manager MVP

- manager dashboard;
- manage centres;
- manage facilities/resources;
- manage equipment;
- configure opening hours;
- configure basic prices;
- view booking requests;
- approve/reject requests;
- create manual bookings;
- create availability blocks/closures;
- view payments;
- manage basic invoices.

### Leisure Assistant MVP

- centre-specific operational view;
- Today's Schedule;
- booking details;
- equipment/setup information;
- arrival status;
- no-show status;
- completion status;
- basic incident reporting.

---

## 53. Likely Phase Two

Potential Phase Two capabilities include:

- organisation teams and richer permissions;
- advanced recurring booking management;
- monthly billing;
- deposits;
- account credit;
- advanced cancellation/refund rules;
- equipment fault/damage workflows;
- closure impact workflows;
- downloadable PDFs;
- monthly statements;
- conditional compliance documents;
- booking overrun charging;
- richer audit tooling.

---

## 54. Likely Phase Three

Potential later capabilities include:

- waiting lists;
- automatic alternative availability suggestions;
- advanced analytics;
- richer notification channels;
- sophisticated event management;
- external accounting integration;
- calendar integration;
- SMS;
- optional AI-assisted search or administrative workflows.

---

## 55. Explicit Non-MVP Areas

The initial implementation should avoid unnecessary scope expansion.

The following are not initial MVP priorities:

- native mobile applications;
- advanced AI assistants;
- complex recommendation engines;
- advanced promotional pricing;
- highly configurable enterprise RBAC;
- accounting-platform integration;
- SMS infrastructure;
- advanced analytics;
- complex automated waiting-list allocation;
- advanced compliance management.

---

## 56. Historical Facts vs Product Decisions

It is important to distinguish historical behaviour from proposed modern behaviour.

### Historical fact

Supported by first-hand experience, former management discussion or historical documentation.

Examples:

- Excel was used to record bookings.
- Managers approved new bookings.
- Cash and cheque payments were accepted.
- Leisure assistants issued receipts.
- Money was stored in centre safes.
- Management collected money from sites.
- Customers could make recurring bookings.
- Future dates could be pre-booked.
- Facility resources could be subdivided.

### Proposed product decision

A design choice for the modern replacement.

Examples:

- Stripe for card payments.
- configurable provisional hold expiry;
- online customer portal;
- automatic conflict validation;
- live staff dashboard;
- organisation user roles;
- digital incident reporting.

### TBD / configurable

A value that does not need to be historically exact.

Examples:

- demonstration facility prices;
- exact opening hours;
- provisional hold duration;
- payment deadline;
- individual centre equipment quantities.

### Unknown historical behaviour

Where evidence is incomplete, the project should record the uncertainty rather than invent historical facts.

---

## 57. Current Assumptions and Open Decisions

The following details may be refined later without blocking the project:

- exact demonstration pricing;
- exact equipment quantities per centre;
- exact demonstration opening hours;
- final cancellation/refund configuration;
- provisional booking expiry defaults;
- payment deadline defaults;
- final organisation permissions;
- event-specific workflows;
- detailed compliance/document rules;
- exact reporting scope.

Where appropriate these should become configurable business rules rather than hard-coded assumptions.

---

## 58. Non-Functional Areas Requiring Dedicated Review

Before production-style implementation is considered complete, dedicated requirements should be created for:

- authentication;
- authorization;
- security;
- privacy;
- UK data-protection obligations;
- accessibility;
- auditability;
- performance;
- concurrency;
- database integrity;
- backups/recovery;
- observability;
- testing.

The availability and booking system must be designed with concurrency in mind so that two simultaneous requests cannot successfully reserve the same exclusive resource.

---

## 59. Product Principle

The platform should be designed around the following principle:

> Create a single reliable source of truth for facility availability, bookings, payments and daily operations while automating repetitive administration and retaining human control where management judgement is required.

---

## 60. Business Value

The proposed platform should create value by:

- reducing manual administration;
- eliminating duplicate Excel entry;
- reducing booking conflicts;
- enabling customer self-service;
- enabling online payments;
- reducing cash handling;
- reducing physical payment collection;
- improving facility utilisation;
- improving customer experience;
- improving visibility of availability;
- improving communication with on-site staff;
- improving payment tracking;
- creating better operational reporting;
- providing management with a central view across multiple centres.

Success should ultimately be measured through business outcomes rather than simply the number of software features delivered.

Potential measures include:

- reduction in administrative time per booking;
- percentage of bookings completed through self-service;
- percentage of payments collected online;
- reduction in booking conflicts;
- reduction in manual payment reconciliation;
- booking conversion rate;
- facility utilisation;
- outstanding invoice value;
- time from request to confirmation.

---

## 61. Portfolio / Case Study Positioning

This project should demonstrate more than Laravel implementation skills.

The case study should show the ability to:

1. investigate an existing business process;
2. interview stakeholders;
3. analyse historical documentation;
4. identify operational problems;
5. separate business requirements from implementation ideas;
6. design improved workflows;
7. model complex booking rules;
8. design a production-style architecture;
9. use AI-assisted development responsibly;
10. deliver software tied to measurable business value.

The portfolio narrative should focus on solving an operational business problem rather than presenting the project as a generic booking-system tutorial.

---

## 62. Privacy and Public Repository Considerations

This repository is intended to be publicly accessible.

Historical source documents, customer information and personally identifiable information should not be committed to the repository.

Demonstration data should use fictional:

- customer names;
- organisations;
- email addresses;
- phone numbers;
- bookings;
- financial information.

The product is inspired by real operational experience but should use its own branding and should not present itself as an official system belonging to the historical organisation.

---

## 63. Discovery Status

Initial business discovery is considered sufficiently complete to proceed to requirements definition.

Additional information may still be incorporated if historical pricing or other useful details become available.

Missing historical details should not prevent development where they can safely be represented as configurable product decisions or fictional demonstration data.

---

## 64. Next Steps

The planned documentation and implementation sequence is:

1. Complete and review `discovery.md`.
2. Create `requirements.md`.
3. Complete UX/design discovery.
4. Create `design.md`.
5. Create `architecture.md`.
6. Define the domain/data model.
7. Create `implementation-plan.md`.
8. Create AI/development agent instructions.
9. Scaffold the application.
10. Implement the MVP incrementally.
11. Test business rules and security boundaries.
12. Build the public case study and demonstration environment.

Development should not begin with feature implementation until the core requirements, design direction and architecture have been sufficiently defined.