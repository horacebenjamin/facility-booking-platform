# Facility Booking Platform — Visual Design Specification

## 1. Purpose

This document defines the visual design system and user-interface direction for the Facility Booking Platform.

It complements:

- `docs/discovery.md` — business context and historical discovery;
- `docs/requirements.md` — functional and non-functional requirements;
- `docs/design.md` — UX, workflows and interaction design;
- `docs/architecture.md` — technical architecture;
- `docs/implementation-plan.md` — implementation sequence.

This document defines how the approved user experience should be visually expressed across:

- public pages;
- customer account pages;
- management interfaces;
- leisure-assistant operational interfaces;
- desktop;
- tablet;
- mobile.

It is intended to provide sufficient visual direction for both human developers and AI coding agents without requiring a unique high-fidelity mock-up for every page.

---

# 2. Document Authority

The visual mock-ups created during design are reference material, not independent business requirements.

The following authority order MUST be respected:

1. `requirements.md` defines required system behaviour.
2. `design.md` defines UX behaviour and interaction intent.
3. `visual-design.md` defines visual presentation and reusable UI patterns.
4. Approved mock-ups demonstrate the intended visual direction.
5. Implementation details must comply with `architecture.md`.

If a mock-up conflicts with a requirement, the requirement takes precedence.

If a mock-up contains:

- generated text errors;
- invented data;
- fictional prices;
- inconsistent labels;
- accidental visual artefacts;
- unsupported functionality;

those elements MUST NOT automatically be implemented.

Mock-ups should be interpreted as visual references rather than pixel-perfect specifications.

---

# 3. Product and Operator Branding

## 3.1 Product Identity

The underlying software product is currently named:

**Facility4Hire**

Facility4Hire is a multi-site facility booking and operations platform.

The product supports organisations that operate:

- sports centres;
- community venues;
- leisure facilities;
- school facilities;
- meeting spaces;
- event spaces;
- other hireable venues.

Facility4Hire should not dominate the public customer experience.

The customer should primarily feel that they are booking directly with the organisation operating the venues.

---

## 3.2 Demonstration Operator

The demonstration organisation is:

**Sheffield Community Venues**

This is a fictional operator used to demonstrate the platform.

It MUST NOT imply affiliation with the historical Active8, Interserve, Mitie, Sheffield schools or any other real organisation.

The public-facing experience should primarily use Sheffield Community Venues branding.

Facility4Hire may be referenced subtly where appropriate, for example:

> Powered by Facility4Hire

This is optional and should not distract from the operator's identity.

---

## 3.3 White-Label Direction

The visual architecture should allow operator branding to be configurable in the future.

Potential configurable elements include:

- organisation name;
- logo;
- primary brand colour;
- contact information;
- venue imagery;
- footer information.

The initial implementation does not require a complete white-label configuration system.

The visual implementation should nevertheless avoid unnecessarily coupling the interface to Sheffield Community Venues.

---

# 4. Visual Design Principles

The interface should feel:

- modern;
- professional;
- trustworthy;
- calm;
- operationally clear;
- accessible;
- efficient;
- commercially credible.

The application should NOT resemble:

- a generic Laravel starter application;
- a developer administration template;
- a highly decorative consumer application;
- an experimental AI interface;
- a sports-only application;
- an overly dense enterprise system on customer-facing pages.

The product should look credible enough that a real multi-site venue operator could plausibly use it.

---

# 5. Visual Hierarchy

Every screen should establish a clear hierarchy.

The normal order is:

1. Current context
2. Primary task
3. Important status
4. Primary information
5. Primary action
6. Supporting information
7. Secondary actions

Users should not need to inspect multiple areas of the page to understand:

- where they are;
- what the current state is;
- what they can do next.

Important operational or commercial information should be visible rather than hidden behind unnecessary interaction.

---

# 6. Colour Direction

The approved visual direction uses a combination of:

- dark navy;
- teal;
- green;
- neutral whites and greys;
- restrained semantic colours.

Exact colour tokens should be finalised during frontend implementation using accessible contrast ratios.

The visual intention is more important than copying colours directly from generated mock-ups.

---

## 6.1 Navy

Dark navy is the primary structural colour.

Typical uses:

- management sidebar;
- staff navigation;
- headings;
- strong text;
- selected administrative navigation;
- high-emphasis structural elements.

It provides the professional and operational character of the product.

---

## 6.2 Teal / Green

Teal and green provide the main brand/action accent.

Typical uses:

- primary buttons;
- selected navigation;
- successful states;
- available states;
- active filters;
- progress indicators;
- positive operational actions.

Green MUST NOT be used as the only method of communicating success or availability.

---

## 6.3 Neutral Colours

Neutral colours should dominate the interface.

Use:

- white backgrounds;
- very light grey page backgrounds;
- subtle grey borders;
- dark navy/charcoal body text;
- muted secondary text.

The design should generally remain light and spacious.

---

# 7. Semantic Colours

Semantic colours should be consistent throughout the application.

Suggested meanings:

### Green

- available;
- successful;
- paid;
- confirmed;
- completed;
- arrived.

### Blue

