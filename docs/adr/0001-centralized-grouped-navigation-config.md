# Centralized Grouped Navigation Configuration

Navigation structure in sidebar and header layouts is centralized in `config/menu.php` as grouped arrays with Spatie permission-based access checks, rather than hardcoded in Blade views. This ensures that new business modules (Customers, Packages, Tickets) can register menu items and access restrictions in a single configuration file without modifying layout templates.
