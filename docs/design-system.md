# Facility4Hire — Design System Contract

M25A.1 establishes this contract and the limited Vue foundations described below.
Screen composition, navigation and status adoption remain later M25 work. This
contract implements the visual direction in [visual-design.md](visual-design.md);
[requirements.md](requirements.md) and [design.md](design.md) govern behaviour.

## 1. Brand identity

- **Facility4Hire:** software identity and management/operations workspace brand.
- **Sheffield Community Venues:** fictional demonstration operator, used for
  public/customer booking. An optional “Powered by Facility4Hire” is secondary.
- This is a static presentation distinction. A customer's booking Organisation
  is not the venue operator or a theme selector. No tenancy, branding database,
  administration or tenant theme switching is introduced.
- M25A.2 adopts the names in appropriate shells. Retain accessible full text as
  the fallback; an existing Lucide venue/building icon can accompany it, marked
  decorative when adjacent text supplies the name. A collapsed brand link needs
  the full accessible name. Do not crop generated logos from mockups.
- No supplied production logo or venue photography is assumed. Use text/icons
  until approved assets exist; do not fabricate photography or add image hosts.

## 2. Visual principles

All 18 target PNG mockups and 23 current PNG screenshots were visually inspected.
The targets establish navy structure, teal actions, light canvases, restrained
cards, clear status labels and mobile stacking. Current Vue screens use mostly
neutral starter styling; Filament uses amber and native panel structures.
The references also contain inconsistent logos, generated text and illustrative
features: they inform composition, not new business scope.

Make context, state and the next action easy to scan. Use one primary action per
task section; keep destructive actions distinct. Customer pages are spacious;
management tables retain useful density; operations prioritises Now and Next.
Use real authorised application data and preserve M01–M24 rules.

## 3. Semantic colours and verified combinations

The existing `resources/css/app.css` variables and Tailwind `@theme inline`
mappings are the Vue source of truth. Extend them rather than adding a second
palette. `primary` is teal; `sidebar-primary` supplies structural navy in light
mode. Existing `destructive` means danger and `ring` means focus: do not add
competing `danger`, `brand`, `navigation` or `focus-ring` aliases.

| Role / tokens | Light background / foreground | Ratio | Dark background / foreground | Ratio |
| --- | --- | --- | --- | --- |
| Canvas: background / foreground | #F4F6F8 / #15253B | 14.26:1 | #0B1423 / #F1F5F9 | 16.84:1 |
| Card, popover / foreground | #FFFFFF / #15253B | 15.44:1 | #142238 / #F1F5F9 | 14.57:1 |
| Primary / primary-foreground | #0F766E / #FFFFFF | 5.47:1 | #5EEAD4 / #0B1423 | 12.47:1 |
| Success / success-foreground | #166534 / #FFFFFF | 7.13:1 | #86EFAC / #0B1423 | 13.14:1 |
| Warning / warning-foreground | #92400E / #FFFFFF | 7.09:1 | #FCD34D / #0B1423 | 12.79:1 |
| Destructive / destructive-foreground | #B91C1C / #FFFFFF | 6.47:1 | #FCA5A5 / #0B1423 | 9.72:1 |
| Info / info-foreground | #1D4ED8 / #FFFFFF | 6.70:1 | #93C5FD / #0B1423 | 10.23:1 |
| Muted / muted-foreground | #EDF1F5 / #526176 | 5.56:1 | #203047 / #B7C4D4 | 7.53:1 |
| Secondary / secondary-foreground | #E8EDF2 / #15253B | 13.11:1 | #203047 / #F1F5F9 | 12.17:1 |
| Accent / accent-foreground | #E3F2F0 / #115E59 | 6.58:1 | #203D42 / #99F6E4 | 9.22:1 |
| Sidebar / sidebar-foreground | #FFFFFF / #15253B | 15.44:1 | #0F1D32 / #F1F5F9 | 15.43:1 |
| Sidebar primary / foreground | #0F2D5B / #FFFFFF | 13.58:1 | #5EEAD4 / #0B1423 | 12.47:1 |