- informational;
- active;
- selected;
- in progress;
- neutral system state.

### Amber

- pending;
- awaiting approval;
- awaiting payment;
- attention required;
- upcoming where useful.

### Red

- failed;
- rejected;
- destructive action;
- serious conflict;
- cancellation;
- no-show where appropriate;
- critical operational issue.

### Grey

- unavailable;
- disabled;
- inactive;
- expired;
- historical;
- secondary state.

Status communication MUST NOT rely solely on colour.

Status badges should contain readable text.

---

# 8. Typography

The product should use a clean modern sans-serif typeface suitable for application interfaces.

The implementation should prefer a well-supported web/system font rather than introducing unnecessary font dependencies.

Typography should prioritise:

- readability;
- hierarchy;
- accessibility;
- compactness where operationally necessary.

Avoid excessive font sizes and decorative typography.

---

## 8.1 Typical Hierarchy

### Page title

Used once per primary page.

Examples:

- Sports centres across Sheffield
- My bookings
- Today's schedule
- Booking #BK-10025

### Section heading

Used for major content groups.

Examples:

- Customer details
- Booking information
- Facilities at this centre
- Upcoming bookings

### Card title

Used for individual resources, facilities, bookings or actions.

### Body text

Used for normal content.

### Supporting text

Used for:

- descriptions;
- metadata;
- secondary information;
- helper text.

Supporting text must still maintain accessible contrast.

---

# 9. Spacing and Layout

The visual system should use consistent spacing rather than page-specific arbitrary values.

Spacing should create:

- clear grouping;
- comfortable scanning;
- sufficient touch targets;
- visual separation without excessive whitespace.

Public/customer interfaces may use more generous spacing.

Management interfaces may use moderately denser spacing.

Operational staff interfaces should prioritise fast scanning and touch interaction.

---

# 10. Corners, Borders and Elevation

The approved visual direction uses:

- moderate border radius;
- subtle borders;
- restrained shadows;
- clearly separated cards.

Avoid excessive:

- glass effects;
- gradients;
- large shadows;
- floating decorative components.

Cards should feel functional rather than ornamental.

Elevation should primarily communicate hierarchy.

---

# 11. Icons

Icons should support comprehension rather than decoration.

Typical uses:

- navigation;
- facility features;
- dates;
- time;
- people/capacity;
- equipment;
- payments;
- notifications;
- incidents;
- locations;
- accessibility.

Use one consistent icon family where practical.

Icons MUST NOT be the only way important actions or statuses are communicated.

---

# 12. Photography

Photography is primarily appropriate for:

- centres;
- facilities;
- public discovery;
- customer booking context.

Images should help users understand what they are booking.

Typical imagery includes:

- sports halls;
- football pitches;
- courts;
- meeting rooms;
- event spaces.

Operational and management screens should use imagery sparingly.

Where images are unavailable, the layout must remain functional using an appropriate placeholder.

---

# 13. Application Experience Areas

The system contains three visually related but distinct application experiences.

## 13.1 Public / Customer

Technology:

- Inertia;
- Vue;
- TypeScript;
- shadcn-based components.

Characteristics:

- welcoming;
- spacious;
- visual;
- guided;
- customer-friendly.

---

## 13.2 Management

Technology:

- Filament.

Characteristics:

- information dense;
- operational;
- efficient;
- table-oriented where appropriate;
- strong filtering;
- clear status visibility.

The management interface should retain the overall product visual identity while respecting Filament conventions rather than fighting the framework.

---

## 13.3 Leisure Assistant / Operations

Technology:

- Filament or the staff-facing architecture defined in `architecture.md`.

Characteristics:

- mobile/tablet friendly;
- highly task-focused;
- fast to scan;
- minimal administrative complexity;
- large operational actions;
- least-privilege information.

---

# 14. Public Navigation

The public header should normally contain:

- operator logo/name;
- Sports centres;
- Facilities;
- Find availability / booking entry point;
- account/sign-in actions.

Navigation should remain simple.

Customers should not need to understand Facility4Hire's internal domain model.

For example, they should be able to think:

> I need a badminton court next Tuesday.

rather than:

> I need to select a child resource belonging to a parent facility.

The UI translates the domain model into customer-friendly language.

---

# 15. Customer Account Navigation

The approved customer account pattern contains navigation such as:

- Dashboard;
- Find facility;
- My bookings;
- My payments;
- Invoices & receipts;
- My profile;
- Notifications.

Desktop may use persistent side navigation.

Mobile should use an appropriate compact navigation pattern.

Navigation should emphasise customer tasks rather than system modules.

---

# 16. Management Navigation

Management uses a persistent dark navigation structure on larger screens.

Typical areas include:

- Dashboard;
- Bookings;
- Calendar;
- Centres;
- Facilities;
- Equipment;
- Customers;
- Payments;
- Invoices;
- Incidents;
- Reports;
- configuration areas where authorised.

Exact navigation must follow implemented scope and permissions.

Do not display inaccessible modules simply because they appeared in a mock-up.

---

# 17. Operations Navigation

The leisure-assistant interface should contain only operationally relevant functionality.

Primary areas may include:

- Today's schedule;
- Bookings;
- Incidents;
- Facilities;
- Equipment;
- Messages/notifications where implemented.

Financial administration, pricing configuration, approval controls and unrelated management functions should not appear for normal leisure assistants.

---

# 18. Public Homepage Pattern

The homepage introduces the operator and directs users towards finding suitable facilities.

Primary objectives:

1. communicate what can be hired;
2. establish trust;
3. make facility discovery easy;
4. lead toward availability and booking.

Recommended sections include:

- hero;
- facility/centre search;
- centre discovery;
- activity/facility categories;
- featured facilities;
- how booking works;
- operator information;
- footer.

The homepage should prioritise booking intent over marketing decoration.

---

# 19. Sports Centres Listing Pattern

The centre listing page represents physical venue locations.

A centre card/list item should focus on:

- centre name;
- image;
- location/address summary;
- available facility types;
- relevant accessibility/amenity information;
- View centre action.

Centre-level pricing MUST NOT be displayed as though a centre itself has one hire price.

Pricing belongs to bookable facilities/resources.

---

## 19.1 Centre Filters

Desktop may provide filters including:

- Sports centres;
- Facilities;
- Location;
- Accessibility.

The Sports Centres filter should contain actual centre names.

Example demonstration centres:

- North Sheffield Leisure Centre;
- Hillsborough Community Centre;
- Attercliffe Community Centre;
- Graves Park Sports Hub.

These are fictional demonstration data.

The primary selector may use:

**Sports centres**

with:

**All sports centres**

as the default option.

Mobile should avoid unnecessary duplicated filter controls.

---

# 20. Sports Centre Detail Pattern

A centre detail page describes the physical venue.

Typical information:

- centre name;
- address;
- hero image;
- centre description;
- facilities;
- accessibility information;
- amenities;
- opening information;
- contact information;
- location/map;
- gallery where useful.

The primary commercial content is:

**Facilities at this centre**

Facility cards lead into individual facility details and booking options.

---

# 21. Facility Detail Pattern

A facility represents a hireable area/category within a centre.

Examples:

- Sports Hall;
- Gymnasium;
- 3G Pitch;
- Hard Courts;
- Meeting Room.

The page should show:

- facility name;
- parent centre;
- images;
- description;
- features;
- capacity where applicable;
- accessibility information;
- booking options/resources;
- prices;
- availability entry point.

---

## 21.1 Bookable Options

The relationship between facility and bookable resource must be understandable.

Example:

Sports Hall

may contain:

- Whole Sports Hall;
- Half Sports Hall;
- Badminton Court 1;
- Badminton Court 2;
- Badminton Court 3;
- Badminton Court 4.

Customers should not need to understand database terminology such as parent/child allocation.

The interface should simply communicate what they can hire.

---

# 22. Pricing Presentation

Pricing should be displayed alongside the relevant bookable option.

Examples:

- Whole Sports Hall — £X/hour
- Badminton Court — £X/hour

Demonstration prices may be realistic fictional values.

Generated mock-up prices are illustrative and MUST NOT automatically become final configured prices.

Pricing should clearly communicate the pricing unit, such as:

- per hour;
- per session;
- agreed price.

---

# 23. Availability Pattern

Availability is one of the most important interfaces in the product.

Users should be able to quickly understand:

- selected centre;
- selected facility;
- selected resource/bookable option;
- date;
- available periods;
- unavailable periods;
- price;
- selected period.

Availability should be visually scannable.

---

## 23.1 Availability States

At minimum the interface must distinguish:

- Available;
- Unavailable;
- Selected.

Additional internal states may exist but should not expose private booking information.

Public users MUST NOT see who has booked an unavailable period.

---

## 23.2 Consecutive Selection

Where supported, customers should be able to select consecutive valid booking periods.

The resulting duration and estimated price should update clearly.

---

## 23.3 Booking Summary

Availability selection should provide a persistent or clearly visible summary containing relevant:

- centre;
- facility;
- resource;
- date;
- start/end time;
- duration;
- price.

On mobile this may become a sticky summary/action area.

---

# 24. Booking Request Pattern

The customer booking flow should feel guided rather than administrative.

The approved conceptual progress is:

**Details → Availability → Review → Confirmation**

The exact number of technical routes/pages is not important.

The user should always understand where they are in the process.

---

## 24.1 Booking Details

Typical fields include:

- booking option/resource;
- date;
- start time;
- end time;
- activity/purpose;
- participant count;
- equipment where relevant;
- additional information.

Forms should be divided into logical sections.

Do not present the customer with one large undifferentiated form.

---

## 24.2 Check Availability

Checking availability and submitting a booking request are different actions.

The UI MUST NOT imply that checking availability confirms a booking.

---

## 24.3 Review and Submit

Before submission, show a clear summary.

Typical information:

- centre;
- facility;
- resource;
- date/time;
- customer;
- activity;
- equipment;
- price;
- relevant terms.

Primary action:

**Submit booking request**

The language must communicate that the request may still require management approval.

---

# 25. Customer Dashboard Pattern

The customer dashboard is task-oriented.

It should answer:

- What bookings do I have coming up?
- Is anything awaiting approval?
- Do I need to pay anything?
- Do I have outstanding invoices?
- What should I do next?

Typical summary cards:

- Upcoming bookings;
- Pending requests;
- Payment due;
- Outstanding invoices.

Supporting areas may include:

- upcoming bookings;
- recent activity;
- quick actions;
- useful facility discovery.

Avoid filling the dashboard with analytics that do not help the customer act.

---

# 26. Customer Booking Details Pattern

A single booking-details layout should adapt to booking state.

Do not create unnecessarily different page structures for every status.

The page should contain consistent booking information while the status/action area changes.

---

## 26.1 Pending Approval

Communicate:

- request received;
- awaiting management review;
- current reservation/hold information where appropriate;
- available customer actions.

---

## 26.2 Payment Required

Communicate:

- booking approved;
- payment required;
- amount;
- payment deadline where configured;
- consequence of missing the deadline where applicable.

Primary action:

**Make payment**

---

## 26.3 Confirmed

Communicate:

- booking confirmed;
- date/time;
- resource;
- payment/invoice arrangement;
- booking reference;
- relevant customer actions.

---

## 26.4 Cancelled / Rejected / Expired

These states should clearly explain:

- what happened;
- relevant reason where customer-visible;
- what the customer can do next.

Do not rely only on a red status badge.

---

# 27. Payment / Checkout Pattern

Payment should feel secure and focused.

Typical structure:

- booking summary;
- amount due;
- payment method;
- secure payment-provider area;
- primary payment action.

Raw card details MUST NOT be stored by the Facility4Hire application.

Stripe/provider-controlled payment components should be used according to the application architecture.

---

## 27.1 Payment States

The visual design must support:

### Ready

Customer can submit payment.

### Processing

Prevent accidental duplicate submission and show visible progress.

### Successful

Clearly communicate payment success and the resulting booking state.

### Failed

Explain that payment was unsuccessful without implying the booking itself has disappeared.

Provide a safe retry path where appropriate.

---

# 28. Management Dashboard Pattern

The management dashboard is an operational overview.

It should prioritise actionable information rather than decorative analytics.

Important information may include:

- requests awaiting approval;
- today's bookings;
- payments requiring attention;
- invoices requiring attention;
- centre/closure updates;
- recent requests;
- today's operational schedule;
- utilisation summary;
- revenue summary where useful.

Primary actions should lead managers directly to work requiring attention.

---

# 29. Management Data Table Pattern

Management list screens should use consistent Filament table patterns.

Typical capabilities:

- search;
- filters;
- status tabs;
- date range;
- centre filter;
- facility filter;
- pagination;
- sortable columns where useful;
- row actions;
- bulk actions only where genuinely appropriate.

Tables should avoid showing every available database field.

Columns should be selected according to the management task.

---

# 30. Booking Management List

Typical columns may include:

- booking reference;
- customer/organisation;
- centre;
- facility/resource;
- date/time;
- booking status;
- payment status;
- amount where authorised;
- action.

Status and payment state must remain visually distinct.

Booking status and financial status are separate concepts.

---

# 31. Management Calendar Pattern

The management booking calendar should support operational scheduling across centres.

Potential views:

- day;
- week;
- month;
- agenda/list.

Managers should be able to identify:

- booking time;
- centre;
- facility/resource;
- relevant status.

Centre filtering should be readily available.

Colour may assist differentiation but MUST NOT be the sole source of meaning.

Mobile may prioritise list/agenda views rather than attempting to reproduce a dense desktop calendar.

---

# 32. Manager Booking Request Review

This is a critical management screen.

The manager must be able to understand the request before deciding.

Information should include relevant:

- booking reference;
- customer;
- organisation;
- centre;
- facility/resource;
- date/time;
- recurring occurrences;
- activity;
- participant count;
- equipment;
- availability/conflicts;
- pricing;
- payment arrangement;
- customer notes;
- internal notes where authorised.

---

## 32.1 Decision Area

Decision controls should be visually separated from informational content.

Typical actions:

- Approve;
- Reject;
- request/change information where supported.

Approval should be prominent when valid.

Reject should be clearly available without being visually confused with the primary positive action.

Destructive or irreversible actions require appropriate confirmation.

---

## 32.2 Conflicts

Conflicts require prominent visual treatment.

The manager should not need to manually scan the entire request to discover a conflict.

Conflict presentation should explain what is conflicting where possible.

---

# 33. Today's Schedule Pattern

Today's Schedule is the primary leisure-assistant operational interface.

It should be optimised for:

- tablets;
- phones;
- quick scanning during a shift.

The schedule should prioritise:

- time;
- facility/resource;
- activity;
- booking/customer context necessary for operations;
- participant information where needed;
- equipment/setup;
- current operational status.

---

## 33.1 Schedule Statuses

Examples include:

- Upcoming;
- In progress;
- Completed;
- No-show.

Status labels must remain clear without relying solely on colour.

---

## 33.2 Centre Context

The current centre should always be clear.

Where a staff member is authorised to cover multiple centres, provide an obvious centre selector.

---

