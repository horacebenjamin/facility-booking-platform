# Facility Booking Platform — Design Specification

## 1. Purpose

This document defines the UX and interface design direction for the multi-site facility booking and operations platform.

It translates the approved product discovery and software requirements into design rules that should guide:

- public and customer-facing interfaces;
- management interfaces;
- leisure-assistant operational interfaces;
- wireframes;
- reusable components;
- responsive behaviour;
- accessibility;
- interaction states;
- implementation by developers and AI coding agents.

This document describes how the product should communicate and behave from a user-interface perspective.

It does not replace `discovery.md` or `requirements.md`.

Where documents serve different purposes:

- `discovery.md` explains the business context, historical process, problems, users and product opportunities;
- `requirements.md` defines what the system must or should do;
- `design.md` defines how those capabilities should be presented and experienced;
- future architecture documentation will define how the system is technically structured;
- future implementation planning will define the order in which capabilities are built.

Design decisions must not silently introduce new product requirements.

If design work exposes a missing or changed requirement, the requirements documentation should be reviewed explicitly before material implementation begins.

---

# 2. Design Objectives

## 2.1 Product Feel

The platform should feel modern, professional and trustworthy.

It should provide:

- a simple and intuitive customer booking experience;
- an efficient, information-focused management experience;
- a clear, operationally focused leisure-assistant experience.

The product should feel like a genuine commercial software platform rather than:

- a generic administration template;
- dated enterprise software;
- a portfolio demonstration;
- an unnecessarily complicated booking system.

Visual elements should support user tasks rather than exist purely for decoration.

---

## 2.2 Primary Design Goals

The three primary design goals are:

### Clarity

Availability, booking state, payment state and required actions should be easy to understand.

Users should not need knowledge of the organisation's internal processes to understand what is happening.

### Efficiency

Frequent workflows should minimise unnecessary steps, repeated data entry and navigation.

The interface should help users complete common tasks quickly while retaining appropriate safeguards for consequential actions.

### Trust

Users should have confidence that:

- availability is current;
- booking information is accurate;
- payments have a clear status;
- operational information is current;
- actions have predictable consequences.

Trust should come from clear information and reliable behaviour rather than decorative security claims.

---

# 3. Business Outcomes Supported by the UX

The interface should contribute to:

- increased customer self-service;
- reduced management administration;
- shorter enquiry-to-confirmation time;
- fewer booking and scheduling errors;
- easier and more reliable payment collection;
- improved facility utilisation;
- reduced dependence on manual communication;
- clearer operational information for on-site staff.

Design decisions should be evaluated against these outcomes rather than visual novelty alone.

---

# 4. User Outcomes

## 4.1 Customer / Hirer

Customers should be able to:

- discover suitable facilities;
- understand appropriate public availability;
- understand relevant pricing;
- request bookings;
- provide required booking information;
- make required payments;
- understand the current booking state;
- understand what happens next;
- manage appropriate aspects of existing bookings.

The customer experience should not require prior knowledge of the historical or internal booking process.

---

## 4.2 Manager

Managers should be able to:

- identify work requiring attention;
- review booking requests efficiently;
- understand availability and conflicts;
- approve or reject bookings;
- manage bookings and schedules;
- administer facilities and resources;
- manage operational exceptions;
- understand relevant financial information;
- respond to closures and operational issues.

The management experience should optimise repeat administrative work without sacrificing clarity.

---

## 4.3 Leisure Assistant

Leisure assistants should be able to:

- understand what is happening at their centre;
- see what is happening now;
- see what is happening next;
- identify required preparation;
- identify equipment requirements;
- recognise booking changes;
- record arrivals;
- record no-shows;
- record completion;
- report incidents or damage where appropriate.

The operational interface should minimise navigation and unnecessary administrative information.

---

# 5. Primary User Types and Usage Context

## 5.1 Customer / Hirer

Customer usage ranges from:

- first-time users;
- occasional users;
- regular individual hirers;
- recurring clubs and organisations.

No prior system training should be assumed.

Customers may use the platform from home or work and should be able to complete common journeys on both desktop and mobile devices.

Straightforward booking journeys should work well on mobile.

More complicated recurring activity may benefit from larger screens but should remain responsive.

---

## 5.2 Manager

Managers are repeat users.

They are expected to use the system frequently during active operating periods.

Desktop and laptop are the primary management environments because management workflows may involve:

- tables;
- schedules;
- booking comparisons;
- financial information;
- configuration.

Mobile and tablet use should still support appropriate quick checks and urgent actions.

---

## 5.3 Leisure Assistant

Leisure assistants are repeat operational users.

They may use the system while moving around a facility.

Mobile and tablet usability is therefore particularly important.

The core operational interface should require minimal training and should remain understandable to staff covering a centre they do not normally operate.

---

# 6. Core UX Principle

The interface should follow the business workflow rather than expose the underlying database structure.

The primary booking journey is:

**Discover → Check Availability → Select → Request → Provisional Hold → Management Review → Approval → Payment / Invoice → Confirmation → Operational Delivery → Completion**

Alternative outcomes may include:

- rejection;
- expiry;
- cancellation;
- closure;
- no-show;
- amendment.

Booking state, payment state and operational state should remain appropriately distinct.

The interface must not collapse these concepts into one ambiguous status.

---

# 7. Critical Status Communication

One of the highest UX risks is users misunderstanding the state of a booking.

The interface must clearly distinguish states such as:

- Requested;
- Awaiting Approval;
- Approved — Payment Required;
- Awaiting Payment;
- Confirmed;
- Rejected;
- Cancelled;
- Expired;
- Completed;
- No-show.

Exact status terminology must remain consistent once finalised.

Submitting a booking request must not visually or verbally imply that the booking is confirmed.

Where payment is required after management approval, approval must not be presented as equivalent to confirmation.

For example:

**Awaiting Payment**

may be accompanied by an explanation such as:

> Your booking request has been approved, but the booking is not confirmed until the required payment has been completed.

Status presentation should normally provide:

- the current state;
- a short explanation where required;
- the next action, if any.

---

# 8. Information Architecture

The platform should provide distinct experiences for:

1. Public visitors;
2. Customers;
3. Managers;
4. Leisure assistants.

These are different views of the same underlying booking system rather than independent applications with separate sources of truth.

---

# 9. Public Area

The public area should support:

- landing/home page;
- centre listing;
- centre details;
- facility details;
- facility discovery;
- appropriate public availability search;
- availability results.

Visitors should be able to discover facilities and view appropriate availability information without authentication.

Authentication should become necessary when an account-specific action is required, such as submitting a booking request.

Public availability must not expose customer identity or private booking information.

---

