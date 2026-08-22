# Dashboard Analytics, Stat Components, and ApexCharts Integration

## Context
Staff across finance, support, and network administration require an executive and operational command center upon logging into GOBILLING. The previous dashboard consisted of static placeholder wireframes without data aggregation or visual charting. Because Flux UI Free does not include chart or stat components, a modular, aesthetic, and reactive analytics layer is required that harmonizes with Livewire 4, Alpine.js, and dark mode theming.

## Decisions

1. **Charting Engine: ApexCharts + Alpine.js**:
   - Integrated **ApexCharts** for rendering reactive, high-performance, dark-mode aware visualizations.
   - Initialized charts using Alpine.js `Alpine.data()` wrappers with custom theme detection (`document.documentElement.classList.contains('dark')`).
   - Implemented three core charts:
     - **Revenue & Invoicing Area Trend**: 12-month dual-series comparison of collected payments vs total issued invoices.
     - **Package Distribution Donut Chart**: Proportional share of active subscriptions across bandwidth packages.
     - **Operational Ticket Load Column Chart**: Ticket volume segmented by category (`Pemasangan`, `Gangguan`, `Pindah Alamat`, `Pencabutan`).

2. **Custom Reusable `<x-stat-card>` Blade Component**:
   - Created `resources/views/components/stat-card.blade.php` styled with Tailwind CSS tokens.
   - Supports contextual color accents (`emerald`, `indigo`, `amber`, `rose`, `cyan`, `purple`), KPI trend badges (`+14.2% vs periode lalu`), subtext metrics, Lucide/Heroicon containers, and clickable navigation links.

3. **Livewire 4 Dashboard Component (`App\Livewire\Dashboard`)**:
   - Replaced static view routing with a full-fledged Livewire 4 component (`App\Livewire\Dashboard`).
   - Supports reactive time period filtering (`Bulan Ini`, `30 Hari Terakhir`, `Tahun Ini`, `Semua`).
   - Computes aggregated financial metrics (Revenue, Growth %, Unpaid Invoices, Overdue count), subscriber statuses (Active, Isolated, New), infrastructure health (Online MikroTik routers), and critical open tickets.

4. **Recent Operational Activity Feeds**:
   - Displays the 5 latest payments received in real-time.
   - Displays the 5 most urgent open tickets requiring immediate technician or dispatcher action.
