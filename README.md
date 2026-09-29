# Facility4Hire

## AI-Augmented Multi-Site Facility Booking & Operations Platform

Facility4Hire is an AI-augmented multi-site facility booking and operations platform designed to replace manual availability checks, booking forms, spreadsheets, payment tracking and static staff schedules with a centralised digital system.

The project is based on first-hand experience of a real-world community facility hire operation, supported by retrospective discovery with a former manager and historical operational documentation.

Alongside the core booking and operations platform, Facility4Hire uses AI-assisted workflows to help managers and staff query operational data, surface insights, summarise activity and interact more efficiently with the system.

> **Project status:** In active development. Discovery, requirements, UX design, visual design, architecture and implementation planning are complete. Application implementation is now underway.

---

## Overview

Facility4Hire is designed for organisations that manage multiple hireable venues and facilities.

Examples include:

- sports centres;
- community venues;
- school facilities;
- sports halls;
- pitches and courts;
- meeting and conference spaces;
- event spaces.

The platform connects three parts of the operation:

**Customers** can discover facilities, check availability, request bookings and manage payments.

**Managers** can control availability, review booking requests, manage facilities, pricing, payments and operational changes.

**On-site staff** can use a live schedule to see upcoming bookings, prepare facilities and equipment, record booking arrival, complete sessions and report operational issues.

**AI-assisted workflows** help authorised users query operational information, generate summaries, surface useful insights and interact with the platform using natural language.

The objective is to create a single reliable source of truth for bookings, availability, payments and day-to-day venue operations while using AI to make that information easier to understand and act upon.

---

## The Business Problem

The project was inspired by a real multi-site community facility hire operation where many processes depended on manual administration.

The historical workflow included:

- customers contacting the office to ask about availability;
- paper or document-based booking applications;
- staff manually checking availability;
- bookings recorded in spreadsheets;
- managers manually approving requests;
- static booking schedules supplied to on-site staff;
- last-minute changes communicated separately;
- cash and cheque payments;
- manual receipt handling;
- manual payment reconciliation;
- limited customer self-service.

This created several operational problems:

- repetitive manual administration;
- limited customer visibility of availability;
- risk of booking conflicts;
- manual payment handling and reconciliation;
- fragmented communication with on-site staff;
- information spread across multiple manual processes.

---

## The Solution

Facility4Hire replaces these disconnected processes with a centralised platform.

A typical booking journey becomes:

```text
Customer
   ↓
Discover Centre / Facility
   ↓
Check Availability
   ↓
Select Bookable Resource
   ↓
Submit Booking Request
   ↓
Provisional Reservation
   ↓
Manager Review
   ↓
Approval
   ↓
Payment / Invoice Arrangement
   ↓
Booking Confirmed
   ↓
Live Staff Schedule
   ↓
Booking Arrived
   ↓
Session Completed
```

The platform preserves management control where operational judgement is required while automating repetitive administration wherever practical.

AI-assisted capabilities sit alongside these workflows to help authorised users understand and interact with operational data more efficiently.

---

## Core Capabilities

### Facility Discovery

Customers can browse centres, facilities, bookable resources, availability and pricing.

Facilities can contain independently bookable resources.

For example:

```text
Sports Hall
├── Whole Sports Hall
├── Half Sports Hall
├── Badminton Court 1
├── Badminton Court 2
├── Badminton Court 3
└── Badminton Court 4
```

The availability model is designed to prevent incompatible parent and child resources from being booked simultaneously.

### Availability Management

Availability can account for:

- confirmed bookings;
- provisional reservations;
- centre operating hours;
- facility-specific bookable hours;
- school use;
- maintenance;
- temporary closures;
- health and safety restrictions;
- weather closures;
- management blocks;
- exceptional operating hours.

This allows the application to act as the authoritative source of facility availability.

### Booking Requests

Customers can submit booking requests containing information such as:

- centre;
- facility;
- bookable resource;
- date;
- start and end time;
- activity;
- participant information;
- equipment requirements;
- additional booking information.

Checking availability and submitting a booking request are separate actions.

A request is not presented as confirmed until the required approval and commercial steps have been completed.

### Recurring Bookings

The platform is designed to support recurring bookings such as weekly sports clubs, training sessions, community groups and recurring meetings.

Individual occurrences can be validated so that an unavailable date does not necessarily invalidate the entire recurring series.

### Management Approval

Managers can review booking requests before approval.

Relevant information may include:

- customer and organisation;
- centre;
- facility and resource;
- dates and times;
- recurring occurrences;
- equipment;
- availability conflicts;
- pricing;
- payment arrangement;
- booking notes.

### Payments and Invoicing

The modernised platform is designed to support:

- online card payments;
- Stripe integration;
- invoice-based bookings where authorised;
- payment tracking;
- receipts;
- payment status;
- future support for deposits and more advanced billing arrangements.

Booking status and financial status are treated as separate concepts.

For example, an approved organisation may have a confirmed booking while payment is handled under agreed invoice terms.

### Equipment Management

Facilities may require equipment such as:

- badminton nets;
- football goals;
- hockey goals;
- volleyball equipment;
- basketball equipment.

The platform is designed to track quantities so overlapping bookings cannot allocate more equipment than is available.

Equipment requirements can also be surfaced to operational staff before a booking begins.

### Live Operations

Authorised operational staff receive a centre-specific **Today's Schedule**.

The operational interface can provide:

- booking time;
- facility/resource;
- activity;
- relevant booking information;
- equipment requirements;
- setup information;
- operational notes;
- current status.

Staff can record operational events such as:

- **Booking arrived**;
- booking completed;
- no-show;
- incident;
- equipment or facility damage.

### Closures and Availability Blocks

Managers can make facilities unavailable because of circumstances such as:

- maintenance;
- school requirements;
- weather;
- health and safety issues;
- exceptional closures.

Affected bookings can then be identified for appropriate operational and customer follow-up.

### Reporting

The wider product direction includes reporting across areas such as:

- bookings;
- revenue;
- centre utilisation;
- facility utilisation;
- payments;
- invoices;
- cancellations;
- no-shows.

The objective is to give management better operational visibility than spreadsheet-based administration.

### AI & Intelligent Assistance

AI is a first-class capability of Facility4Hire and is intended to demonstrate how AI can be integrated into a production-style business application.

The platform will use the **Laravel AI SDK** to provide AI-assisted workflows across management and operations.

Planned capabilities include:

- natural-language querying of booking and operational information;
- management summaries and operational insights;
- utilisation and booking-pattern analysis;
- cancellation and no-show summaries;
- assisted facility and availability discovery;
- operational briefing generation;
- contextual assistance using authorised application data.

Rather than operating as an isolated chatbot, the AI layer will interact with controlled application tools and domain services.

For example:

```text
Manager
   ↓
AI Assistant
   ↓
Laravel AI SDK
   ↓
Authorised Application Tools
   ↓
Booking / Availability / Reporting Services
   ↓
Application Database
```

This allows the AI assistant to work with real application context while respecting existing permissions, validation and business rules.

The AI layer can help users ask questions such as:

- Which facilities have been underutilised recently?
- Summarise today's operational issues across all centres.
- Which centres have experienced the most no-shows?
- Find suitable availability for a requested activity and time.
- Summarise recent cancellation patterns.
- What bookings or operational issues require management attention today?

Critical operations such as preventing booking conflicts, allocating resources, processing payments and changing authoritative booking state remain controlled by application services.

AI assists users in understanding, discovering and working with application information while the underlying domain remains authoritative.

---

## User Types

### Customer

Customers can:

- manage their profile;
- browse centres and facilities;
- check availability;
- request bookings;
- request equipment;
- manage bookings;
- make payments;
- view relevant invoices and receipts.

### Manager

Managers can oversee one or more centres and perform functions such as:

- manage centres;
- manage facilities and resources;
- manage equipment;
- configure opening hours;
- configure availability;
- review booking requests;
- approve or reject requests;
- create manual bookings;
- manage closures;
- manage pricing;
- monitor payments;
- manage invoices;
- view operational information;
- access reporting;
- use authorised AI-assisted management workflows.

### Leisure Assistant

Operational staff receive a deliberately focused interface for:

- Today's Schedule;
- operational booking details;
- setup requirements;
- equipment requirements;
- booking arrival;
- completion;
- no-show recording;
- incident reporting;
- damage reporting;
- authorised AI-assisted operational workflows where appropriate.

Administrative and financial functionality is kept separate from the normal operational workflow.

---

## Product Design

Facility4Hire separates the product into three related experiences.

### Public / Customer

Designed to be approachable, responsive and focused on facility discovery and booking.

### Management

Designed to be operational, efficient and suitable for managing multiple centres.

### Operations

Designed around mobile and tablet use, fast scanning, touch-friendly actions and minimal administrative complexity.

This is particularly important for staff working around a venue rather than sitting permanently at a desk.

### AI-Assisted Experience