# 10. Customer Area

The authenticated customer area should include, as capabilities are implemented:

- customer dashboard;
- facility discovery;
- availability search;
- booking request workflow;
- booking request confirmation;
- My Bookings;
- booking details;
- appropriate recurring-booking views;
- payments;
- invoices;
- notifications;
- profile;
- account and security;
- notification preferences.

Organisation-related functionality should be introduced when the organisation capability enters implementation rather than unnecessarily expanding the initial MVP interface.

The customer area should be organised around the booking lifecycle rather than internal administrative entities.

---

# 11. Management Area

The management experience should support:

- dashboard;
- booking requests;
- booking review and approval;
- booking administration;
- schedule/calendar;
- manual bookings;
- centres;
- facilities;
- resources;
- equipment;
- opening/bookable hours;
- availability exceptions;
- blockouts;
- closures;
- customers;
- organisations;
- payments;
- invoices;
- operational incidents;
- damage records;
- reporting;
- appropriate configuration.

These capabilities do not all need to appear in the first implementation milestone.

Navigation should reflect implemented capabilities rather than displaying empty future modules.

---

# 12. Leisure Assistant Area

The leisure-assistant experience should remain deliberately limited.

Its principal areas should be:

- Today's Schedule;
- operational booking/session details;
- incident/damage recording where required.

Additional operational screens should only be introduced when a clear workflow requires them.

The leisure assistant should not receive a reduced copy of the complete management application.

---

# 13. Navigation

## 13.1 Public and Customer Navigation

Public and customer-facing interfaces should primarily use top navigation.

On smaller screens, navigation should collapse into an appropriate mobile pattern.

Provisional authenticated customer navigation:

- Home / Dashboard;
- Find a Facility;
- My Bookings;
- Payments & Invoices;
- Notifications.

Account-related secondary navigation may include:

- Profile;
- Account & Security;
- Notification Preferences;
- Organisation / Team settings where applicable.

Exact labels may be refined during wireframing.

---

## 13.2 Management Navigation

The management interface should primarily use:

- persistent left sidebar on desktop;
- lightweight top utility/context bar;
- flexible main content workspace.

Provisional management navigation:

- Dashboard;
- Bookings;
- Schedule;
- Facilities & Resources;
- Customers & Organisations;
- Finance;
- Operations;
- Reports.

Infrequent configuration should remain accessible without competing with frequent operational tasks.

---

## 13.3 Leisure Assistant Navigation

Navigation should remain minimal.

Provisional navigation:

- Today's Schedule;
- Bookings;
- Incidents.

Today's Schedule should normally act as the leisure assistant's home screen.

---

## 13.4 Role Differences

Navigation should differ significantly according to role.

Customers should receive booking-focused navigation.

Managers should receive administration and decision-focused navigation.

Leisure assistants should receive operational navigation.

Where a user can access multiple roles or contexts, the interface should provide an explicit context switch rather than merging every capability into one large menu.

Interface visibility does not replace server-side authorisation.

---

# 14. Navigation Supporting Patterns

Breadcrumbs should be used for deeper management and configuration hierarchies where they improve orientation.

Back navigation should preserve useful context where practical.

Useful shortcuts may include:

Customer:

- Find a Facility;
- Pay Now;
- View Booking.

Manager:

- Review Pending Requests;
- Create Manual Booking;
- View Schedule.

Leisure assistant:

- current booking;
- next booking;
- attendance action;
- incident reporting.

Recent items, favourites and saved shortcuts are not required for the MVP unless a demonstrated need emerges.

---

# 15. Application Shells

The product should use role-appropriate application shells built from a shared design language.

## 15.1 Public / Customer Shell

The public and customer-facing application should primarily use:

- top navigation;
- centred content;
- comfortable spacing;
- stronger use of facility imagery where useful.

The authenticated customer experience should remain visually related to the public experience rather than suddenly resembling an administration panel.

---

## 15.2 Management Shell

The management application should use:

- persistent sidebar on desktop;
- lightweight top utility/context bar;
- flexible main content area.

The workspace must support both:

- controlled-width detail/form pages;
- wide tables, calendars and schedules.

---

## 15.3 Leisure Assistant Shell

The leisure-assistant application should use a simplified responsive operational shell.

On mobile and tablet, Today's Schedule and current activity should receive maximum available screen space.

Navigation should remain minimal.

---

# 16. Content Width

Content width should adapt to the task.

Customer-facing content should generally use centred maximum-width containers.

Forms should use appropriately constrained widths rather than stretching inputs unnecessarily across large displays.

Management detail and form screens should use controlled widths where that improves readability.

Data-heavy management screens such as:

- tables;
- calendars;
- schedules;

may use substantially more horizontal workspace.

Leisure-assistant operational screens should make efficient use of the available viewport.

The design system should provide reusable layout/container patterns rather than arbitrary page-specific widths.

---

# 17. Page Structure

Pages should use a consistent hierarchy that makes the following immediately understandable:

- page purpose;
- current state;
- primary action;
- important context.

A typical structure may include:

1. Page title;
2. Status/context;
3. Primary action;
4. Supporting summary;
5. Main content;
6. Secondary/contextual information.

Content should be grouped into meaningful sections rather than presented as a continuous collection of fields.

Secondary information should receive lower visual priority.

Where useful on desktop, a contextual side panel may keep important information or actions visible alongside primary content.

Split layouts should only be used where they materially improve the workflow.

---

# 18. Primary Action Placement

Primary actions should appear in predictable locations and remain close to the context in which decisions are made.

Standard page-level actions should normally appear in the page header/action area on larger screens.

Examples include:

- Create Booking;
- Add Facility;
- Add Resource.

Workflow-specific actions should remain close to the relevant decision context.

For example, booking approval controls may belong in the booking review panel rather than being detached from the information being reviewed.

Customer journey actions should follow natural reading and interaction order.

Mobile layouts may use full-width or appropriately persistent actions where this materially improves usability.

Destructive or exceptional actions should be visually separated from normal primary actions.

---

# 19. Information Grouping

Sections should be the default way to group related information on detail and form pages.

Cards should represent genuinely distinct:

- records;
- summaries;
- actionable items.

Cards should not become the universal wrapper for every block of content.

Tabs should be used where users reasonably switch between substantial related views.

Important workflow information should not be hidden behind tabs merely to make a screen appear cleaner.

Accordions should primarily support:

- optional information;
- secondary information;
- infrequently required information;
- appropriate responsive disclosure.

Information required for an immediate decision should not be hidden unnecessarily.

Split panels should be used where persistent context materially improves a workflow.

---

# 20. Spacing and Visual Grouping

The interface should use a consistent spacing scale.

