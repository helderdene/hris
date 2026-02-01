# Typography Configuration

## Google Fonts Import

Add to your HTML `<head>`:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap" rel="stylesheet">
```

Or import in CSS:

```css
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap');
```

## Font Families

### DM Sans (Headings & Body)

A geometric sans-serif designed for clarity and readability. Used for all headings and body text.

**CSS:**
```css
font-family: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
```

**Tailwind:**
```html
<h1 class="font-['DM_Sans']">Heading</h1>
```

Or configure in your CSS:
```css
body {
  font-family: 'DM Sans', sans-serif;
}
```

### JetBrains Mono (Code & Technical)

A monospaced font optimized for code. Used for employee IDs, reference numbers, and technical content.

**CSS:**
```css
font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
```

**Tailwind:**
```html
<code class="font-mono">EMP-2025-0001</code>
```

## Usage Guidelines

### Headings

```html
<!-- Page title -->
<h1 class="text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-slate-100">
  Employee Management
</h1>

<!-- Section heading -->
<h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
  Personal Information
</h2>

<!-- Card heading -->
<h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
  Leave Balances
</h3>

<!-- Subsection -->
<h4 class="text-base font-medium text-slate-800 dark:text-slate-200">
  Government IDs
</h4>
```

### Body Text

```html
<!-- Regular body -->
<p class="text-base text-slate-600 dark:text-slate-400">
  Regular paragraph text for descriptions and content.
</p>

<!-- Small body -->
<p class="text-sm text-slate-600 dark:text-slate-400">
  Smaller text for secondary information.
</p>

<!-- Caption/label -->
<span class="text-xs text-slate-500 dark:text-slate-500">
  Last updated 5 minutes ago
</span>
```

### Technical/Code

```html
<!-- Employee ID -->
<span class="font-mono text-sm text-slate-700 dark:text-slate-300">
  EMP-2025-0001
</span>

<!-- Reference number -->
<span class="font-mono text-xs text-slate-500">
  LV-2025-0042
</span>

<!-- Currency -->
<span class="font-mono tabular-nums">
  ₱25,000.00
</span>
```

## Font Weights

Use these Tailwind font weight classes:

| Weight | Class | Usage |
|--------|-------|-------|
| 400 | `font-normal` | Body text, descriptions |
| 500 | `font-medium` | Labels, nav items, emphasis |
| 600 | `font-semibold` | Headings, buttons |
| 700 | `font-bold` | Strong emphasis, KPI numbers |

## Responsive Typography

Scale text for different screen sizes:

```html
<h1 class="text-xl sm:text-2xl lg:text-3xl font-semibold">
  Responsive Heading
</h1>

<p class="text-sm sm:text-base">
  Responsive body text
</p>
```

## Line Heights

Tailwind provides sensible defaults. Override when needed:

```html
<!-- Tight (headings) -->
<h1 class="leading-tight">Compact Heading</h1>

<!-- Normal (body) -->
<p class="leading-normal">Regular paragraph</p>

<!-- Relaxed (long content) -->
<p class="leading-relaxed">Extended paragraph with more breathing room</p>
```
