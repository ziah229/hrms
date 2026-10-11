---
name: Enterprise HCMS Slate & Emerald
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
  on-surface-variant: '#3d4a42'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#6d7a72'
  outline-variant: '#bccac0'
  surface-tint: '#006c4a'
  primary: '#006948'
  on-primary: '#ffffff'
  primary-container: '#00855d'
  on-primary-container: '#f5fff7'
  inverse-primary: '#68dba9'
  secondary: '#565e74'
  on-secondary: '#ffffff'
  secondary-container: '#dae2fd'
  on-secondary-container: '#5c647a'
  tertiary: '#006947'
  on-tertiary: '#ffffff'
  tertiary-container: '#00855b'
  on-tertiary-container: '#f5fff6'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#85f8c4'
  primary-fixed-dim: '#68dba9'
  on-primary-fixed: '#002114'
  on-primary-fixed-variant: '#005137'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-lg:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-lg: 1.5rem
  margin: 1.5rem
  margin-lg: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style
The design system delivers an authoritative, high-density operational platform for headless content management at enterprise scale. Built for technical administrators, content architects, and global operations teams, the visual tone balances quiet precision with decisive action triggers. 

The aesthetic is Modern Corporate with architectural slate structures: crisp cool-gray foundations keep visual fatigue low during prolonged administrative sessions, high-contrast structural containment separates complex data grids, and a controlled emerald/forest accent directs critical user interventions. The interface evokes trust, architectural rigor, and frictionless productivity.

## Colors
The color architecture relies on a specialized division between navigation surfaces, content workbenches, and interactive states:

- **Primary Actions & Validation (`#059669`, `#10b981`, `#047857`):** Emerald anchors primary CTA buttons, active state indicators, pipeline completion bars, and schema status badges. `#047857` provides high-contrast text and hover states, while `#10b981` is reserved for telemetry ticks and success notifications.
- **Structural Nav & Chrome (`#0f172a`, `#1e293b`):** Deep slate-blue provides high grounding for global sidebars, terminal viewports, and primary structural headers, visually isolating global navigation from the functional workspace.
- **Canvas & Surface Architecture (`#f8fafc`, `#f1f5f9`, `#ffffff`):** Work surfaces use `#f8fafc` as the base canvas, `#f1f5f9` for table headers, embedded sub-panels, and secondary toolbars, while pure `#ffffff` is strictly reserved for data cards and modal surfaces.
- **Borders & Dividers (`#e2e8f0`):** Fine architectural line work separates content densities without relying on heavy shadows.

## Typography
Typographic clarity in this enterprise environment favors high scanability and structured information density. `Inter` is deployed across all tiers, utilizing OpenType features (`cv02`, `cv03`, `cv04`, `cv11`) for enhanced character distinction in tabular datasets, code strings, and content identifiers.

Hierarchy relies on crisp weight variance (Medium `500` and SemiBold `600`) rather than drastic font size jumps. Monospace treatments (fallbacks to system monospaces) are reserved for API endpoints, JSON payloads, and localization slugs at `12px` / `16px` line height.

## Layout & Spacing
The layout follows a fluid-responsive enterprise shell anchored by a persistent, collapsible 260px dark slate navigation bar (`#0f172a`) and a dynamic horizontal utility header.

- **Main Workbench Grid:** A fluid 12-column layout with fixed 1rem (`16px`) gutters at desktop widths, scaling up to 1.5rem (`24px`) on ultra-wide viewports (>1440px). 
- **Density Zones:** High-density zones (data tables, schema attribute trees) utilize tight vertical paddings (`space-xs` to `space-sm`), while card containers and page orchestrations utilize standard spacing (`space-md` to `space-lg`).
- **Responsive Adaptations:** Below 1024px, the dark slate sidebar collapses into an off-canvas drawer, multi-column form splits collapse into single-column vertical stacks, and data tables enable horizontal touch scrolling with sticky identity columns.

## Elevation & Depth
Depth is constructed through subtle tonal transitions and thin structural borders rather than heavy atmospheric drop shadows.

- **Base Work Surface:** Flat `#f8fafc` background with no elevation.
- **Card Containers & Modules:** Surface layer in pure `#ffffff`, bordered with a 1px solid `#e2e8f0` outline and an ultra-subtle ambient lift (`0 1px 2px 0 rgba(15, 23, 42, 0.04)`).
- **Floating Overlays & Popovers:** Dropdown menus, schema pickers, and filter cards utilize a crisp 1px `#e2e8f0` stroke supported by `0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.03)`.
- **System Modals & Drawers:** High z-index surfaces layered over an explicit `#0f172a` backdrop at 60% opacity with backdrop blur (4px), elevating focus to critical administrative tasks.

## Shapes
The design system enforces a soft, modern geometry (`roundedness: 1` — 0.25rem / 4px base radius) reflecting programmatic stability and efficient spatial utilization. 

- Form fields, action buttons, table rows, and alert panels adopt the base `0.25rem` radius to preserve sharp spatial alignment within tabular displays.
- Structural cards, modals, and slide-over panels utilize `rounded-lg` (`0.5rem`) for perimeter containment.
- Status badges and metadata tags utilize fully rounded pill styles strictly when representing standalone entities or lifecycle states.

## Components

### Buttons
- **Primary:** Forest emerald background (`#059669`), white text, hover state at `#047857`, active state `#065f46`. Focused with a 2px offset ring in `#10b981`.
- **Secondary:** Slate-tinted neutral surface (`#ffffff`), 1px border `#e2e8f0`, text `#1e293b`. Hover transitions to `#f8fafc` with border `#cbd5e1`.
- **Destructive:** Crisp white surface with `#dc2626` text and border, shifting to solid crimson on confirmation steps.

### Navigation Sidebar
- **Shell:** `#0f172a` background with `#1e293b` internal sub-group borders.
- **Nav Items:** Regular items use `#94a3b8` text and icons.
- **Active State:** Solid `#1e293b` background, high-contrast `#ffffff` text, anchored by a vertical `3px` solid emerald (`#10b981`) left border indicator.

### Input Fields & Controls
- **Form Controls:** 36px standard compact height, `#ffffff` fill, 1px border in `#cbd5e1`, text `#0f172a`, placeholder `#94a3b8`. 
- **Focus State:** 1px border in `#059669` accompanied by a 3px soft outer ring in `rgba(16, 185, 129, 0.15)`.
- **Checkboxes & Radios:** Neutral unchecked state with 1px `#cbd5e1`. Checked state renders solid `#059669` fill with pure white glyphs.

### Chips & Badges
- **Status Indicators (Published/Live):** `#ecfdf5` background, `#047857` text, and a decorative 6px inline `#10b981` dot indicator.
- **Draft/Staged:** `#f1f5f9` background, `#475569` text.
- **Changed/Pending Review:** `#fffbeb` background, `#b45309` text.

### Cards & Tables
- **Data Cards:** Pure `#ffffff` surface, 1px border `#e2e8f0`, interior padding of `1.25rem`.
- **Data Grids:** `#ffffff` canvas with `#f8fafc` header rows; sticky horizontal borders in `#e2e8f0`; row hover states highlight gently with `#f1f5f9`. Row actions remain muted until parent hover.