Spacing should communicate relationships.

Closely related elements should sit visually closer together.

Distinct sections should receive stronger separation.

Typography, spacing and alignment should provide the primary visual structure.

Borders, cards, dividers and background treatments should be used when they communicate a meaningful boundary rather than purely for decoration.

Customer and public interfaces may use more generous spacing.

Management interfaces may use greater information density.

Both should use the same underlying spacing system.

---

# 21. Critical Screen 1 — Facility Availability / Booking Selection

## Primary User

Customer / Hirer.

## Goal

Find a suitable available facility/resource and select an appropriate date/time to begin a booking request.

## Important Information

The screen should make relevant information such as the following clear:

- centre;
- facility;
- resource;
- date;
- available times;
- duration;
- appropriate resource options;
- indicative pricing where available.

## Primary Action

Select an available time/resource and continue.

## Secondary Actions

May include:

- change date;
- change centre;
- change activity;
- change facility;
- change duration;
- modify search;
- view facility details.

## Avoid

The screen should avoid:

- internal administrative terminology;
- exposing technical resource structure unnecessarily;
- unrelated functionality;
- excessive visual decoration.

Availability must be easy to interpret without requiring knowledge of how resources are stored internally.

---

# 22. Critical Screen 2 — Booking Request Review

## Primary User

Manager.

## Goal

Review a booking request and make an informed approval decision efficiently.

## Important Information

The screen should provide appropriate visibility of:

- customer / organisation;
- booking reference;
- centre;
- facility/resource;
- date;
- time;
- duration;
- activity;
- requested equipment;
- current availability;
- conflicts;
- relevant pricing;
- payment/billing context;
- booking notes.

## Primary Action

Approve booking request.

## Secondary Actions

May include:

- reject;
- propose or record an alternative where supported;
- authorised amendment;
- add notes;
- inspect customer;
- inspect schedule context.

## Avoid

The screen should avoid:

- decorative metrics;
- unrelated bookings;
- unrelated configuration;
- turning the review experience into a giant generic edit form.

The screen should be designed as a decision interface.

---

# 23. Critical Screen 3 — Today's Schedule

## Primary User

Leisure Assistant.

## Goal

Understand current and upcoming bookings and prepare/deliver them efficiently.

## Important Information

The screen should emphasise:

- current time;
- booking time;
- resource;
- customer / organisation where operationally appropriate;
- activity;
- setup requirements;
- equipment requirements;
- operational status;
- recent changes.

## Primary Action

Open/manage the current or upcoming booking.

## Secondary Actions

May include:

- record arrival;
- record completion;
- record no-show;
- inspect setup/equipment;
- report incident;
- report damage;
- inspect relevant booking changes.

## Avoid

The screen should avoid:

- unnecessary financial information;
- pricing details;
- reporting;
- unrelated centres;
- configuration.

The visual hierarchy should strongly emphasise:

**Now**

and

**Next**

Today's Schedule is the leisure assistant's operational dashboard rather than merely another data table.

---

# 24. Dashboard Design

## 24.1 Customer Dashboard

The customer dashboard should remain lightweight.

It should answer questions such as:

- What bookings are coming up?
- Is anything awaiting approval?
- Is payment required?
- Has anything changed?
- Do I need to take action?

Useful actions may include:

- Find / Book a Facility;
- Pay Now;
- View Booking.

The customer dashboard should not be filled with conventional business KPIs.

---

## 24.2 Manager Dashboard

The manager dashboard should be operational.

It should help answer:

- What needs attention?
- Are there booking requests waiting?
- What is happening today?
- Are there payment or invoice issues?
- Are there closures, conflicts or incidents requiring action?

Appropriate concise indicators may include:

- pending booking requests;
- today's confirmed activity;
- outstanding financial actions;
- active operational issues.

Historical analytics should primarily live in reporting rather than dominate the dashboard.

---

## 24.3 Leisure Assistant Dashboard

There should not be a separate conventional dashboard.

Today's Schedule should function as the leisure assistant's dashboard.

Business KPIs are not required.

Operational counts may be shown only where they directly help the user perform their shift.

---

# 25. Data Presentation

## 25.1 Tables

Tables are appropriate for management workflows requiring:

- scanning;
- comparison;
- filtering;
- sorting.

Examples include:

- bookings;
- requests;
- customers;
- organisations;
- payments;
- invoices;
- equipment;
- operational records.

Columns should remain task-focused.

Customer interfaces should use tables sparingly.

Today's Schedule should not default to a generic administration table.

---

## 25.2 Cards

Cards are appropriate for individual identity, summary or next-action information.

Examples include:

- facility discovery;
- customer upcoming bookings;
- pending customer actions;
- manager attention items;
- responsive operational schedule entries.

---

## 25.3 Lists and Timelines

Lists are useful for:

- notifications;
- recent activity;
- attention items;
- booking history summaries;
- chronological schedules.

Timelines may be appropriate for:

- booking lifecycle;
- status history;
- relevant chronological activity.

---

## 25.4 Charts

Charts should primarily support reporting and analysis.

Decorative charts should not be added to dashboards merely to make the product look more sophisticated.

---

## 25.5 Detail Panels

Detail views should expose full record/context appropriate to the user's role.

Summary views should communicate:

- identity;
- status;
- urgency;
- next action.

---

# 26. Search

Search should be contextual rather than universally exposed everywhere.

Customer discovery should support useful concepts such as:

- centre;
- activity / facility type;
- date;
- availability.

Customers should not need to understand internal resource identifiers.

Management search may support concepts such as:

- booking reference;
- customer;
- organisation;
- relevant financial reference.

Leisure assistants should primarily rely on the current operational schedule.

Additional operational search should only be introduced if useful.

---

# 27. Filters and Sorting

Customer-facing filters may include, where applicable:

- centre;
- activity;
- facility;
- date;
- time;
- duration;
- capacity;
- relevant equipment later.

Management filters may include:

- centre;
- resource;
- date range;
- booking status;
- payment status;
- customer;
- organisation;
- activity.

Defaults should match the task.

Examples:

- schedules → chronological;
- pending requests → urgency/age;
- operational schedule → current centre/day.

Saved filters, column customisation and advanced personalisation are not MVP requirements unless a demonstrated need emerges.

---

# 28. Forms and Data Entry

The most important customer form is the booking request.

It should be treated as a guided booking journey rather than a digital reproduction of the historical paper form.

Known information should be carried forward automatically.

Other important forms include:

- manager manual booking;
- amendment;
- cancellation;
- facility/resource administration;
- equipment administration;
- blockout;
- closure;
- financial actions;
- incident;
- damage.

Conditional information should only appear when relevant.