# 34. Operational Booking Details

Opening a booking from Today's Schedule should provide only the information required to operate the session.

Typical information:

- booking reference;
- date/time;
- facility/resource;
- activity;
- booking contact;
- participant count;
- equipment;
- setup requirements;
- operational notes;
- relevant status.

Avoid exposing unnecessary customer or financial information.

---

# 35. Operational Actions

Operational actions must be large, clear and touch friendly.

The approved terminology for recording arrival is:

**Booking arrived**

Do not substitute:

- Check in;
- Check customer in;
- Mark as arrived;

unless the design documentation is intentionally changed.

Following arrival, appropriate actions may include:

- Complete booking;
- Mark as no-show;
- Report an issue.

Actions displayed must depend on valid booking state.

---

# 36. Attendance

Where individual attendance information is required, use a compact list suitable for touch interaction.

However, the system's core requirement is booking-level arrival/no-show/completion.

Do not introduce participant-level attendance functionality merely because a generated mock-up visually suggested it unless corresponding requirements are defined.

This is an example of requirements taking precedence over mock-up content.

---

# 37. Incident and Damage Reporting

Operational staff should have a straightforward way to report an issue associated with a booking.

The interface may distinguish categories such as:

- equipment damage;
- facility issue;
- health and safety;
- other.

Exact categories should remain configurable or implementation-defined where requirements do not prescribe them.

---

## 37.1 Incident Form

The form should automatically retain available context such as:

- booking;
- centre;
- facility/resource;
- date/time;
- reporting staff member.

The staff member should not need to re-enter information the system already knows.

Potential user-entered information includes:

- issue type;
- title;
- description;
- immediate action taken.

Photo/file upload should only be implemented when included in the relevant implementation scope.

---

# 38. Status Badge Pattern

Status badges should:

- use consistent shapes;
- remain compact;
- include text;
- use semantic colour;
- remain legible;
- never communicate meaning through colour alone.

Booking and payment statuses should use separate badges where both are displayed.

Do not combine unrelated domain states into one ambiguous status.

---

# 39. Button Hierarchy

Buttons should have a predictable hierarchy.

## Primary

Used for the main action.

Examples:

- Check availability;
- Submit booking request;
- Make payment;
- Approve;
- Booking arrived;
- Complete booking.

Normally only one primary action should visually dominate a section.

---

## Secondary

Used for valid supporting actions.

Examples:

- Edit;
- View details;
- Contact customer;
- Back;
- Save draft where supported.

---

## Destructive

Used for:

- Reject;
- Cancel;
- Mark no-show where operationally appropriate;
- destructive administrative actions.

Destructive styling should not be used for ordinary navigation.

---

# 40. Form Pattern

Forms should use:

- visible labels;
- helpful descriptions where needed;
- sensible field grouping;
- inline validation;
- clear required/optional communication;
- accessible error summaries where appropriate.

Placeholder text MUST NOT replace labels.

Validation messages should explain how the user can correct the problem.

---

# 41. Filter Pattern

Filters should be appropriate to the number and complexity of options.

Use:

- select menus;
- search;
- date controls;
- checkboxes;
- chips/toggles where suitable.

Desktop management screens may use denser filter bars.

Public/customer screens should use simpler, more approachable controls.

On mobile, advanced filters may use a drawer/sheet pattern.

---

# 42. Cards

Cards are used for grouped information such as:

- centres;
- facilities;
- bookings;
- dashboard summaries;
- operational sessions.

Cards should have:

- clear hierarchy;
- consistent padding;
- subtle borders;
- restrained elevation.

Avoid placing cards inside cards repeatedly unless hierarchy genuinely requires it.

---

# 43. Tables

Tables are primarily a management pattern.

Tables should:

- have readable column headings;
- avoid unnecessary columns;
- support horizontal adaptation where unavoidable;
- transform to more suitable mobile representations where necessary.

Do not force a wide desktop table into a phone viewport if a card/list representation communicates the information better.

---

# 44. Empty States

Every major collection screen should have a deliberate empty state.

Examples:

### No upcoming bookings

Explain that the customer has no upcoming bookings and provide a route to find a facility.

### No pending requests

Communicate that management has no requests requiring review.

### No bookings today

Tell leisure assistants clearly that there are no scheduled bookings for the selected centre/date.

Empty states should help users understand what the absence of data means.

---

# 45. Loading States

Interactive pages should provide visible loading feedback.

Appropriate techniques include:

- skeletons;
- local spinners;
- disabled action states;
- progress indicators.

Avoid blocking the entire application for small local requests.

Users must not be encouraged to repeat payment or booking actions because the application appears unresponsive.

---

# 46. Error States

Errors should:

1. explain what failed;
2. preserve user-entered information where safe;
3. explain what the user can do next.

Examples include:

- availability changed;
- payment failed;
- network error;
- validation failure;
- permission denied.

Technical exception details must not be exposed to normal users.

---

# 47. Conflict State

Booking conflicts deserve a dedicated pattern.

A conflict should clearly communicate:

- that the selected option is no longer available;
- which date/time/resource is affected where appropriate;
- what the user can do next.

