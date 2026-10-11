---
name: Enterprise HCMS Administration
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#444651'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#757682'
  outline-variant: '#c5c5d3'
  surface-tint: '#4059aa'
  primary: '#00236f'
  on-primary: '#ffffff'
  primary-container: '#1e3a8a'
  on-primary-container: '#90a8ff'
  inverse-primary: '#b6c4ff'
  secondary: '#565e74'
  on-secondary: '#ffffff'
  secondary-container: '#dae2fd'
  on-secondary-container: '#5c647a'
  tertiary: '#002d48'
  on-tertiary: '#ffffff'
  tertiary-container: '#004469'
  on-tertiary-container: '#56b3f9'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b6c4ff'
  on-primary-fixed: '#00164e'
  on-primary-fixed-variant: '#264191'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#cce5ff'
  tertiary-fixed-dim: '#93ccff'
  on-tertiary-fixed: '#001d31'
  on-tertiary-fixed-variant: '#004b73'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 22px
  body-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '600'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  label-sm:
    fontFamily: Inter
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 12px
    letterSpacing: 0.06em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-dense: 0.5rem
  margin: 1.5rem
  margin-mobile: 0.75rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system targets enterprise Human Capital Management Systems (HCMS), personnel operations, and payroll administrative dashboards. It bridges the structured utility of classic enterprise back-offices (AdminLTE, NiceAdmin) with the clarity, precision, and spatial elegance of modern enterprise web applications (Bootstrap 5 and Material Design).

The aesthetic is **Corporate Modern / Dense Utility**:
- **Tone:** Methodical, authoritative, trustworthy, and audit-ready.
- **Focus:** High information density without cognitive clutter. Complex workforce schedules, attendance registers, approval matrixes, and payroll ledgers must remain instantly legible.
- **Visual Structure:** A deep slate navigation spine paired with bright, crisp operational workspaces. Visual weight is assigned systematically: actions are distinct, statuses are unambiguously color-coded, and secondary metadata never competes with operational records.

## Colors

The palette is engineered for administrative workflows where status signaling and visual hierarchy directly affect operational accuracy.

### Functional Roles
- **Primary (`#1e3a8a` / `#2563eb`):** Corporate Navy. Anchors primary calls-to-action, navigation state highlights, and structural accents. Use `#1d4ed8` strictly for hover and active button states.
- **Secondary / Slate Navy (`#0f172a` canvas, `#1e293b` surface):** Assigned to the master administrative sidebar, utility menus, and inverted control headers. Provides solid framing around work surfaces.
- **Neutral Canvas (`#f8fafc` background, `#f1f5f9` surface-subtle):** Cool slate background reduces eye strain over long operational shifts compared to high-glare raw white.
- **Card Surfaces (`#ffffff`):** Pure white container surfaces bounded by fine structural borders (`#e2e8f0`).

### Semantic Status Signaling
Color mapping must follow administrative conventions:
- **Success (`#059669` / bg `#ecfdf5`):** Positive confirmations, attendance fulfilled ('Hadir'), finalized cycles ('Final'), and approved workflows ('Disetujui').
- **Warning (`#d97706` / bg `#fffbeb`):** Pending approvals ('Review', 'Pending'), irregularities, and grace-period alerts ('Terlambat').
- **Danger (`#dc2626` / bg `#fef2f2`):** Unexcused absences ('Alpha'), rejections ('Ditolak'), infractions, and payroll deductions ('Potongan').
- **Info (`#0284c7` / bg `#f0f9ff`):** Planned leave allocations ('Izin/Cuti'), standard allowances, and organizational alerts.

## Typography

The typography scale relies on **Inter**, configured for structural density, tabular data consistency, and effortless scanning across large datasets.

### Application Rules
- **Tabular Figures:** All metric widgets, ledger tables, currency figures, and time records must utilize `font-feature-settings: "tnum" 1` to guarantee vertical number alignment.
- **Section & Page Titles:** Use `headline-lg` for top-level operational breadcrumbs and page headings; pair with subtle breadcrumb ancestors in `body-sm`.
- **Labels & Status Pills:** `label-md` and `label-sm` are strictly uppercase with slight letter spacing (`0.04em`–`0.06em`) for status indicators, table header labels, and field categorizations.
- **Table Cells:** Default cell data maps directly to `body-md` (13px/18px) to maximize row density without compromising touch or cursor hit targets.

## Layout & Spacing