---

# 29. Form Complexity

Short, focused tasks may use single-page forms.

Examples may include:

- profile;
- preferences;
- simple administration;
- blockout;
- attendance;
- operational notes.

Decision-heavy or longer workflows should use meaningful grouping or steps.

Approval/rejection should remain contextual to the booking rather than becoming a large standalone form.

A rejection reason may appear conditionally when rejection is selected.

---

# 30. Customer Booking Journey

The conceptual booking request flow should be:

1. Facility & Time;
2. Booking Details;
3. Equipment / Add-ons where applicable;
4. Customer / Organisation context;
5. Price / Summary;
6. Review;
7. Submit.

The exact number of visual pages/steps may be refined during wireframing.

Recurring booking workflows may additionally require:

- recurrence definition;
- generated occurrences;
- conflict review.

Complex events may require additional information in later phases.

Manager manual booking should reuse relevant concepts rather than becoming an unrelated workflow.

---

# 31. Form Validation

Validation should happen as early as practical and should be specific.

Relevant validation may include:

- availability;
- parent/child resource conflicts;
- equipment quantity;
- permitted date/time;
- slot rules;
- duration rules;
- required customer information.

Availability and conflict-sensitive information must be revalidated at appropriate commitment points.

Recurring bookings should identify the specific occurrences that conflict rather than simply reporting that the entire series is invalid.

Field-level errors should appear close to the relevant field.

Consequential management actions should explain important consequences.

Client-side validation does not replace server-side and database safeguards.

---

# 32. Prefill and Derived Information

The interface should avoid unnecessary manual entry.

Known information should be prefilled or carried forward where appropriate.

Examples include:

- centre;
- resource;
- date;
- time;
- duration;
- customer profile;
- organisation.

The system should derive appropriate information such as:

- availability;
- duration;
- price;
- totals;
- recurrence dates;
- booking reference;
- status;
- timestamps.

The management manual-booking workflow should also reuse known data wherever possible.

The product should not recreate the repetitive data-entry characteristics of the historical spreadsheet process.

---

# 33. Drafts, Autosave and Review

A review/summary step should be provided before submitting an important booking request.

Simple MVP booking requests do not require draft/autosave functionality unless implementation demonstrates a need.

More complex event workflows may benefit from drafts in later phases.

Unsaved-change warnings should be used where abandoning a meaningful form could cause accidental data loss.

Confirmation dialogs should be reserved primarily for consequential or destructive actions rather than routine navigation.

---

# 34. Workflow Progress

Before booking submission, a concise step indicator may be used where the journey spans multiple meaningful stages.

After submission, customers should see a lifecycle/status representation appropriate to the booking.

The interface should always make the next required action understandable.

The representation must accommodate different paths such as:

- standard card payment;
- authorised invoice;
- rejection;
- cancellation;
- expiry.

Managers need clear state and available decisions.

Leisure assistants only need the operational state relevant to delivery.

---

# 35. Workflow Recovery

Valid user input should be preserved wherever practical when something goes wrong.

If availability changes during a booking journey:

- explain what happened;
- return the customer to an appropriate availability-selection state;
- preserve reusable information where possible.

Payment failure should result in an unambiguous state.

Where retry is permitted within the relevant deadline, the user should be able to retry without recreating the entire booking.

Expired holds and closures should explain:

- what changed;
- what effect it has;
- what options remain.

The interface should protect staff from accidental duplicate actions.

---

# 36. Consequential Actions

The level of confirmation should reflect the impact of the action.

Routine and easily reversible actions should not receive unnecessary confirmation.

Consequential actions such as:

- rejection;
- cancellation;
- closure;
- refund;
- financial override;
- major booking amendment;

should explain the impact before completion.

A reason should be requested where appropriate for auditability and operational understanding.

Where an action affects multiple bookings or occurrences, the interface should summarise the impact before confirmation.

---

# 37. Visual Direction

The product should use a modern, clean, professional commercial SaaS aesthetic.

The interface should emphasise:

- strong typography;
- clear hierarchy;
- restrained colour;
- consistent spacing;
- purposeful components.

It should avoid resembling:

- a generic admin template;
- dated enterprise software;
- an over-designed portfolio project.

Customer-facing areas may use:

- more facility imagery;
- greater whitespace;
- more expressive presentation.

Management areas should be:

- efficient;
- information-focused;
- moderately dense.

Operational areas should prioritise:

- scanability;
- time;
- resource;
- status;
- touch interaction.

All experiences should belong to the same overall design language.

---

# 38. Branding

## 38.1 Historical Context

The historical Active8 service had an existing visual identity and logo using blue and turquoise tones with a dynamic curved/swirl motif and the Active 8 name.

This identity provides historical context but should not be reused as the branding for the new portfolio product.

The redesigned platform should have its own fictional commercial brand and logo.

It should be clearly presented as an independently designed modern facility-booking and operations product rather than an official Active8, Interserve or school system.

Historical branding may inform the case-study background, but the new product identity should be original.

---

## 38.2 Colour Direction

The historical identity used blue and turquoise tones.

The new product may take loose inspiration from this direction while using an original palette that does not reproduce the historical branding.

The preferred direction is:

- modern blue/teal primary palette;
- neutral whites and greys;
- appropriate semantic colours.

The palette should communicate:

- trust;
- reliability;
- modernity;
- sufficient energy for a facility/venue product.

Exact colour values should be established when the design system is implemented and validated for accessibility.

---

## 38.3 Typography

The new product does not need to inherit historical typography.

It should use a modern sans-serif typeface providing:

- strong interface readability;
- clear hierarchy;
- readable body text;
- readable forms;
- strong date/time rendering;
- strong numerical and monetary rendering;
- suitable weight variation.

The exact typeface should be selected and tested during creation of the visual design system.

---

## 38.4 Brand Personality

The brand should be:

**Modern · Trustworthy · Efficient · Approachable · Professional**

It should feel dependable enough for operational and financial workflows while remaining understandable to occasional customers.

The product should not feel like either:

- corporate legacy software;
- an overly playful sports application.

---

## 38.5 Visual Treatments to Avoid

The product should avoid directly reproducing:

- the historical Active8 logo;
- the historical swoosh motif;
- the exact historical visual identity.

It should also avoid sports-specific branding that incorrectly positions the platform as exclusively a sports-booking product.

Avoid excessive:

- gradients;
- glass effects;
- neon palettes;
- unnecessary animation;
- oversized rounded components;
- shadows;
- decorative charts.

The visual style should remain purposeful, restrained and durable.

---

# 39. Imagery and Icons

Customer-facing areas may use realistic, purposeful facility imagery where it improves discovery.