These ratios were calculated from sRGB relative luminance using the
[WCAG contrast formula](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
Normal text requires 4.5:1; large text requires 3:1. Treat all listed text pairs
as normal text. Supporting text on cards is 6.31:1 light and 9.01:1 dark.

`border` is decorative separation (#CBD5E1 light / #33455D dark), not an adequate
control boundary by itself. Use `input` (#64748B / #71839A) when the outline is
needed to identify a control: canvas/card ratios are at least 4.39:1 light and
4.12:1 dark. Opaque `ring` (#0F766E / #5EEAD4) against canvas, card and muted
surfaces has a minimum 4.82:1 light / 9.01:1 dark, exceeding the applicable
[3:1 non-text target](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).
Check focus against the actual adjacent surface, especially filled controls.

Use `bg-success text-success-foreground` and equivalent warning, destructive
and info pairs for solid status badges. Always include a label; decorative
icons supplement it. Success covers confirmed/paid/completed; warning covers
pending approval/payment or overdue; danger covers failure/conflict or a
consequential action; info covers neutral progress/help. Cancellation is neutral
unless attention is required. Preserve existing domain terminology and keep
booking, payment and invoice states separate.

Ratios cover the exact opaque pairs, not every existing component. Hover blends,
translucent alerts/rings, charts, disabled states, hardcoded colours and images
require rendered review. Existing chart tokens are retained pending M25C.
Dark mode uses the existing `.dark` class and appearance preference; do not
introduce another switch. Bright dark-mode status fills use dark foregrounds.

## 4. Typography

Reuse locally bundled **Instrument Sans** and the existing system fallback.
The Vite font configuration already supplies 400, 500 and 600; no new font or
runtime font host is added. Prefer these actual weights.

| Purpose | Tailwind convention |
| --- | --- |
| Page title, one h1 | text-2xl font-semibold tracking-tight; md:text-3xl where useful |
| Section title, h2 | text-lg font-semibold |
| Subsection, h3 | text-base font-semibold |
| Customer body | text-base leading-6 |
| Tables and dense supporting content | text-sm leading-5 |
| Labels | text-sm font-medium |
| Captions / metadata | text-xs leading-4; never sole critical instruction |
| Summary value | text-2xl font-semibold tabular-nums |
| Money, dates and aligned table values | tabular-nums; right-align numeric columns |

Use meaningful heading elements independently of visual size. Existing Heading
components and pages are adapted in later stages, not mass resized in M25A.1.

## 5. Spacing, radius and elevation

Use Tailwind's existing 4px rhythm: gap-2 (8px), gap-3 (12px), gap-4 (16px),
gap-6 (24px) and gap-8 (32px). Page padding starts at p-4, expands to md:p-6;
card interiors use p-4 or p-6 and section groups gap-6. Avoid arbitrary spacing.

Retain `--radius: 0.5rem`: rounded-lg 8px, rounded-md 6px, rounded-sm 4px.
Existing Card rounded-xl (12px) remains appropriate; badges may be rounded-full.
Use border for grouping, shadow-xs/sm sparingly for cards and shadow-lg for
overlays. Avoid gradients, large decorative shadows and excessive nested cards.

## 6. Navigation conventions

Customer/public shells identify Sheffield Community Venues, offer clear booking
and account destinations and retain Facility4Hire as secondary attribution.
Staff shells identify Facility4Hire and their workspace with restrained navy
structure. Active destinations use visible text and an active indication plus
`aria-current="page"`. Preserve authorised destinations and route helpers.

M25A.2 adapts existing Vue layouts, AppLogo, navigation and Sheet primitives;
no new route or role capability is implied. Mobile uses a labelled menu toggle
and accessible drawer with focus return. Avoid duplicate bottom navigation
until the destination model warrants it. Management and operations retain
native Filament navigation, with role-specific grouping in M25C/M25D.

## 7. Cards, tables, forms and shared patterns

Compose existing Card, Badge, Button, Alert, Dialog, Sheet and Skeleton. Preserve
their behaviour and accessibility. Use labelled form controls, nearby help and
associated error text. Show errors inline and, for longer forms, in a linked
summary. Tables use proper headers, meaningful actions and aligned monetary
values. Never hide essential status/actions only to fit a viewport.

The following are contracts, not new components implemented in M25A.1:

| Pattern | Responsibility and existing building blocks | First adoption |
| --- | --- | --- |
| PageHeader | h1, context, optional description/actions; adapt Heading | M25A.2 |
| StatusBadge | Explicit label + semantic tone using Badge; no business inference | M25A.2 |
| EmptyState | Explain empty state and useful authorised next step; Card/Button | M25A.2 |
| DashboardSummaryCard | Label, value, optional supporting link; Card | M25A.2, used M25B/C |
| SectionCard | Labelled section and optional actions; Card composition | M25A.2 |
| FilterBar | Labelled inputs, apply/reset and results context; wrap on mobile | M25A.2, used M25B/C |
| FormFeedback | Shared errors/success via Alert and existing InputError | M25A.2 |
| LoadingState | Skeleton matching content; concise accessible loading text | M25A.2 |
| ErrorState | Explain failure, preserve input and offer safe retry; Alert/Button | M25A.2 |
| BookingCard | Existing booking data, separate lifecycle/payment labels and actions | M25B |
| BookingStepIndicator | Ordered steps, current step text/aria-current="step" | M25B |

Extract only when adoption justifies reuse. Keep workflow decisions and status
mapping in authorised presenters/pages, not inside generic primitives.

## 8. Responsive conventions

Use existing mobile-first Tailwind breakpoints: sm 640px, md 768px, lg 1024px,
xl 1280px. Customer cards/forms start as one column and add columns when useful;
booking summaries stack below the form on phones. Desktop customer content
usually fits max-w-7xl, task forms max-w-3xl. Keep long names and amounts readable.

Management retains productive desktop tables with deliberate overflow or detail
views for secondary data. Operations Now/Next stacks on phones and forms two
columns where readable on tablets. Its core schedule must not require sideways
scrolling. Verify at 320/375, 768 and 1280px, 200% zoom and 400% reflow. Aim for
44px primary touch targets; meet WCAG's 24px minimum or permitted spacing rules.

## 9. Accessibility requirements

Target WCAG 2.2 AA throughout M25; do not claim compliance from colour arithmetic.
Require landmarks, logical headings, visible labels, meaningful link names,
keyboard operation, visible unclipped focus, text status, error association and
useful live announcements for asynchronous outcomes. Dialog/Sheet preserve
focus management and keyboard dismissal where safe; confirm consequential actions.

M25A.1 supplies an opaque 2px focus-visible outline with 2px offset as a base
fallback, plus opaque Button/Badge focus rings. Utility overrides in other
primitives still require review in M25A.2; do not remove outlines without an
equivalent indicator. InputError hardcoded reds and alert opacity are deferred
to the shared feedback work. Respect reduced motion using motion-reduce variants
when adapting transitions, drawers and Skeleton; no new motion is introduced now.

Browser, keyboard, screen-reader, forced-colours and light/dark state review
remain required in the relevant stage and M25E. Screenshots alone cannot prove
interaction or accessibility.

## 10. Vue implementation

Keep Inertia/Vue pages and layouts under resources/js, use existing typed route
helpers, and preserve server props and action flows. CSS-first Tailwind
[theme mappings](https://tailwindcss.com/docs/theme) expose semantic utilities;
use them instead of raw palette classes in new shared patterns.

Implemented now: palette/foreground pairs, success/warning/info mappings,
consolidated font default, focus fallback, and Button/Badge destructive foreground
and focus corrections. No new component framework, page redesign or layout is
implemented. Default and destructive hover opacity still needs contextual review.

## 11. Filament implementation

Keep Filament/Livewire native components, panel configuration and theme APIs.
Share this written brand, colour-role, type and status contract across frameworks;
Vue components are not imported into Blade. The current management theme is
resources/css/filament/management/theme.css and is already a Vite input.
Operations currently uses native panel styling without its own theme input.
Both providers currently use Amber; their theme changes belong to M25C/M25D.

Later map teal to native primary configuration and native success/warning/danger/
info scales; review actual generated shades and dark states. Use supported theme
hooks for navy structure and Instrument Sans loading after checking native font
configuration. Do not import app.css wholesale into Filament: it owns different
Tailwind sources and primitive tokens. Add an operations theme only if an actual
styling requirement cannot be met by panel configuration. No provider, theme
loading or Vite entry-point change is made in M25A.1.

## 12. Subsequent M25 stages

- **M25A.2:** Vue shells and branding, navigation, shared patterns from section 7,
  semantic tone/focus/feedback adoption and responsive shared structure. This is
  the first substantial visible redesign; preserve current routes and data.
- **M25B:** Customer discovery/availability, booking review, dashboard and existing
  account workflows; BookingCard and step indicator. New public finder routes or
  missing mockup capabilities require their own approved scope.
- **M25C:** Native management theming, dashboard, actionable tables/forms and
  status consistency using existing data; no financial/domain changes.
- **M25D:** Operations shell and Now/Next/full schedule for mobile/tablet using
  the existing schedule and permissions; no new attendance workflow.
- **M25E:** Final cross-device, keyboard, screen-reader, contrast and state review,
  targeted fixes and recorded acceptance evidence.

Accessibility is part of every stage. M25 remains incomplete until its defined
acceptance is verified; AI remains M28–M30.

## 13. Anti-patterns and exclusions

No pixel copying, guessed mockup data, invented maps/galleries/amenity taxonomies,
custom payment/card-entry flows, new AI, tenancy or domain behaviour. No dependency
changes, vendor edits, broad Filament selectors, duplicate token layers, arbitrary
colours, unlabelled status dots or unnecessary Vite inputs. Preserve availability,
ownership, pricing snapshots, settlement, authorisation and concurrency safeguards.
Screen-specific hardcoded colours and motion are migrated in their scheduled
stages, not through a blanket restyle in this foundations task.
