---
title: "Alpine.js & CSP"
---

## Why this exists

Task Fiend serves a nonce-based Content-Security-Policy with no `'unsafe-eval'` (every `<script>`
tag carries a per-request nonce via the `csp_nonce()` helper). Alpine.js normally evaluates
directive expressions (`x-data`, `@click`, `:class`, and so on) with `new Function(...)`, which
requires `unsafe-eval` — incompatible with that policy. To avoid loosening the CSP, Task Fiend loads
Alpine's [CSP-safe build](https://alpinejs.dev/advanced/csp) instead, which parses directive
expressions with its own restricted, non-`eval` interpreter.

## What that restricts

The CSP build's expression parser only understands a single JS **expression** — member access,
comparisons, ternaries, logical operators, and function calls. It does **not** understand JS
**statements**: no `const`/`let` declarations, no `if`, no semicolon-separated sequences of
statements.

Writing multi-statement logic directly in a directive, like this:

```html
<button @click="const p = new URLSearchParams(window.location.search); if (x) { p.set('a', x); } window.location = '/foo?' + p.toString();">
```

fails silently as far as the page is concerned — nothing happens when you click it — and the actual
error only shows up in the browser console:

```
Uncaught Error: CSP Parser Error: Unexpected token: p
```

There's no error on the page itself, which makes this easy to mistake for a routing or backend bug
instead of a frontend one.

## The fix: put logic in a named method

Anything beyond a trivial expression belongs in an `Alpine.data()` component, registered on
`alpine:init`, with the real logic written as an ordinary method. The directive itself then only
ever needs to contain a bare function call:

```html
<button x-data="dayPdfExport" @click="go()">Export PDF</button>
```

```js
document.addEventListener('alpine:init', () => {
    Alpine.data('dayPdfExport', () => ({
        go() {
            // As much real, unrestricted JS as this needs — building a URL, reading the
            // DOM, whatever. The directive above never sees any of it, just the go() call.
            const p = new URLSearchParams(window.location.search);
            const filterText = Alpine.store('taskCount').filterText;
            if (filterText) { p.set('filter', filterText); }
            window.location.href = '/day/export-pdf?' + p.toString();
        }
    }));
});
```

(Simplified for illustration — see the file below for what `go()` actually does today, which has
grown more logic since this was first written; the CSP-relevant shape hasn't changed.)

The `Alpine.data()` callback and the methods it returns run as normal, unrestricted JavaScript —
the CSP parser only ever sees the bare `go()` call sitting in the `@click` attribute. This is the
same pattern already used throughout the codebase: `sortBy()` / `toggleSortReversed()` and the
`taskFilter` component in `task-list.blade.php`, the `staleBanner` and `dayDateEditor` components in
`dashboard/day.blade.php`, and so on.

## What's safe to write inline

Single expressions are fine directly in a directive — no need to reach for a named method just to
read a value or make one call:

- `:disabled="$store.taskCount.visible === 0"`
- `:class="active ? 'foo' : 'bar'"`
- `@click="toggle()"` — a single call is fine even when the method it calls does something
  complicated internally

The rule of thumb: if what you're writing would need a semicolon to separate two statements, or a
`const`/`if` keyword, it needs to move into a component method instead.

## Reference

See `dayPdfExport` in `resources/views/dashboard/day.blade.php` for a complete worked example, and
`taskFilter` / `sortBy()` in `resources/views/components/task-list.blade.php` for the established
pattern it follows.