Public portfolio/demo imagery should be fictional, licensed or otherwise appropriate for use.

It should not imply unauthorised affiliation with the historical organisation or schools.

Icons should be:

- consistent;
- purposeful;
- useful for scanning.

Text labels should accompany icons where meaning may be ambiguous.

Illustration should be used sparingly.

Facilities themselves are the primary visual content.

---

# 40. Typography and Density

The interface should use a limited, consistent typographic scale.

Customer-facing pages may use larger headings and more generous spacing.

Management screens may use more compact typography where appropriate.

Operational screens should emphasise:

- time;
- resource;
- booking status;
- important changes.

Text must remain comfortably readable.

Dates, times, prices and numerical information require particular clarity.

---

# 41. Reusable Component Strategy

The application should use reusable components for standard interface foundations.

Examples include:

- buttons;
- form controls;
- date/time inputs;
- selects;
- search controls;
- filters;
- tables;
- pagination;
- dialogs;
- drawers;
- tabs;
- accordions;
- tooltips;
- alerts;
- toasts;
- loading states;
- empty states;
- error states.

Standard interface primitives should not be recreated unnecessarily.

---

# 42. Domain-Specific Components

Repeated business concepts should receive reusable domain components or documented patterns.

Likely components include:

- Booking Status;
- Payment Status;
- Booking Summary;
- Booking Card;
- Facility Card;
- Resource Summary;
- Availability Slot Selector;
- Price Summary;
- Booking Review Summary;
- Booking Lifecycle Timeline;
- Schedule Entry;
- Now / Next Schedule Item;
- Equipment Requirement Summary;
- Conflict Warning;
- Availability Warning;
- Customer / Organisation Summary;
- Action Required Item;
- Date / Time Context;
- Centre / Resource Context.

Frequently repeated concepts must not be independently redesigned on every screen.

---

# 43. Component Variants

Components may provide controlled variants where the underlying concept remains the same.

Buttons should use a limited hierarchy such as:

- primary;
- secondary;
- subtle / tertiary;
- destructive.

Feedback components may use semantic variants such as:

- information;
- success;
- warning;
- error.

Booking and payment status components should represent defined business states consistently.

Booking summaries may adapt for:

- customer context;
- management context;
- operational context.

Schedule entries may distinguish:

- upcoming;
- current;
- completed;
- cancelled;
- recently changed.

New variants should only be introduced when they communicate a genuine difference in meaning or interaction.

---

# 44. Business-Critical Components

Components communicating or changing the following deserve additional design and usability attention:

- availability;
- booking state;
- payment state;
- operational state.

Particularly important components include:

- availability/time-slot selector;
- booking status;
- payment status;
- booking summary;
- manager approval controls;
- resource conflict warnings;
- equipment conflict warnings;
- price/payment summary;
- booking lifecycle display;
- Today's Schedule entries.

These components should:

- prioritise clarity over novelty;
- explain important consequences;
- work across required responsive layouts;
- never rely solely on colour;
- use consistent terminology.

---

# 45. Framework and Component Foundations

## 45.1 Management and Operational Interfaces

Management and leisure-assistant interfaces should use Filament as their primary interface foundation.

Standard Filament components and interaction patterns should be preferred where they adequately satisfy the requirement.

This includes appropriate use of existing:

- resources;
- forms;
- tables;
- filters;
- actions;
- notifications;
- widgets;
- navigation;
- responsive administration patterns.

The product should work with Filament rather than unnecessarily fighting the framework.

---

## 45.2 Custom Filament Experiences

Custom Filament pages or components should be introduced where the business workflow genuinely requires a specialised experience.

Today's Schedule is a primary example.

The leisure-assistant experience should use Filament infrastructure where useful without forcing staff to interact with a generic CRUD administration interface.

Other custom management workflows may be introduced where established resource/form/table patterns are insufficient for a critical decision or operational task.

---

## 45.3 Public / Customer Interface

The public and customer-facing application should use:

- Vue.js;
- TypeScript;
- shadcn/ui;

as its frontend/component foundation.

shadcn/ui should provide standard interface primitives where appropriate.

Custom domain components should be built for booking-specific interactions and presentation.

---

## 45.4 Cross-Interface Consistency

Filament and Vue/shadcn interfaces do not need to be pixel-identical.

They should share:

- brand direction;
- terminology;
- status semantics;
- accessibility principles;
- interaction hierarchy;
- product personality.

Attempting to force both frameworks into pixel-identical implementations should not create unnecessary development complexity.

---

# 46. Framework-Native First Principle

Established framework-native components and interaction patterns should be used where they adequately solve the UX requirement.

Custom design and engineering effort should concentrate on areas providing meaningful business value or domain differentiation.

The product should not spend significant implementation effort recreating solved interface primitives such as:

- standard buttons;
- basic form fields;
- pagination;
- generic tables;
- ordinary dialogs.

Bespoke effort should instead focus on areas such as:

- availability;
- resource relationships;
- booking workflow;
- booking review;
- status communication;
- operational scheduling.

---

# 47. Application States

Every important reusable component should consider relevant states where applicable.

These may include:

- default;
- loading;
- empty;
- disabled;
- processing;
- success;
- warning;
- error.

The interface should communicate both:

1. what happened;
2. what the user can do next.

---

## 47.1 Loading

Use contextual loading states where practical rather than unnecessarily blocking the entire application.

Tables and lists may use appropriate loading indicators or skeletons.

Consequential actions should clearly communicate processing and prevent accidental duplicate submission.

---

## 47.2 Empty States

Empty states should explain what the absence of data means.

Where appropriate, they should provide the next useful action.

Example:

**You don't have any upcoming bookings.**

Action:

**Find a Facility**

Avoid displaying only generic messages such as:

**No records found.**

---

## 47.3 Success States

Successful actions should explain:

- what happened;
- what happens next.

Booking request submission must not imply confirmation unless the booking has actually reached the confirmed state.

---

## 47.4 Error States

Errors should be:

- specific;
- understandable;
- recoverable where possible.

Valid user input should be preserved.

Availability conflicts should explain that the selected resource/time is no longer available and provide a route back to availability selection.

---

## 47.5 Warning / Attention States

Warning states may communicate conditions such as:

- payment deadlines;
- recently changed bookings;
- equipment issues;
- operational changes;
- unresolved actions.

Warnings should not be used so frequently that they lose meaning.

---

## 47.6 Disabled / Unavailable States

Where useful, explain why an action or resource is unavailable.

Users should not be left unnecessarily guessing why a control cannot be used.

---

## 47.7 Partial / Degraded States

Failure of one non-critical component should not unnecessarily make an entire page unusable.