The structural layout utilizes a persistent **Dual-Axis Enterprise Shell**:
- **Sidebar Shell:** A fixed 260px left sidebar (collapsible to a 64px compact icon rail) rendered in `secondary_color_hex` (`#0f172a`).
- **Topbar Anchor:** A 60px sticky top bar aligned to the content area containing global search, branch/tenant selection, notification bells, and profile dropdowns.
- **Main Viewport Canvas:** A 12-column responsive fluid grid nestled within `margin` (24px) padding, collapsing to 12px margins on screens under 768px.

### Breakpoints & Adaptations
- **Desktop (≥1200px):** Full-width 260px sidebar visible; metric cards arranged in 4 columns (`col-3`); data table actions rendered inline.
- **Tablet / Laptop (992px - 1199px):** Sidebar compacts to mini-rail or collapsible drawer; metric cards wrap to 2 columns (`col-6`).
- **Mobile (<768px):** Off-canvas drawer navigation triggered via hamburger menu; table containers enable horizontal swipe with pinned primary columns; action bars collapse to a primary floating or sticky bottom group.

## Elevation & Depth

Visual hierarchy uses a refined hybrid approach: **low-contrast borders for planar division combined with soft ambient drop-shadows** to lift active surfaces.

### Depth Scale
- **Level 0 (App Canvas):** `#f8fafc` flat background. No shadow.
- **Level 1 (Cards, Metric Widgets, Data Tables):** Pure white `#ffffff` surface, bounded by a 1px solid `#e2e8f0` stroke, with subtle dual-layer shadow:
  `box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06), 0 1px 2px 0 rgba(0, 0, 0, 0.04)`
- **Level 2 (Dropdowns, Flyouts, Popovers):** White surface with 1px `#e2e8f0` border, lifted by:
  `box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04)`
- **Level 3 (Modal Dialogs, Quick Input Panels):** Centered overlay raised above a 50% opacity backdrop (`rgba(15, 23, 42, 0.55)`):
  `box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 10px 10px -5px rgba(0, 0, 0, 0.04)`

## Shapes

This design system uses **Soft (Level 1)** geometry to uphold a professional, crisp, and space-efficient dashboard aesthetic.

### Radius Assignments
- **Core Elements (`0.25rem` / 4px):** Form inputs, standard buttons, data table action buttons, and small dropdown triggers.
- **Containers (`0.5rem` / 8px):** Cards, metric tiles, modal containers, and alert banners.
- **Pills (`9999px`):** Status indicator badges (`label-sm`), notification counter dots, and active sidebar item highlighting pills.

## Components

### Buttons
- **Primary:** Background `#1e3a8a` with white text. Hover `#1d4ed8`. Padding `0.5rem 1rem`, radius `0.25rem`. Focus ring: 2px offset with `#2563eb`.
- **Secondary / Neutral:** Crisp white background with `#e2e8f0` border and `#1e293b` text. Hover brings `#f8fafc` background.
- **Icon Actions:** 32x32px compact neutral buttons within table rows, providing rapid access to view, edit, and print actions.

### Status Badges & Pills
- Rendered in uppercase `label-sm` with full pill radius (`9999px`) and padding `0.2rem 0.6rem`.
- Always pair soft 10% tinted background fills with deep matching 800-series text:
  - *Hadir / Final / Disetujui:* `#ecfdf5` background, `#065f46` text.
  - *Terlambat / Pending:* `#fffbeb` background, `#92400e` text.
  - *Alpha / Ditolak:* `#fef2f2` background, `#991b1b` text.
  - *Izin / Cuti:* `#f0f9ff` background, `#075985` text.

### Form Inputs & Selects
- 38px standard height, `#ffffff` surface, 1px `#cbd5e1` border, 4px corner radius.
- Padding `0.375rem 0.75rem`, font size `13px`.
- Active focus state: border shifts to `#2563eb` with a 3px soft outer ring (`rgba(37, 99, 235, 0.15)`).

### Tables & Data Grid
- Header: `#f8fafc` background with 1px border-bottom `#cbd5e1`. Text formatted using `label-md` in `#475569`.
- Cell padding: Compact `0.65rem 0.85rem` vertical-horizontal spacing. Hover row highlight: `#f1f5f9`.
- Footer: Embedded pagination with items-per-page selector, record range summary, and segmented page buttons.

### Navigation Sidebar
- Background `#0f172a` with brand crest at top.
- Nav items use `#94a3b8` text with subtle icons.
- Active nav item receives a `#1e3a8a` or `#2563eb` solid pill background with `#ffffff` bold text.
- Collapsible group arrows rotate with transition on expansion.

### Modals & Dialogs
- Max-width containers (`500px` for standard single-column forms, `768px` for dual-column HR employee records).
- Header divided by fine border `#e2e8f0` with modal title in `headline-sm` and a quick-close icon.
- Sticky action footer with secondary dismiss and primary commit actions right-aligned.