For recurring bookings, conflicting occurrences should be identifiable individually.

The user should not have to restart the entire booking flow unnecessarily.

---

# 48. Success States

Successful important actions should provide explicit confirmation.

Examples:

- booking request submitted;
- payment successful;
- booking approved;
- booking arrived recorded;
- incident submitted.

Success should not be represented solely by changing a badge somewhere else on the page.

---

# 49. Responsive Design

Responsive behaviour is a core requirement rather than an optional visual enhancement.

---

## 49.1 Public / Customer

Core customer journeys must work comfortably on mobile.

Desktop multi-column layouts should stack logically.

Primary actions should remain easy to reach.

Booking summaries may become sticky bottom sections where beneficial.

---

## 49.2 Management

Management should remain practical on:

- desktop;
- laptop;
- tablet.

Some highly dense management functionality may use simplified mobile representations.

The goal is usability rather than reproducing desktop layouts exactly.

---

## 49.3 Operations

Today's Schedule and operational booking details should be particularly strong on mobile.

Leisure assistants may be moving around a venue while using the system.

Therefore:

- touch targets should be generous;
- primary actions should be prominent;
- unnecessary content should be removed;
- critical information should not require horizontal scrolling;
- actions should remain easy to access.

---

# 50. Accessibility

The application should target WCAG 2.2 AA.

Visual implementation should include:

- sufficient colour contrast;
- visible keyboard focus;
- keyboard-operable controls;
- semantic HTML;
- accessible labels;
- accessible validation;
- meaningful heading structure;
- suitable touch targets;
- status text in addition to colour;
- alternative text for meaningful imagery.

Motion should be restrained and should not be required to understand system state.

Accessibility requirements take precedence over exact reproduction of a mock-up.

---

# 51. Focus States

Interactive controls must provide clearly visible focus indicators.

Do not remove browser focus outlines unless replaced by an equally visible accessible alternative.

Focus states should be visually consistent across:

- buttons;
- links;
- inputs;
- selects;
- tabs;
- menu items;
- booking slots.

---

# 52. Disabled States

Disabled controls should:

- appear visually inactive;
- remain readable;
- not rely on opacity so low that content becomes inaccessible.

Where useful, explain why an action is unavailable.

For example:

> Payment becomes available after the booking request is approved.

---

# 53. Confirmation Dialogs

Confirmation should be used where an action:

- is destructive;
- is difficult to reverse;
- materially changes a booking;
- affects availability;
- may have financial consequences.

Do not add confirmation dialogs to ordinary low-risk actions.

---

# 54. Notifications and Messages

Transient messages should use consistent toast/banner patterns.

Examples:

- saved successfully;
- booking request submitted;
- payment failed;
- availability changed.

Persistent problems requiring action should use an inline alert rather than disappearing automatically.

---

# 55. Content and Terminology

Terminology must remain consistent across interfaces.

Preferred terms include:

- Sports centre;
- Facility;
- Booking option where customer-friendly;
- Booking;
- Booking request;
- Booking reference;
- Today's schedule;
- Booking arrived;
- No-show;
- Payment required;
- Confirmed;
- Pending approval.

Avoid unnecessary technical/domain terminology in customer interfaces.

---

# 56. Facility vs Centre Terminology

These terms must not be used interchangeably.

### Sports Centre / Centre

The physical venue/site.

Example:

**North Sheffield Leisure Centre**

### Facility

A hireable area/category at a centre.

Example:

**Sports Hall**

### Bookable Option / Resource

The actual allocation that may be booked.

Examples:

- Whole Sports Hall;
- Half Sports Hall;
- Badminton Court 1.

### Price

Belongs to the relevant bookable facility/resource arrangement.

Do not present a generic centre-level hire price unless a genuine business rule supports one.

---

# 57. Visual Reference Screens

The approved design work establishes visual direction for the following representative screens:

1. Facility Availability / Booking Selection
2. Manager Booking Request Review
3. Today's Schedule
4. Public Homepage
5. Sports Centres Listing
6. Sports Centre Details
7. Facility Details and Pricing
8. Customer Dashboard
9. Booking Request / Review and Submit
10. Customer Booking Details and Status States
11. Payment / Checkout
12. Management Dashboard
13. Management Booking List / Calendar
14. Operational Booking Details / Incident Reporting

These screens provide sufficient representative coverage for the initial application.

Not every CRUD or supporting page requires a separate high-fidelity mock-up.

---

# 58. Screen Pattern Inheritance

Screens without dedicated mock-ups should inherit from the closest established pattern.

Examples:

### Customer profile

Inherit:

- customer account shell;
- customer form patterns;
- card patterns.

### Customer invoices

Inherit:

- customer account shell;
- booking list/card patterns;
- status badges;
- payment presentation.

### Management equipment

Inherit:

- management shell;
- Filament table;
- filters;
- forms;
- status patterns.

### Management centres

Inherit:

- management shell;
- Filament resource/list/form patterns.

### Incident list

Inherit:

- management data table;
- status filters;
- detail/review pattern.

AI agents should compose new screens from these established patterns rather than inventing unrelated layouts.

---