Where practical, unaffected information should remain available with a clear indication of what could not be loaded.

---

# 48. Responsive Behaviour

Responsive design should adapt the workflow rather than merely shrink the desktop layout.

---

## 48.1 Customer

Mobile is a first-class customer experience.

Customers should be able to:

- discover facilities;
- check availability;
- submit straightforward booking requests;
- pay;
- manage bookings;

comfortably from a phone.

Facility cards, availability selection, forms and booking summaries should reflow appropriately.

---

## 48.2 Manager

Desktop/laptop is the primary management environment.

Management interfaces should remain appropriately responsive for:

- quick checks;
- today's activity;
- urgent actions;
- appropriate tablet/mobile use.

Complex administration does not need to be artificially transformed into a phone-first experience.

Existing Filament responsive behaviour should be used where suitable.

---

## 48.3 Leisure Assistant

Mobile and tablet are particularly important.

Today's Schedule must work well on smaller touch devices.

It should emphasise:

- Now;
- Next;
- location/resource;
- setup requirements;
- status;
- important changes.

Core operational use should not depend on horizontal scrolling.

---

## 48.4 Responsive Tables

Data-heavy tables may:

- hide lower-priority columns;
- provide responsive detail views;
- use deliberate horizontal scrolling where genuinely required.

The interface should not attempt to squeeze large desktop tables into unusable mobile layouts.

---

## 48.5 Touch

Important operational controls should use suitable touch targets and spacing.

Critical actions must not depend on:

- hover;
- tiny controls;
- precision pointer interaction.

---

# 49. Accessibility

The target accessibility standard is:

**WCAG 2.2 AA**

Accessibility should be considered during design and implementation rather than added as a final QA task.

---

## 49.1 Keyboard

Core workflows should be usable without a mouse.

Focus order should follow logical visual/task order.

Visible focus states are required.

Custom controls such as availability selectors require deliberate keyboard behaviour.

---

## 49.2 Colour and Contrast

Text and controls should provide sufficient contrast.

Status must not rely solely on colour.

For example:

- Confirmed;
- Awaiting Payment;
- Cancelled;

must have explicit textual or otherwise accessible meaning.

---

## 49.3 Forms

Fields should provide:

- clear labels;
- understandable instructions;
- appropriate required-state communication;
- understandable validation errors.

Errors should be programmatically associated with relevant controls where applicable.

---

## 49.4 Semantic Structure

Interfaces should use appropriate semantic:

- headings;
- landmarks;
- buttons;
- links;
- tables;
- forms.

Assistive technology should be able to understand page structure and controls.

---

## 49.5 Dynamic Interactions

Dynamic elements such as:

- dialogs;
- validation updates;
- notifications;
- loading states;
- booking updates;

should use appropriate focus management and accessible announcements where necessary.

---

## 49.6 Motion

Animation should remain purposeful and restrained.

Reduced-motion preferences should be respected where appropriate.

Motion must not be required to understand state.

---

## 49.7 Accessible Component Libraries

Accessible behaviour provided by Filament and shadcn/ui or their underlying primitives should be used where appropriate.

Using an accessible component library does not automatically make the completed workflow accessible.

Custom domain components require deliberate accessibility review.

---

# 50. Content and Microcopy

The tone should be:

- clear;
- professional;
- concise;
- approachable.

Customer-facing language should avoid unnecessary internal terminology.

Manager and operational interfaces may use more domain-specific language where that improves efficiency.

---

## 50.1 Status Terminology

The same business state should use the same terminology everywhere.

Do not use different names for the same booking/payment state on different screens.

---

## 50.2 Action Labels

Use specific verbs where the action matters.

Prefer:

- Submit Booking Request;
- Approve Booking;
- Pay £45.00;
- Cancel Booking;
- Record No-show.

Avoid vague labels such as:

- OK;
- Submit;
- Continue;

where a more specific action label would reduce ambiguity.

---

## 50.3 Error Copy

Errors should explain:

- what happened;
- what the user should do next.

Avoid generic technical messages in customer-facing workflows.

---

## 50.4 Confirmation Copy

Confirmation messages should clearly distinguish:

- request submitted;
- request approved;
- payment completed;
- booking confirmed.

Do not accidentally imply confirmation at an earlier lifecycle stage.

---

## 50.5 Consequential Action Copy

Confirmation copy for consequential actions should describe the actual effect.

Avoid relying solely on generic:

**Are you sure?**

---

## 50.6 Dates, Times and Money

Dates, times and prices should use clear, consistent formats appropriate to a UK product.

Important booking information should favour clarity over unnecessary compactness.

---

# 51. Feedback

Feedback should be displayed at a level appropriate to its importance.

Use:

- transient feedback for minor successful actions;
- inline feedback for contextual problems;
- persistent alerts for unresolved conditions requiring attention.

---

## 51.1 Toasts

Toasts may be used for lightweight confirmation where the result is already visible.

They should not be the only location of important information.

---

## 51.2 Inline Feedback

Use inline feedback for:

- validation;
- availability conflicts;
- payment problems;
- contextual issues requiring action.

---

## 51.3 Persistent Alerts

Use persistent alerts for unresolved conditions such as:

- payment required;
- closure impact;
- expired reservation;
- significant operational issue.

---

# 52. Notifications

Notifications should be:

- actionable;
- role-appropriate;
- specific;
- restrained enough to avoid unnecessary notification fatigue.

A useful notification should answer:

- What happened?
- Which booking does it affect?
- Is action required?
- Where should the user go next?

---

## 52.1 Customer Notifications

Initial customer notification events may include:

- request submitted;
- request approved;
- request rejected;
- payment required;
- payment successful;
- booking confirmed;
- booking amended;
- booking cancelled;
- closure / operational change;
- appropriate reminders.

Email and in-app notifications are the initial channels.

SMS remains a later capability.

---

## 52.2 Manager Notifications

Manager notifications should prioritise actionable events such as:

- booking requests requiring review;
- relevant financial issues;
- operational exceptions.

Managers should not receive unnecessary notifications for every routine state transition.

---

## 52.3 Leisure Assistant Notifications

Operational notifications should focus on changes affecting current delivery.

Examples include:

- changed bookings;
- cancellations;
- closures;
- setup changes;
- equipment changes;
- relevant centre/day operational information.

---

# 53. Trust, Privacy and Sensitive Information

The interface should expose only information appropriate to the user's task and permissions.

Customers should only access:

- their own information;
- organisation information they are authorised to access.

Managers may receive broader information according to role.

Leisure assistants should receive the information necessary to operate bookings without unnecessary access to administrative or financial information.

---

## 53.1 Public Availability Privacy

Public availability may communicate whether a resource is:

