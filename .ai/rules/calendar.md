---
paths:
  - 'resources/views/livewire/calendar/**'
---

# Calendar

## Calendar month grid is tablet-first
Month grid on phones is compact: shorter cells and trip dots plus count, inspector stacked below. The full 7-col pills layout is for md and up (tablet). Do not add a JS calendar library.

## Phone calendar toolbar is full-width
On phones the month toolbar stacks and stretches: prev/Today/next is a full-width segmented control with h-11 hits, the experience filter is w-full, and Block Dates is w-full/justify-center. Shrink-wrap those controls from sm, and keep the lg side-by-side toolbar. Do not restore left-aligned shrink-wrapped pills on mobile.

## Phone calendar toolbar stays full width
On phones and stacked widths, the month stepper is a full-width 3-column grid, the experience filter is w-full, and Block Dates is w-full and centered. Shrink those controls only from lg. Do not restore left-aligned shrink-wrapped Today or Block Dates pills on mobile.