# 59. DESIGN vs PATTERN Screens

The project does not require bespoke mock-ups for every screen.

## DESIGN

Screens requiring deliberate visual treatment because they contain unusual or important interactions.

Examples:

- availability;
- booking request;
- manager request review;
- Today's Schedule;
- operational booking detail;
- checkout.

## PATTERN

Screens that can safely inherit existing visual structures.

Examples:

- CRUD management pages;
- profile;
- simple settings;
- standard lists;
- standard forms;
- invoice lists.

This distinction should prevent unnecessary design work while maintaining visual consistency.

---

# 60. Mock-Up Interpretation Rules

Generated mock-ups MUST NOT be implemented blindly.

Developers and AI agents should extract:

- hierarchy;
- layout;
- component relationships;
- visual tone;
- responsive intent;
- interaction emphasis.

They should NOT automatically copy:

- generated names;
- addresses;
- prices;
- participant data;
- dates;
- counts;
- unsupported buttons;
- generated text;
- accidental duplicated components;
- visual artefacts.

Real application data and requirements determine actual content.

---

# 61. Demonstration Data

Public demo data must be fictional.

The application may use realistic fictional:

- centre names;
- customer names;
- organisation names;
- prices;
- addresses;
- bookings;
- equipment quantities.

Historical customer or employee PII must not be used.

The demonstration environment should feel realistic without pretending fictional data is historically accurate.

---

# 62. Framework Implementation Guidance

## Public / Customer

Use the application's established:

- Vue components;
- TypeScript;
- shadcn primitives;
- Tailwind/design tokens;
- shared layout components.

Avoid page-specific CSS where reusable components/tokens are appropriate.

---

## Management / Operations

Use Filament's component and theming system where possible.

Do not excessively override Filament merely to reproduce a generated image pixel-for-pixel.

Custom components are justified where the workflow genuinely requires them, particularly:

- availability;
- operational schedule;
- specialised booking review;
- calendar behaviour.

---

# 63. Reusable Components

Where practical, establish reusable components for:

- status badges;
- booking cards;
- centre cards;
- facility cards;
- price display;
- availability slots;
- booking summaries;
- page headers;
- empty states;
- alerts;
- confirmation dialogs;
- filters;
- form sections;
- date/time presentation;
- money presentation.

Reuse should improve consistency without forcing unrelated workflows into inappropriate abstractions.

---

# 64. Design Tokens

The implementation should define reusable design tokens for:

- colours;
- spacing;
- typography;
- border radius;
- borders;
- shadows;
- breakpoints where required;
- focus rings;
- semantic status colours.

Components should reference tokens rather than repeatedly introducing arbitrary values.

---

# 65. AI-Assisted Experience Pattern

AI-assisted interfaces must use the existing Facility4Hire visual system,
application shell, typography, spacing, colour, card, table, form, alert and
confirmation patterns. AI must not introduce a separate visual language or an
experimental chatbot aesthetic.

The primary conversational surface is a contextual right-hand
slide-over/drawer on desktop and tablet. It should preserve visibility of the
current page where practical rather than replace the user's workflow with a
generic chat screen.

On mobile, use a bottom sheet or an appropriate near-full-screen mobile drawer.
Do not compress the desktop drawer into a narrow column and do not use a centred
modal popup as the default conversational surface.

A dedicated management AI Assistant workspace may support deeper cross-system
analysis. Selected AI summaries and discovery controls may also be embedded in
high-value screens, including the Manager AI Daily Briefing, Leisure Assistant
AI Shift Briefing and natural-language facility discovery. Embedded AI should
use existing card, alert, search and content patterns.

## 65.1 Prompt and Response

The drawer/sheet header should identify the assistant, provide a clearly
labelled close control and show useful authorised context such as the current
booking, facility, report, centre or Today's Schedule. Context should be concise
and visually distinct from the conversation.

The prompt control should have a visible label, clear submission action and
helpful example prompts appropriate to the user's role. Suggested prompts may
help users begin but must not compete with the primary input or imply unsupported
capabilities.

When context is available, the initial surface should offer relevant suggested
actions instead of always presenting an empty conversation. Examples include:

- Summarise this booking
- Explain conflicts
- Find alternatives
- Explain pricing
- What changed today?
- What do I need to set up next?
- Summarise this report
- What needs my attention?

AI responses should:

- be visibly identified as AI-generated where relevant;
- use readable text hierarchy;
- separate generated explanation from authoritative tool-derived data;
- use established result cards, lists, tables, metrics and status badges for
  structured data;
- link to underlying authorised records or reports where useful;
- avoid presenting generated prose as a confirmed application action.

Authoritative values such as availability, booking state, price and payment
state should use the same established components and terminology as elsewhere
in the application.

## 65.2 Tool and Action Progress

While an agent is working, the interface should provide concise progress such
as retrieving bookings, checking authorised availability or preparing a
summary. Progress must be understandable without exposing internal chain-of-
thought, raw prompts, tool payloads or technical provider details.

Loading and longer-running states should use established spinner, skeleton and
progress patterns. Controls should prevent accidental duplicate submissions
while a request is active and should provide an accessible status announcement.