- available;
- unavailable.

It must not reveal:

- customer identity;
- organisation identity where inappropriate;
- private booking details.

---

## 53.2 Payment Trust

Payment interfaces should clearly communicate:

- amount;
- purpose;
- payment state.

Card details should be handled by the selected payment provider rather than unnecessarily stored or processed by the application.

---

## 53.3 Sensitive Information

Do not display personal information merely because it exists in the database.

Operational screens should expose only the customer/organisation information necessary to deliver the booking.

---

## 53.4 Security UX

Authentication, authorisation and ownership isolation must be enforced server-side.

Hiding a button is not an access-control mechanism.

---

## 53.5 Auditability

Appropriate authorised users should be able to understand consequential actions such as:

- approvals;
- overrides;
- cancellations;
- financial changes;
- operational exceptions.

The interface should expose appropriate history/context where required.

---

## 53.6 Trust Claims

Avoid unsupported claims such as:

**100% secure**

or decorative security badges without meaning.

Trust should result from:

- professional presentation;
- transparent workflows;
- clear state;
- correct system behaviour;
- appropriate security implementation.

---

# 54. AI UX

AI is not a core UX requirement for the initial product.

Critical workflows should use deterministic application logic.

These include:

- availability;
- conflict prevention;
- pricing;
- booking approval;
- payment state;
- booking-state transitions.

The MVP should not introduce an AI assistant or conversational interface merely to position the product as AI-powered.

Potential later AI capabilities may include:

- management operational summaries;
- utilisation insights;
- incident summarisation;
- facility-discovery assistance.

Any future AI output should remain distinguishable from authoritative booking and operational data.

AI should augment users rather than become a dependency for completing essential workflows.

AI may still be used extensively during software development as an engineering productivity tool.

---

# 55. Wireframing Strategy

Wireframing effort should be proportional to UX and business risk.

Critical customer, management and operational workflows should be wireframed before implementation.

Standard administrative CRUD interfaces may use established Filament patterns without bespoke wireframes unless the workflow requires custom interaction.

Initial wireframes should validate:

- information hierarchy;
- workflow;
- status communication;
- primary actions;
- responsive behaviour.

Visual polish should come after these fundamentals are validated.

---

## 55.1 Customer Wireframes

Priority customer wireframes should cover the journey:

**Facility Discovery → Facility Detail → Availability Selection → Booking Request → Review & Submit → Request Status / Payment → Confirmed Booking**

---

## 55.2 Manager Wireframes

Priority management wireframes should include:

- Dashboard;
- Booking Request List;
- Booking Request Review;
- Schedule / Calendar;
- Manual Booking.

---

## 55.3 Leisure Assistant Wireframes

Priority operational wireframes should include:

**Today's Schedule → Booking / Session Detail → Arrival / No-show / Completion → Incident / Damage Reporting**

---

## 55.4 Highest-Priority Wireframes

The three highest-priority screens are:

1. Facility Availability / Booking Selection;
2. Booking Request Review;
3. Today's Schedule.

These should receive particular attention before custom interface implementation.

---

# 56. Implementation Consistency

The product should use a shared set of design rules across interface technologies.

These include:

- typography;
- spacing;
- semantic colour;
- status terminology;
- interaction hierarchy;
- accessibility.

Framework-specific implementations may differ while preserving the same product language.

---

## 56.1 Reuse Before Creation

Implementation should prefer:

**Reuse → Configure → Extend**

before creating a new pattern.

Existing Filament or shadcn/ui components should be used where appropriate.

Repeated domain concepts should use established domain components.

---

## 56.2 AI Coding Agent Constraints

AI coding agents should not independently introduce:

- new colours;
- new spacing conventions;
- competing button hierarchies;
- alternative status terminology;
- arbitrary page layouts;
- duplicate components;
- new component variants;

simply because they appear locally appropriate.

New patterns should be deliberate design decisions.

---

## 56.3 Design Tokens

Reusable visual values should be represented through appropriate design-system tokens or central configuration rather than scattered arbitrary values.

These may include:

- typography;
- spacing;
- radii;
- semantic colours;
- other recurring visual decisions.

Exact implementation will be determined during architecture and frontend setup.

---

## 56.4 Exceptions

Where a screen genuinely needs to break an established pattern, the reason should be intentional.

Exceptions should not arise accidentally from independent implementation choices.

---

# 57. Design Acceptance Criteria

A design should be considered implementation-ready when the following are sufficiently defined.

## 57.1 Purpose

The intended user and goal are clear.

## 57.2 State

The current state is understandable.

Critical booking states cannot reasonably be confused.

## 57.3 Actions

Primary and secondary actions have clear hierarchy.

Consequential actions receive appropriate treatment.

## 57.4 Application States

Relevant:

- loading;
- empty;
- error;
- validation;
- disabled;
- processing;
- success;

states have been considered.

## 57.5 Responsive Behaviour

The design works appropriately for its target usage context.

Customer workflows consider mobile and desktop.

Management workflows prioritise productive desktop use.

Leisure-assistant workflows particularly consider mobile/tablet.

## 57.6 Accessibility

The design supports the WCAG 2.2 AA target.

Relevant considerations include:

- keyboard access;
- visible focus;
- contrast;
- labels;
- non-colour state communication.

## 57.7 Component Reuse

Existing Filament or shadcn/ui components are used where appropriate.

Custom UI exists because the domain workflow requires it.

## 57.8 Terminology

Established booking and payment terminology is reused consistently.

## 57.9 Privacy

Only role-appropriate information is exposed.

## 57.10 Traceability

The screen supports a real requirement/workflow.

Design should not introduce functionality merely because it appears useful.

## 57.11 Implementation Clarity

A developer or coding agent should be able to implement the screen without inventing major UX behaviour.

A polished default desktop screenshot alone does not make a design implementation-ready.

---

# 58. Design Risks and Unknowns

## 58.1 Availability UX Complexity

Representing:

- whole facilities;
- subdivided resources;
- consecutive slots;
- variable durations;
- equipment availability;

without overwhelming customers requires validation through wireframing.

---

## 58.2 Recurring Bookings

Recurring bookings introduce complexity around:

- multiple occurrences;
- partial conflicts;
- occurrence-specific changes;
- occurrence-specific cancellations.

The interface should remain understandable before sophisticated recurring controls are introduced.

---

## 58.3 Booking Review Density

Managers need enough information to make informed decisions without creating an excessively dense review screen.

Booking Request Review therefore requires focused wireframing.

---

## 58.4 Today's Schedule

The required operational information is understood, but the exact mobile/tablet layout should be validated through wireframes and implementation prototypes.

---

## 58.5 Filament Constraints