AI functionality should be integrated into relevant workflows rather than existing as an unrelated demonstration.

The AI experience should:

- respect the permissions of the authenticated user;
- work with authorised application data;
- use application tools and services;
- provide useful context and summaries;
- support natural-language interaction;
- make complex operational information easier to understand.

The goal is to demonstrate practical AI engineering within a real business application.

---

## Technical Direction

Facility4Hire is being designed as a modular Laravel application with an integrated AI application layer.

The planned technology direction includes:

### Backend

- Laravel
- PHP
- MySQL
- Redis

### Public / Customer Frontend

- Inertia.js
- Vue
- TypeScript
- Tailwind CSS
- shadcn-style component primitives

### Management / Operations

- Filament

### Payments

- Stripe
- Laravel Cashier

### AI

- Laravel AI SDK
- tool-enabled AI agents
- structured AI outputs
- application/domain tools
- conversational context where appropriate
- Ollama for local AI development and testing where appropriate
- production AI providers supported through the Laravel AI SDK

The AI architecture is intended to demonstrate more than basic prompt-and-response integration.

AI agents can interact with controlled application tools that expose appropriate capabilities from the underlying domain.

Conceptually:

```text
User
   ↓
AI Interface
   ↓
Laravel AI SDK
   ↓
AI Agent
   ↓
Tool Calls
   ↓
Application Services
   ↓
Domain / Database
```

This provides a clear boundary between probabilistic AI reasoning and deterministic application behaviour.

### Supporting Capabilities

The architecture also provides direction for:

- authentication and authorisation;
- role and permission management;
- audit logging;
- queues;
- notifications;
- scheduled jobs;
- reporting and exports;
- automated testing;
- concurrency protection;
- observability.

Exact dependency versions will be documented as implementation progresses.

---

## Engineering Principles

### Business Correctness First

Preventing invalid bookings and maintaining reliable availability is more important than optimising for rapid feature development.

### Single Source of Truth

Availability, bookings and operational state should be derived from authoritative application data rather than independently maintained spreadsheets or schedules.

### Concurrency Safety

Availability checks alone are insufficient.

The booking process must protect against concurrent requests attempting to reserve the same exclusive resource.

### Explicit Domain Rules

Important rules such as parent/child resource conflicts, equipment allocation, provisional reservations, booking approval, payment state and closures should be represented explicitly rather than hidden inside UI code.

### Human Control Where Valuable

Automation should reduce repetitive administration without removing management judgement where exceptions and operational decisions are genuinely required.

### AI-Augmented Engineering

AI is an integrated capability of Facility4Hire.

The application should demonstrate how AI can improve business workflows through:

- agents;
- tool calling;
- structured outputs;
- contextual assistance;
- operational summaries;
- natural-language access to application capabilities.

AI should be connected to meaningful business workflows rather than added solely as a generic chatbot.

### Deterministic Core, Intelligent Interface

AI may help interpret requests, retrieve information, summarise data and assist users.

Authoritative business operations remain controlled by application services.

For example, AI may help a manager find suitable availability, but the application's availability service determines whether the resource is actually available.

This allows Facility4Hire to demonstrate modern AI engineering without sacrificing transactional correctness.

---

## Project Documentation

The repository contains detailed documentation covering the project from discovery through implementation.

| Document | Purpose |
| --- | --- |
| [`docs/discovery.md`](docs/discovery.md) | Business context, historical workflow, stakeholder discovery and identified problems |
| [`docs/requirements.md`](docs/requirements.md) | Functional and non-functional software requirements |
| [`docs/design.md`](docs/design.md) | UX, user journeys and interaction design |
| [`docs/visual-design.md`](docs/visual-design.md) | Visual system, responsive patterns and UI direction |
| [`docs/architecture.md`](docs/architecture.md) | Technical architecture and engineering decisions |
| [`docs/implementation-plan.md`](docs/implementation-plan.md) | Phased implementation roadmap |
| [`AGENTS.md`](AGENTS.md) | Development rules for AI coding agents |

The documentation is maintained alongside the application so implementation decisions remain traceable to business requirements.

---

## Development Approach

The project is being developed incrementally.

The broad implementation sequence is:

```text
Foundation
    ↓
Authentication & Authorisation
    ↓
Venue / Facility Domain
    ↓
Availability & Allocation
    ↓
Booking Workflow
    ↓
Pricing
    ↓
Payments & Invoicing
    ↓
Operations
    ↓
Customer & Management Features
    ↓
Security / Concurrency / Accessibility
    ↓
Testing & Hardening
    ↓
AI Integration & Agent Tooling
    ↓
Demo / Deployment
```