## 65.3 Errors and Provider Unavailability

Tool, permission, timeout and invalid-response errors should use the existing
inline alert/error patterns and explain the available next step.

If the AI provider is unavailable, the surface should clearly state that AI
assistance is temporarily unavailable and, where useful, link to the normal
report, search or workflow. It must not imply that core application data or
booking functionality is unavailable.

## 65.4 Suggested and Consequential Actions

Suggested actions should be visually secondary until the user chooses to act.
They must be labelled as suggestions where confusion is possible.

Embedded briefings should provide a concise summary, clear AI-generated label,
relevant source links and an **Ask AI** entry point where conversation would add
value. They must not visually displace the screen's authoritative status or
primary deterministic actions.

On Today's Schedule, **Booking arrived**, **Complete booking** and **Mark
no-show** remain normal explicit operational controls outside the AI
conversation.

If consequential AI-assisted actions are introduced in an approved later
scope, the existing confirmation-dialog pattern must show the authoritative
action, affected record and material effects before submission. The final
application outcome should be displayed using normal success, conflict or error
patterns rather than an AI response alone.

## 65.5 Responsive and Accessible Behaviour

At desktop and tablet widths, the right-hand drawer should be wide enough for
readable responses and structured results while retaining useful current-page
context. The layout should adapt when the remaining page area would become
unusable.

On smaller screens, use a bottom sheet or near-full-screen mobile drawer with a
clear drag/close affordance where appropriate. Prompt controls, structured
results, source links and confirmation actions must remain usable without
horizontal scrolling.

AI-assisted experiences must meet the same WCAG 2.2 AA target as the rest of
the product, including:

- keyboard operation and logical focus order;
- deliberate focus placement when the drawer/sheet opens;
- appropriate focus containment for the chosen drawer/sheet behaviour;
- returning focus to the invoking control when it closes;
- visible focus;
- an always-available keyboard-operable close control;
- accessible drawer/sheet names, labels and instructions;
- Escape-key closing where it does not interrupt a consequential action;
- status announcements for loading and completed responses;
- text alternatives for non-text content;
- meaning that does not depend on colour, animation or icons alone;
- restrained motion and support for reduced-motion preferences.

---

# 66. AI Coding Agent Rules

When implementing UI, AI coding agents MUST:

1. read `requirements.md`;
2. read `design.md`;
3. read `visual-design.md`;
4. read relevant architecture guidance;
5. inspect the relevant approved visual reference where available;
6. reuse existing components before creating new ones;
7. preserve established terminology;
8. preserve accessibility;
9. implement responsive behaviour;
10. implement loading/error/empty states where relevant;
11. avoid inventing business rules;
12. avoid introducing unsupported functionality;
13. avoid creating a new visual language for each page.

Agents MUST NOT treat generated mock-up content as authoritative business data.

If visual design and business requirements conflict, business requirements win.

If the required behaviour is unclear, the agent should identify the ambiguity rather than silently inventing behaviour.

---

# 67. Visual Acceptance Criteria

A UI implementation should not be considered complete merely because the page renders.

Before completion, verify:

### Visual consistency

- Uses established layout.
- Uses established typography.
- Uses established spacing.
- Uses established button hierarchy.
- Uses established status patterns.

### Behaviour

- Correct states are represented.
- Primary action is clear.
- Disabled actions are understandable.
- Validation is visible.
- Errors are recoverable where possible.

### Responsive

- Works at intended desktop width.
- Works at intended tablet width where applicable.
- Works at mobile width where required.
- No accidental horizontal overflow.
- Important actions remain accessible.

### Accessibility

- Keyboard operation works.
- Focus states are visible.
- Labels are present.
- Colour is not the only status indicator.
- Contrast is acceptable.
- Heading hierarchy is logical.

### Product correctness

- Terminology matches documentation.
- Customer privacy is preserved.
- Staff only see appropriate information.
- Mock-up artefacts have not become features.
- Fictional demonstration data is not presented as historical fact.

---

# 68. Visual Design Change Control

Small visual refinements may be made during implementation without updating this document.

Examples:

- spacing adjustments;
- minor typography tuning;
- responsive refinements;
- component sizing;
- icon choice.

Material changes should update this document where they alter an established visual pattern.

Examples:

- changing primary navigation structure;
- introducing a different visual identity;
- changing status semantics;
- changing the booking-flow presentation;
- changing major management layout conventions.

If the change alters user behaviour rather than only visual presentation, `design.md` and potentially `requirements.md` should be reviewed instead.

---

# 69. Final Design Principle

The visual system should support the underlying business objective:

> Make a complex multi-site booking operation feel simple to customers, manageable to administrators and immediately understandable to on-site staff.

Visual polish is important, but clarity and business correctness take priority.

The interface should make the right action obvious, the current state understandable and operational problems difficult to overlook.

---

# 70. Design Status

The representative visual-design phase is complete.

The approved screens establish sufficient visual direction to begin implementation.

Further bespoke mock-ups should only be created where implementation reveals a genuinely unresolved interaction or visual problem.

The project should now move into implementation according to `docs/implementation-plan.md`.
