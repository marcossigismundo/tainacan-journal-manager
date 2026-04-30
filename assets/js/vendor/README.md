# Vendor JavaScript libraries

The plugin loads these vendor scripts locally (no CDN — institutional firewalls
often block them).

## Required for Phase 4 (Indicators)

- `chart.umd.min.js` — Chart.js 4.x UMD build.
  - Source: https://www.chartjs.org/dist/4.4.0/chart.umd.min.js
  - License: MIT
  - Place the file at `assets/js/vendor/chart.umd.min.js`.

If the file is missing, the indicators dashboard still renders the
summary cards but charts show a notice and the page does not crash.

## Optional / future

- `jspdf.umd.min.js` + `jspdf.plugin.autotable.min.js` — for in-browser
  PDF export. Not required: the current implementation uses
  `window.print()` (browser "Save as PDF") which works without JS deps.
- `xlsx.full.min.js` — for native XLSX export. Not required: the
  current implementation exports CSV with a UTF-8 BOM (Excel-friendly).