Features are implemented according to documented milestones rather than attempting to generate the entire application in one step.

---

## Project Status

### Completed

- business discovery;
- historical process analysis;
- stakeholder discovery;
- software requirements;
- UX design;
- representative visual design;
- technical architecture;
- implementation planning;
- AI-agent development guidance.

### In Progress

- application foundation and implementation.

### Planned

- complete booking workflow;
- online payments;
- invoicing;
- management administration;
- live operational schedule;
- reporting;
- security and concurrency hardening;
- automated testing;
- Laravel AI SDK integration;
- AI agents and application tools;
- AI-assisted management and operational workflows;
- demonstration environment;
- deployment;
- portfolio case study.

---

## Demonstration Data

The eventual demonstration environment will use fictional data, including fictional:

- centres;
- customers;
- organisations;
- bookings;
- prices;
- addresses;
- equipment quantities.

Historical customer or employee personally identifiable information will not be used as demonstration data.

---

## Project Background

This project is inspired by first-hand experience working within a multi-site community leisure facility operation in Sheffield, UK.

The historical operation managed the commercial hire of school and community facilities outside periods required by the schools.

Rather than recreating the historical system directly, Facility4Hire uses that experience as the basis for investigating how a modern platform could improve the process.

Discovery has included:

- first-hand operational experience;
- retrospective discussion with a former manager;
- analysis of historical booking documentation;
- analysis of historical schedules and operational processes;
- identification of administrative bottlenecks;
- separation of historical behaviour from proposed modern product decisions.

The project is intended to demonstrate the complete process of:

```text
Business Problem
      ↓
Discovery
      ↓
Requirements
      ↓
UX Design
      ↓
Visual Design
      ↓
Architecture
      ↓
Implementation
      ↓
AI Integration
      ↓
Testing
      ↓
Deployment
      ↓
Business Outcome
```

The objective is to demonstrate not only software implementation, but the ability to investigate an operational business problem, design an appropriate technical solution and integrate AI where it creates genuine business value.

---

## Business Outcomes

Facility4Hire is designed around measurable operational improvements rather than feature count.

Potential outcomes include:

- reduced administration time per booking;
- fewer manual availability checks;
- reduced duplicate data entry;
- fewer booking conflicts;
- increased customer self-service;
- increased online payment collection;
- reduced cash handling;
- faster booking confirmation;
- improved facility utilisation;
- improved staff visibility of booking changes;
- improved payment tracking;
- improved management reporting;
- faster access to operational information through AI-assisted querying;
- reduced time spent manually analysing booking and operational data;
- faster management understanding of trends, exceptions and operational issues.

These outcomes will form part of the final project case study.

---

## Portfolio Objectives

Facility4Hire is being built to demonstrate capability across both modern software engineering and applied AI engineering.

The project is intended to demonstrate experience with:

- business and stakeholder discovery;
- requirements engineering;
- UX and product design;
- Laravel application architecture;
- Vue and TypeScript frontend development;
- complex booking and availability domains;
- transactional and concurrency-safe systems;
- Stripe payment integration;
- role-based application design;
- operational dashboards;
- automated testing;
- AI agent development;
- LLM tool calling;
- structured AI outputs;
- AI integration with application services;
- local and hosted AI models;
- AI-assisted software development;
- production-style deployment and observability.

The aim is to demonstrate the ability to build AI-augmented business applications rather than isolated AI prototypes.

---

## Installation

Application installation instructions will be added once the initial Laravel application foundation has been implemented.

---

## Screenshots

Screenshots will be added as the application UI is implemented.

---

## Live Demo

A public demonstration environment is planned for a later implementation phase.

The final demonstration should showcase both the core booking platform and its AI-assisted capabilities.

---

## Case Study

A detailed portfolio case study will be published once the application reaches a suitable demonstration milestone.

It will cover:

- the original business problem;
- discovery;
- stakeholder input;
- process redesign;
- product decisions;
- UX and visual design;
- architecture;
- engineering challenges;
- AI architecture;
- AI agents and tool calling;
- testing;
- security;
- deployment;
- expected business impact;
- lessons learned.

---

## Disclaimer

Facility4Hire is an independent portfolio project.

The demonstration product, branding, centres, customers and operational data are fictional.

The project is inspired by previous professional experience and historical business processes but is not an official product of, endorsed by, or affiliated with any former employer, school, venue operator or other organisation referenced during private project research.