Management and operational designs should work with Filament where practical.

If a design requires extensive framework customisation for limited business value, simplifying the design should be considered.

---

## 58.6 Customer Availability Component

The availability/resource selection experience is likely to be one of the most important and challenging custom Vue components.

It requires focused UX and accessibility attention.

---

## 58.7 Historical Policy Uncertainty

Some policies remain deliberately configurable or TBD.

Examples include:

- cancellation policy;
- exact opening hours;
- exact pricing;
- some operational rules.

The UX should not invent historical facts to resolve these uncertainties.

---

## 58.8 Later-Phase Complexity

Capabilities such as:

- advanced organisation permissions;
- sophisticated event management;
- compliance documentation;
- advanced billing;
- waiting lists;
- advanced analytics;

should not distort the MVP interface before they enter implementation.

---

## 58.9 Cross-Interface Consistency

Filament and Vue/shadcn will naturally have differences.

Consistency should focus on:

- product language;
- brand;
- semantics;
- interaction principles;

rather than unnecessary pixel-level duplication.

---

## 58.10 Accessibility of Custom Controls

Custom:

- calendars;
- availability selectors;
- resource selectors;

require deliberate keyboard and assistive-technology testing.

---

## 58.11 Operational Mobile Use

The leisure-assistant experience should be tested on realistic phone and tablet sizes rather than only by resizing a desktop browser.

---

## 58.12 Design Scope Creep

Ideas discovered during design should not automatically become requirements or MVP features.

They should be evaluated against:

- business problem;
- discovery evidence;
- user value;
- MVP scope;
- complexity;
- impact.

Where a design decision requires a new product capability, the requirements documentation should be updated explicitly before implementation.

---

# 59. Final Design Summary

The platform should feel like a modern, trustworthy and commercially credible multi-site facility booking and operations product.

It should replace fragmented manual processes with:

- clear customer self-service;
- reliable management workflows;
- current operational information.

The experience should prioritise:

**Clarity · Efficiency · Trust**

over visual novelty.

Users should always be able to understand:

- the current state;
- whether action is required;
- what happens next.

---

## 59.1 Customer Experience Summary

Customers should receive a polished, accessible and mobile-friendly experience focused on:

- discovering facilities;
- understanding availability;
- requesting bookings;
- completing payment;
- managing bookings.

Customers should not need to understand the organisation's internal administrative structure.

---

## 59.2 Management Experience Summary

Managers should receive an efficient, information-focused application for:

- booking requests;
- schedules;
- facilities/resources;
- availability;
- customers;
- finance;
- operational exceptions.

The interface should prioritise:

- pending work;
- decisions;
- exceptions;

rather than decorative dashboards.

---

## 59.3 Leisure Assistant Experience Summary

Leisure assistants should receive a deliberately simplified operational experience centred on Today's Schedule.

The interface should answer:

- What is happening now?
- What is happening next?
- Where is it happening?
- What needs preparing?
- Has anything changed?
- What operational action is required?

---

## 59.4 Technology and Component Summary

Management and operational interfaces should use Filament as their primary UI foundation.

Standard Filament components should be preferred where appropriate.

Custom Filament experiences should be introduced where the domain workflow requires them, particularly Today's Schedule.

The public/customer interface should use:

- Vue.js;
- TypeScript;
- shadcn/ui;

with custom domain components for booking-specific UX.

The interfaces do not need to be pixel-identical.

They should share:

- branding;
- terminology;
- status semantics;
- accessibility principles;
- overall product language.

---

## 59.5 Visual Summary

The product should use an original fictional commercial identity.

The historical Active8 identity may provide contextual inspiration but should not be reproduced.

The preferred visual direction is:

- modern;
- professional;
- approachable;
- restrained;
- blue/teal-oriented;
- supported by neutral colours;
- strong typography;
- clear hierarchy.

The interface should avoid unnecessary visual trends that compromise clarity or make the product appear like a generic SaaS template.

---

## 59.6 Workflow Summary

The design should reflect the business workflow:

**Discover → Check Availability → Select → Request → Provisional Hold → Management Review → Approval → Payment / Invoice → Confirmation → Operational Delivery → Completion**

The user interface should make transitions between these states understandable.

---

## 59.7 Implementation Summary

Established framework components should solve standard interface problems.

Bespoke design and engineering effort should concentrate on the areas that differentiate the product and carry business risk.

These include:

- availability;
- resource conflicts;
- booking state;
- approval;
- payment state;
- operational scheduling.

Implementation should prefer reusable components and patterns over one-off solutions.

AI coding agents should follow the documented design rules rather than independently inventing competing patterns.

---

## 59.8 Quality Summary

The design should target:

- WCAG 2.2 AA;
- appropriate responsive behaviour;
- clear application states;
- role-appropriate information exposure;
- consistent consequential-action handling;
- implementation-ready critical workflows.

The most important custom workflows should be wireframed before implementation:

1. Facility Availability / Booking Selection;
2. Booking Request Review;
3. Today's Schedule.

A screen is not implementation-ready merely because it looks polished.

Its:

- hierarchy;
- behaviour;
- states;
- responsive expectations;
- accessibility expectations;
- important interactions;

should be sufficiently defined that implementation does not require major UX decisions to be invented during coding.

---

# 60. Design Principle

> **The purpose of the interface is not to demonstrate how much UI can be designed. It is to make a complex real-world booking and operations process feel simple, reliable and efficient to each person using it.**

---

# 61. Relationship to Future Documentation

This design specification should inform the next stages of planning.

The intended documentation sequence is:

**Discovery → Requirements → Design → Architecture → Implementation Plan → Agent Instructions → Implementation**

The architecture stage should determine technical matters including:

- Laravel application structure;
- Filament panel structure;
- Vue integration approach;
- authentication boundaries;
- role and permission architecture;
- domain model;
- centre/facility/resource relationships;
- availability calculation;
- booking lifecycle implementation;
- concurrency strategy;
- payment integration;
- invoicing boundaries;
- notifications;
- queues;
- audit logging;
- testing strategy.

Those technical decisions should not be invented in this design document unless they are necessary to enforce an approved UX requirement.

---

# 62. Design Status

This document represents the approved design direction produced from the completed design and UX discovery process.

The major UX principles, role experiences, critical workflows, interface foundations and design constraints are sufficiently defined to proceed to architecture planning.

Remaining visual details such as:

- final product name;
- final logo;
- exact colour values;
- exact typography;
- detailed wireframes;
- final component styling;

may be resolved during the appropriate design-system and wireframing work without reopening the fundamental product design decisions documented here.

Material changes to workflows or product capabilities should be reflected in the relevant requirements documentation before implementation.