# Tailwind Color Configuration

## Color Choices

KasamaHR uses three Tailwind color palettes:

- **Primary:** `blue` — Used for buttons, links, active states, primary actions
- **Secondary:** `emerald` — Used for success states, positive badges, confirmations
- **Neutral:** `slate` — Used for backgrounds, text, borders, cards

## Usage Examples

### Primary (Blue)

```html
<!-- Primary button -->
<button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
  Save Changes
</button>

<!-- Primary link -->
<a class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
  View Details
</a>

<!-- Active nav item -->
<div class="bg-blue-600 text-white rounded-lg px-3 py-2">
  Dashboard
</div>

<!-- Primary badge -->
<span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 px-2 py-0.5 rounded">
  Active
</span>
```

### Secondary (Emerald)

```html
<!-- Success badge -->
<span class="bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300 px-2 py-0.5 rounded">
  Approved
</span>

<!-- Success alert -->
<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300 px-4 py-3 rounded-lg">
  Changes saved successfully!
</div>

<!-- Positive stat -->
<span class="text-emerald-600 dark:text-emerald-400">
  +12.5%
</span>
```

### Neutral (Slate)

```html
<!-- Page background -->
<div class="bg-slate-50 dark:bg-slate-900 min-h-screen">

  <!-- Card -->
  <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">

    <!-- Heading -->
    <h2 class="text-slate-900 dark:text-slate-100 text-xl font-semibold">
      Employee List
    </h2>

    <!-- Body text -->
    <p class="text-slate-600 dark:text-slate-400 text-sm">
      Showing 25 of 150 employees
    </p>

    <!-- Muted text -->
    <span class="text-slate-500 dark:text-slate-500 text-xs">
      Last updated 5 mins ago
    </span>

  </div>
</div>

<!-- Border/divider -->
<div class="border-b border-slate-200 dark:border-slate-700"></div>

<!-- Input field -->
<input class="border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2" />
```

## Status Colors

In addition to the main palette, use these semantic colors:

```html
<!-- Error/Danger -->
<span class="text-red-600 dark:text-red-400">Error message</span>
<button class="bg-red-600 hover:bg-red-700 text-white">Delete</button>

<!-- Warning -->
<span class="text-amber-600 dark:text-amber-400">Warning message</span>
<div class="bg-amber-100 text-amber-800">Late</div>

<!-- Info -->
<span class="text-blue-600 dark:text-blue-400">Info message</span>
```

## Dark Mode

Always include dark mode variants using the `dark:` prefix:

```html
<!-- Good: Handles both light and dark -->
<div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
  Content
</div>

<!-- Bad: Only works in light mode -->
<div class="bg-white text-slate-900">
  Content
</div>
```

## Common Patterns

### Card

```html
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm p-6">
  <h3 class="text-slate-900 dark:text-slate-100 font-semibold">Title</h3>
  <p class="text-slate-600 dark:text-slate-400 text-sm mt-1">Description</p>
</div>
```

### Table Row

```html
<tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50">
  <td class="px-4 py-3 text-slate-900 dark:text-slate-100">Value</td>
</tr>
```

### Form Field

```html
<label class="block">
  <span class="text-slate-700 dark:text-slate-300 text-sm font-medium">Label</span>
  <input class="mt-1 w-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
</label>
```
