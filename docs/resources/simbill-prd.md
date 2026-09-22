SimBill Rebuild — Product Requirements Document
Sep 21, 2026 · @Ryu Kurnianto Putra
1. Overview
SimBill Rebuild is a self-hosted billing and RADIUS management platform for ISPs, RT/RW Net operators and hotspot operators in Indonesia, built on the same technology stack as the upstream SimBill product.
Background. Upstream SimBill is an ISP billing application on Node.js, Express and MariaDB. It authenticates PPPoE and hotspot users through FreeRADIUS and MikroTik, notifies customers over WhatsApp and Telegram, accepts online payments, and manages ONU devices over TR-069. Everything sits in one Indonesian-language dashboard that also works on phones.
Problem. Small operators often run billing, RADIUS, router provisioning, customer messaging and ONU checks as separate tools and reconcile them by hand. The gaps show up as late invoices, manual suspensions and disputes over who is still online. One system that owns the customer record and writes it to RADIUS and the router removes that reconciliation work.
Approach. This PRD specifies an independent implementation, written from the publicly described features and architecture of the upstream project (SimBill-Project and simbill-dist), not from its source code. It keeps the upstream stack, data-flow rules and role model so operators can carry over their data and habits.
Scope
In scope
Out of scope for the first release
PPPoE and hotspot customers, packages, invoices, payments
Multi-tenant SaaS hosting for several ISPs
FreeRADIUS integration, MikroTik provisioning, live sessions
Native Android apps (the REST API must support them)
Vouchers and a reseller panel with a balance ledger
Full accounting, tax and inventory modules
WhatsApp and Telegram bots, four payment gateways
Zero-touch ONU provisioning beyond signal and status checks
Tickets, ODC/ODP map, TR-069 optical-signal check
Compiled-binary distribution and license activation
Reports, backup and restore, Indonesian and English UI, dark mode
In-app self-update from GitHub Releases (deferred)
2. Goals, non-goals and success metrics
The rebuild succeeds when one operator can run the full customer lifecycle, from signup to invoice to suspension, without touching RADIUS or the router by hand.
Goals
1. Single source of truth: the customer record drives RADIUS credentials, MikroTik profiles and notifications.
2. Automated billing: monthly invoices, reminders and auto-suspend run on a schedule with no operator action.
3. Trustworthy online status: session state comes only from radacct, and stale data is marked, never shown as healthy.
4. Self-service payment: customers pay through Midtrans, Xendit, Duitku or Tripay and the invoice closes itself.
5. Field-ready: technicians and resellers get a phone-friendly view limited to what their role needs.
6. Simple operations: a fresh Ubuntu or Debian VPS reaches a working panel from one install command.
Non-goals
• Replacing FreeRADIUS, MikroTik RouterOS or the ACS with in-house equivalents.
• Supporting routers other than MikroTik in the first release.
• Building a general accounting system; reporting stops at income, periods and net profit.
Success metrics
All targets are proposed and need confirmation against a real operator's size.
Metric
Target
How it is measured
Monthly invoice run, 5,000 customers
Under 10 minutes
Job duration log
Suspend after grace period expires
Within 15 minutes
Scheduler log vs. radacct stop time
Payment webhook to invoice marked paid
Under 30 seconds at p95
Webhook receipt vs. payment timestamp
Dashboard load, 5,000 customers
Under 2 seconds at p95
Browser timing on a 4 GB, 2-core VPS
Fresh install to working login
Under 15 minutes
Timed run on a clean VPS
Duplicate or replayed payments applied twice
Zero
Unique gateway reference constraint plus test
Bulk edits to radacct stop times by the app
Zero
Code review and query audit
3. Users and roles
The system has six roles: four staff roles (superadmin, admin, operator, teknisi), resellers, and customers. The staff role names and the limited technician view come from upstream; the operator/admin split and the customer role are proposed here.
Role
Who they are
Main jobs
Superadmin
ISP owner or IT lead
Manages staff accounts, settings, gateways, backup and restore
Admin
Office or billing manager
Runs customers, invoices, packages, vouchers, resellers and reports
Operator
Front-desk or support staff
Registers customers, records payments, opens tickets
Teknisi
Field technician
Sees assigned tickets, the network map and device status
Reseller
Outlet or sub-agent
Manages own customers and vouchers, tops up balance
Customer
End subscriber
Checks bills, pays online, reports faults via the apps and bots
Permission matrix
"Own" means only records the user created or is assigned. "View" is read-only.
Module
Superadmin
Admin
Operator
Teknisi
Reseller
Customer
Customers
Full
Full
Full
View
Own
Own profile
Packages
Full
Full
View
None
View
None
Invoices and payments
Full
Full
Record payments
None
Own
Own
Vouchers and templates
Full
Full
View
None
Own
None
Reseller accounts and balance
Full
Full
None
None
Own
None
NAS, RADIUS, active sessions
Full
Full
View
View
None
None
Network map (ODC/ODP)
Full
Full
View
Full
None
None
Tickets
Full
Full
Full
Assigned
None
Create
TR-069 devices
Full
Full
View
Full
None
Own signal check
Financial reports
Full
Full
None
None
Own
None
Settings, gateways, backup
Full
None
None
None
None
None
Staff accounts and admin log
Full
View log
None
None
None
None
4. System architecture and tech stack
The system is one Node.js application on a MariaDB database shared with FreeRADIUS, plus add-on services for messaging and TR-069; the stack matches upstream except that the Node.js baseline moves from 20 to 24 LTS.
Upstream pins Node.js 20, which reached end-of-life on 2026-04-30. Node.js 24 is Active LTS until 2028-04-30 and 22 is in maintenance until 2027-04-30, per the Node.js Release Working Group.
Technology stack
Layer
Choice
Notes
Runtime
Node.js 24 LTS (22 LTS minimum)
Upstream installs Node.js 20
Language
JavaScript
TypeScript is an open question (section 13)
Web framework
Express
REST API plus static frontend
Database
MariaDB 10.6 or newer
One database, billing_radius, shared with FreeRADIUS
Authentication
FreeRADIUS with the sql module
UDP 1812 auth, 1813 accounting
Router integration
MikroTik RouterOS API
Ports 8728 and 8729 (TLS)
TR-069
ACS Lite (GoACS)
Own database and user goacs, port 7547
WhatsApp
WAHA (Docker) and WA Mandiri (Baileys)
The legacy whatsapp-web.js QR path is excluded
Telegram
Bot API
Two-way commands
Payments
Midtrans, Xendit, Duitku, Tripay
One driver per gateway
PDF
Headless Chrome
A4 and 58 mm thermal invoices
Process manager
pm2
Web process billing-radius, plus a worker process for scheduled jobs
Scheduler
node-cron in the worker
Keeps jobs from running twice when the web process restarts
Frontend
HTML, CSS, JavaScript SPA, no build step
Native ES modules split across files; upstream ships one admin.html
Host OS
Ubuntu 22.04 or 24.04, Debian 11 or 12
2 CPU cores, 4 GB RAM or more; x86-64-v2 CPU for WAHA
Topology
flowchart LR
  U["Admin, reseller, teknisi<br/>browser"] --> A["SimBill app :3000<br/>Node.js + Express"]
  A --> D[("MariaDB<br/>billing_radius")]
  R["FreeRADIUS<br/>1812/1813"] --> D
  N["MikroTik NAS<br/>PPPoE + Hotspot"] -->|RADIUS| R
  A -->|RouterOS API| N
  C["Customers"] --> N
  A --> W["WhatsApp gateway<br/>WAHA / WA Mandiri"]
  A --> T["Telegram Bot API"]
  P["Payment gateways"] -->|webhook| A
  A --> G["ACS Lite (GoACS)<br/>:7547"]
  O["ONU / CPE"] -->|TR-069| G
The app writes customer credentials to the database, FreeRADIUS reads them to authenticate sessions that MikroTik forwards, and accounting flows back into the same database.
Ports and exposure
Port
Service
Exposure
3000
SimBill panel and API
Behind a TLS reverse proxy
1812, 1813 (UDP)
FreeRADIUS
NAS addresses only
3306
MariaDB
Localhost only
3100
WAHA
Localhost only
3200
WA Mandiri gateway
Localhost only
7547
ACS Lite (TR-069)
Reachable by CPE devices
8728, 8729
MikroTik API
Never public; allow only the app server's address
Core data flows
1. An operator changes a customer, package or invoice in the panel; the app writes credentials to radcheck and radusergroup.
2. A customer connects over PPPoE or hotspot; MikroTik asks FreeRADIUS, which authenticates from billing_radius.
3. Session and usage records land in radacct; the panel reads it for online status and last-seen.
4. A payment gateway calls the webhook; the app marks the invoice paid and lifts any suspension.
5. The scheduler sends reminders and suspensions; messages go out through the WhatsApp gateway.
6. The ACS collects ONU telemetry such as optical power; the app reads it through the ACS API.
Architecture rules
• radacct is the source of truth for online status and is read-only for the app; it never bulk-updates acctstoptime.
• Vouchers and PPPoE customers share the radcheck username namespace, so one uniqueness check guards both.
• The application time zone is Asia/Jakarta; server logs may stay in UTC.
• Secrets live in .env with mode 600; operational settings live in the setting table.
5. Functional requirements: customers and billing
This module owns the customer record, the package catalogue, monthly invoicing, payments and the reminder-then-suspend cycle. Requirements use the form "the system shall"; each has an acceptance criterion a tester can check.
5.1 Customers
ID
Requirement
Acceptance criterion
FR-CUS-01
Create, edit and deactivate PPPoE and hotspot customers with name, phone, address, package, username and password
Saving writes matching rows to radcheck and radusergroup within 5 seconds
FR-CUS-02
Enforce one username namespace across customers and vouchers
A duplicate username is rejected with a clear message, including on import
FR-CUS-03
Store a KTP photo per customer
JPEG or PNG up to 5 MB; viewable only by authenticated staff roles
FR-CUS-04
Record location coordinates and link the customer to an ODC and ODP
The customer appears on the network map under the correct node
FR-CUS-05
Search and filter by name, username, phone, status, package and ODP, with paging
Results return in under 1 second at 5,000 customers
FR-CUS-06
Export customers and import them with a dry-run preview
The preview lists every username collision and row error before anything is written
A customer moves through these states; only the transitions shown are allowed.
stateDiagram-v2
  [*] --> Pending
  Pending --> Active: installed
  Active --> Suspended: overdue past grace
  Suspended --> Active: invoice paid
  Active --> Terminated: contract ended
  Suspended --> Terminated: contract ended
  Terminated --> [*]
5.2 Packages
ID
Requirement
Acceptance criterion
FR-PKG-01
Define packages with name, type (PPPoE, hotspot or both), monthly price and a bandwidth profile
A package can be saved only with a valid rate limit and price
FR-PKG-02
Map each package to a RADIUS group carrying its reply attributes, such as the MikroTik rate limit
Saving a package creates or updates its group rows in the RADIUS tables
FR-PKG-03
Changing a customer's package updates RADIUS and the router profile
The new speed applies at the customer's next session, or immediately after a forced disconnect
FR-PKG-04
Block deletion of a package that still has customers
Deletion returns the count of customers using it
5.3 Invoices
ID
Requirement
Acceptance criterion
FR-INV-01
Generate monthly invoices for all active customers on a configurable day
Re-running the job for the same period creates no duplicates, enforced by a unique index on customer and period
FR-INV-02
Create one-off invoices for items such as installation fees
Appears in the customer's invoice list and totals
FR-INV-03
Track status as unpaid, paid or void, and derive overdue from the due date
Overdue invoices are filterable and counted on the dashboard
FR-INV-04
Print invoices with a QR code on A4 and 58 mm thermal layouts
PDF renders in under 3 seconds; the QR encodes the payment link or invoice number
5.4 Payments
ID
Requirement
Acceptance criterion
FR-PAY-01
Staff can record a manual payment (cash or transfer) with amount, date and note
The invoice is marked paid when payments reach its total; an admin-log entry is written
FR-PAY-02
Print a payment receipt
Thermal receipt shows invoice number, amount, date and cashier
FR-PAY-03
Paying an invoice of a suspended customer restores service automatically
Within 1 minute the customer is active, RADIUS group restored, session re-established, and a confirmation sent
5.5 Reminders and auto-suspend
ID
Requirement
Acceptance criterion
FR-SUS-01
Send a WhatsApp reminder a configurable number of days before the due date (default 3)
One reminder per invoice per offset; failures retry up to 3 times with backoff
FR-SUS-02
Suspend customers whose invoice is unpaid past the due date plus grace days
The customer moves to the isolir group and the live session is disconnected; runs at least hourly
FR-SUS-03
Allow a per-customer "never suspend" flag
Flagged customers are skipped and listed in the job log
FR-SUS-04
Send suspension and reactivation notices from editable templates
Templates support name, invoice number, amount and due-date variables
FR-SUS-05
Scheduled jobs are idempotent and take a lock
Two overlapping runs never process the same customer twice
6. Functional requirements: network integration
This module connects billing decisions to the network: RADIUS records, MikroTik routers, live sessions, the ODC/ODP map and ONU signal checks over TR-069.
6.1 RADIUS
ID
Requirement
Acceptance criterion
FR-RAD-01
Maintain credentials in radcheck, group membership in radusergroup, and group attributes in radgroupcheck and radgroupreply; use radreply for per-user overrides
A test authentication with a customer's credentials returns Access-Accept with the package's rate limit
FR-RAD-02
Manage NAS devices in the nas table with name, address, shared secret and type
The panel flags when FreeRADIUS needs a reload to pick up a new or changed NAS
FR-RAD-03
Show each NAS's health: API reachable and time of last accounting packet
Status refreshes at least every 30 seconds; an unreachable NAS is flagged
FR-RAD-04
Derive online status only from radacct and mark sessions with no recent update as stale
A session with no interim update for twice the interim interval shows "stale", never "online"
FR-RAD-05
Show recent authentication failures per user from radpostauth
Support staff can see the last 20 rejects with reason and time
6.2 MikroTik
ID
Requirement
Acceptance criterion
FR-MTK-01
Sync PPP and hotspot profiles from packages through the RouterOS API
A dry run shows the diff; applying twice changes nothing the second time
FR-MTK-02
Disconnect a live session on demand
The session ends within 5 seconds; failures are logged and retried
FR-MTK-03
Prefer the TLS API port and store router credentials encrypted
Credentials are never returned by any API response
FR-MTK-04
Show real-time interface traffic per NAS on the dashboard
Graph updates at least every 5 seconds while the page is open
6.3 Active sessions and dashboard
ID
Requirement
Acceptance criterion
FR-SES-01
List active PPPoE and hotspot sessions with username, IP, NAS, uptime and up/down bytes, with search and a disconnect action
Loads in under 2 seconds for 2,000 sessions
FR-SES-02
Dashboard summarising revenue, users online, offline and suspended, NAS health and traffic
Figures refresh at least every 10 seconds; each tile shows its data age
6.4 Network map
ID
Requirement
Acceptance criterion
FR-MAP-01
Maintain ODC and ODP records with name, coordinates, parent and port capacity
An ODP cannot be linked to more customers than its ports
FR-MAP-02
Show customers, ODC and ODP on an interactive map with marker clustering
5,000 markers render in under 3 seconds; clicking a marker opens the record
The map uses Leaflet with OpenStreetMap tiles; this choice is proposed and open to change.
6.5 TR-069 and ONU signal
ID
Requirement
Acceptance criterion
FR-ACS-01
Connect to ACS Lite through its HTTP API, with URL and API key kept in settings
A "test connection" button reports success or the failure reason
FR-ACS-02
List devices with serial, model and last inform time, linked to a customer
A device links by PPPoE username or a stored serial mapping
FR-ACS-03
Check an ONU's optical signal (attenuation) on demand from the panel or the Telegram bot
Returns a reading within 30 seconds or a timeout message
FR-ACS-04
Classify readings as good, warning or critical against configurable thresholds
Readings older than the freshness limit show as stale, never as good
FR-ACS-05
Treat the ACS as an external service
The app never reads or writes the ACS database directly
7. Functional requirements: vouchers, messaging, payments and operations
This module covers everything around the core: hotspot vouchers, resellers, WhatsApp and Telegram, online payments, field tickets, reports and system functions.
7.1 Vouchers
ID
Requirement
Acceptance criterion
FR-VCH-01
Generate vouchers in bulk for a hotspot package with prefix, code length and quantity
A batch of 1,000 completes in under 30 seconds; every code is unique in the shared radcheck namespace
FR-VCH-02
Design voucher templates with logo, fields and paper size
The preview matches the printed output on A4 grid and thermal layouts
FR-VCH-03
Enforce validity period or data quota through RADIUS attributes
An expired or exhausted voucher is rejected at authentication
FR-VCH-04
Record each voucher sale with price and seller
Voucher revenue appears in reports, split by admin and reseller sales
7.2 Resellers
ID
Requirement
Acceptance criterion
FR-RSL-01
Provide a separate reseller dashboard limited to the reseller's own customers and vouchers
Requests for another reseller's records return not found; covered by automated tests
FR-RSL-02
Keep reseller balance in an append-only ledger
Balance always equals the sum of ledger rows and never goes negative
FR-RSL-03
Top up balance manually by staff or online through a payment gateway
Every top-up writes one ledger row, even if the gateway retries the callback
FR-RSL-04
Charge the reseller price when generating vouchers or activating customers
Insufficient balance blocks the action with a clear message
FR-RSL-05
Reseller reports for sales, balance history and customers
Filterable by date range and exportable to CSV
7.3 WhatsApp
ID
Requirement
Acceptance criterion
FR-WA-01
Send through a gateway interface with WAHA as the primary driver and WA Mandiri as an alternative
Switching driver is a setting change; a test-message button confirms it works
FR-WA-02
Queue outbound messages with a configurable rate limit and retry
Messages per minute never exceed the limit; failed messages show their error in the log
FR-WA-03
Answer customer commands such as checking unpaid invoices, identified by registered phone number
An unknown number receives a generic reply and no customer data
FR-WA-04
Edit message templates in the panel with variables
A template with an unknown variable is rejected on save
7.4 Telegram
ID
Requirement
Acceptance criterion
FR-TG-01
Provide a staff bot with commands for customer status and ONU signal check
Only Telegram accounts linked to a staff user receive any data
FR-TG-02
Post alerts to a staff group for new tickets, received payments and NAS outages
Each alert type can be switched on or off in settings
7.5 Payment gateways
ID
Requirement
Acceptance criterion
FR-PGW-01
Support Midtrans, Xendit, Duitku and Tripay, each as a driver with create-payment, status check and webhook
Each driver passes a full sandbox payment test
FR-PGW-02
Enable each gateway and store its keys from the panel
Keys are encrypted at rest and masked in the UI
FR-PGW-03
Verify every webhook with the gateway's signature or callback token
A forged callback returns 401 and is logged
FR-PGW-04
Make webhook handling idempotent
A repeated callback returns success and changes nothing; gateway reference is unique in the database
FR-PGW-05
Put a payment link on the invoice, the QR code and the WhatsApp reminder
Paying through the link closes the invoice and triggers FR-PAY-03
FR-PGW-06
Re-check pending payments with the gateway to recover missed webhooks
Payments pending for over 15 minutes are re-checked automatically
7.6 Tickets
ID
Requirement
Acceptance criterion
FR-TKT-01
Create a ticket for a customer with category, description and priority
Priority is one of low, normal, high or urgent
FR-TKT-02
Assign a technician; technicians see only tickets assigned to them
Assignment triggers a notification to the technician
FR-TKT-03
Move tickets through open, assigned, in progress, resolved and closed
Each change is stored with user and time
7.7 Reports, logs, backup and API
ID
Requirement
Acceptance criterion
FR-SYS-01
Financial reports: summary, income by period and net profit, with recorded expenses
Net profit equals income minus expenses for the chosen period
FR-SYS-02
Keep an admin activity log of user, action, record, time and IP
Log entries cannot be edited or deleted from the panel
FR-SYS-03
Back up and restore the database from the panel
Backup runs without locking tables; restore is superadmin-only and takes a snapshot first
FR-SYS-04
Keep operational settings in one place: brand name, gateway tokens, ACS URL and key
Sensitive values are encrypted and masked
FR-API-01
Expose a versioned REST API under /api/v1, documented in OpenAPI, for the customer and admin mobile apps
Every endpoint enforces role scope; token auth with expiry
8. Data model
The database has two kinds of tables: FreeRADIUS tables the app must treat with care, and application tables the app fully owns. Table and column names below are proposed; the table groups follow the upstream architecture.
Core entities
erDiagram
  PACKAGES ||--o{ CUSTOMERS : "subscribed to"
  PACKAGES ||--o{ VOUCHERS : "defines"
  CUSTOMERS ||--o{ INVOICES : "billed"
  INVOICES ||--o{ PAYMENTS : "settled by"
  CUSTOMERS ||--o{ TICKETS : "reports"
  ODC ||--o{ ODP : "feeds"
  ODP ||--o{ CUSTOMERS : "serves"
  RESELLERS ||--o{ CUSTOMERS : "owns"
  RESELLERS ||--o{ VOUCHERS : "sells"
  RESELLERS ||--o{ BALANCE_LEDGER : "has"
Table groups
Group
Tables
Rules
FreeRADIUS
radcheck, radreply, radusergroup, radgroupcheck, radgroupreply, radacct, radpostauth, nas
Created by the FreeRADIUS setup step, never by app migrations. The app writes credentials and groups; it only reads radacct and radpostauth
Access
users, roles, api_tokens, telegram_links
Staff and reseller accounts, role scope, mobile-app tokens
Catalogue and customers
packages, customers, customer_documents, odc, odp
KTP files stored on disk; the table keeps only the path
Billing
invoices, invoice_items, payments, expenses
Money is stored as integer rupiah, never floating point
Vouchers and resellers
voucher_batches, vouchers, voucher_templates, resellers, balance_ledger
Ledger rows are only ever inserted
Messaging
message_templates, message_queue, message_log
Queue rows carry attempt count and last error
Operations
tickets, ticket_events, activity_log, job_runs
job_runs records each scheduled job's start, end and result
Settings
setting
Key and value; sensitive values encrypted
ACS
Separate database goacs
Owned by ACS Lite; the app never connects to it
Required constraints and conventions
Rule
Reason
Unique username across customers and vouchers, checked against radcheck
Both share one RADIUS namespace
Unique (customer_id, period) on monthly invoices
Makes invoice generation safe to re-run
Unique (gateway, gateway_ref) on payments
Makes webhook handling idempotent
No UPDATE or DELETE on balance_ledger from the app
Balance stays auditable
Soft delete: customers become Terminated and invoices become Void
Financial history is never lost
DB server, app and FreeRADIUS all use Asia/Jakarta (+07:00)
radacct times must compare correctly with invoice dates
Indexes on radacct(username, acctstoptime), invoices(status, due_date) and customers(status, package_id)
Needed to meet the section 2 performance targets
Numbered, forward-only migrations run by a migration tool, excluding the RADIUS tables
Repeatable installs and upgrades
9. UI/UX requirements
The panel is a single-page web app in Indonesian and English that works on a phone, shows each role only its own menu, and never hides how old its data is.
Navigation by role
Role
Menu
Superadmin, Admin
Dashboard, Customers, Packages, Invoices, Vouchers, Resellers, Network (NAS, sessions, map), Devices, Tickets, Reports, Messaging, Payments, Settings and backup
Operator
Dashboard, Customers, Invoices, Tickets, Sessions
Teknisi
Dashboard, My tickets, Map, Devices
Reseller
Dashboard, My customers, My vouchers, Top up, Reports
Customers use the mobile apps and the WhatsApp and Telegram bots, not this panel.
Requirements
ID
Requirement
Acceptance criterion
UI-01
Every core screen works on a phone
No horizontal page scroll at 360 px width on the core screens
UI-02
Show only the menus and actions a role may use
The API still enforces permissions; hiding is convenience, not security
UI-03
Indonesian is the default language, English is available, chosen per user
All strings come from locale files; a missing key falls back to Indonesian; money shows as Rp and dates as dd/mm/yyyy
UI-04
Light and dark themes that follow the system setting, with a manual override
Body text meets a 4.5:1 contrast ratio in both themes
UI-05
Large lists use server-side paging, sorting and filtering
A 5,000-row list never loads all rows into the browser
UI-06
Destructive or bulk actions ask for confirmation and show a result summary
Delete, suspend-all and restore each show a confirm dialog naming the count affected
UI-07
Live figures show their age, and old data is labelled stale
Any tile older than its refresh interval turns grey with a "stale" label
UI-08
Print layouts for A4 invoices, 58 mm receipts and voucher sheets
Output matches the on-screen preview
UI-09
Serve the frontend as static ES modules with no build step, with libraries vendored locally
The panel loads on a server with no outbound internet, except map tiles
UI-10
Provide a technician-friendly ticket view with a tap-to-call phone number and a map link
A technician can open a ticket and start navigation in two taps
10. Non-functional requirements
The system must stay safe with customer personal data and payment callbacks, keep customers online when the app is down, and run on a modest 2-core, 4 GB VPS.
Security
ID
Requirement
Acceptance criterion
NFR-SEC-01
No fixed default credentials. The installer generates a random admin password shown once, or forces a change at first login
No build contains a hard-coded admin password. Upstream ships admin / admin123; this is a deliberate change
NFR-SEC-02
Hash passwords with bcrypt or argon2; issue short-lived signed tokens for sessions
Tokens expire; the signing secret is generated at install and preserved across upgrades
NFR-SEC-03
Rate-limit and lock out repeated failed logins
After 5 failures in 15 minutes the account or address is blocked for a cool-down period
NFR-SEC-04
Use parameterised queries only, and never return secrets or password hashes from any endpoint
An automated test scans API responses for hashes, RADIUS secrets and gateway keys
NFR-SEC-05
Encrypt stored secrets (gateway keys, router and ACS credentials) and keep .env at mode 600
Database dump alone does not reveal any gateway key
NFR-SEC-06
Serve the panel over HTTPS behind a reverse proxy with automatic certificates
Plain HTTP redirects to HTTPS; the install guide covers domain and SSL setup
NFR-SEC-07
Bind MariaDB, WAHA and WA Mandiri to localhost, and restrict the MikroTik API to the app server's address
A post-install check lists listening ports and fails on any unexpected public one
NFR-SEC-08
Protect KTP photos and other uploads behind authentication
Direct URL access without a valid session returns 401
NFR-SEC-09
Public webhook endpoints verify signatures, are rate-limited and reveal nothing in responses
Responses contain only a status code and a generic body
Performance and reliability
ID
Requirement
Acceptance criterion
NFR-PERF-01
Meet the section 2 targets on a 2-core, 4 GB VPS at 5,000 customers
Load test at that size passes every target
NFR-PERF-02
List and search API calls stay fast
p95 under 500 ms at 5,000 customers and 2,000 concurrent sessions
NFR-REL-01
Customer authentication does not depend on the app
With the app process stopped, existing customers still authenticate through FreeRADIUS
NFR-REL-02
Billing continues when WhatsApp, a payment gateway, the ACS or a router is down
Failed calls are queued or retried and shown in the UI; no invoice or suspension job aborts
NFR-REL-03
Restart automatically after a crash or reboot
pm2 restarts both processes; scheduled jobs resume without duplicates
NFR-REL-04
Automated nightly database backup with retention of 7 daily and 4 weekly copies
A restore drill from the latest backup succeeds on a clean server
Observability, compatibility and quality
ID
Requirement
Acceptance criterion
NFR-OBS-01
Write structured JSON logs with a request ID, rotated automatically
Logs never contain passwords, tokens or full gateway payloads with secrets
NFR-OBS-02
Provide a health endpoint covering database, RADIUS reachability and last job runs
Returns non-200 when the database is down or a job has missed two runs
NFR-OBS-03
Alert staff on Telegram when a job fails or a NAS goes down
Alert arrives within 5 minutes of the failure
NFR-CMP-01
Support Ubuntu 22.04 and 24.04 and Debian 11 and 12 on x86-64
Installer tested on all four; arm64 is later work
NFR-CMP-02
Work with MikroTik RouterOS 6 and 7
Provisioning and disconnect pass on both versions
NFR-CMP-03
Support current Chrome, Firefox, Safari and Edge, and Android Chrome
Core flows pass on the latest two versions of each
NFR-TST-01
Automated unit and integration tests run in CI against MariaDB and FreeRADIUS containers
An end-to-end test creates a customer and gets Access-Accept from a real RADIUS client
NFR-TST-02
Payment gateway drivers are tested against sandbox environments and recorded fixtures
Every driver has replayable webhook fixtures, including forged and duplicate callbacks
11. Deployment, installation and updates
A fresh Ubuntu or Debian VPS reaches a working panel from one command, and updates keep data safe and roll back on failure. The script set mirrors upstream, with a release tarball replacing the compiled binary and a backup script added.
Install scripts
Every script must be idempotent, non-interactive (no package prompts) and non-fatal for optional add-ons.
Script
What it does
Port
install.sh
Orchestrates: Node.js and pm2, release download, database, app start, then add-ons
None
setup-db.sh
Installs MariaDB, creates database and user, runs migrations, creates the first admin, writes .env
3306
setup-freeradius.sh
Installs FreeRADIUS with the sql module against billing_radius and verifies the config
1812, 1813
setup-waha.sh
Runs WAHA in Docker and stores its token in settings
3100
setup-wa-gateway.sh
Installs the WA Mandiri (Baileys) gateway
3200
setup-acslite.sh
Installs ACS Lite with its own database and a systemd unit
7547
update.sh
Updates the app, optionally the add-ons
None
backup.sh (new)
Nightly database and uploads backup with retention
None
Each component has an opt-out flag, for example SIMBILL_SKIP_RADIUS=1, for servers that already run it.
Configuration
The app reads /opt/simbill/.env at start and refuses to run, with a clear message, if a required value is missing.
Variable
Purpose
PORT
Web port, default 3000
DB_HOST, DB_NAME, DB_USER, DB_PASS
Database connection
JWT_SECRET
Signs session tokens; must be kept across upgrades or every session is invalidated
SECRETS_KEY (new)
Encrypts stored gateway keys and credentials
SIMBILL_HOME
Install directory, default /opt/simbill
TZ
Must be Asia/Jakarta
Updates and rollback
1. update.sh first backs up .env, the database and the uploads folder.
2. It downloads the new release, runs pending migrations and restarts both pm2 processes.
3. It checks the health endpoint; on failure it restores the previous version and database snapshot.
4. Uploads and .env are never overwritten.
In-app self-update from GitHub Releases is deferred past the first release.
Backup and restore
• backup.sh runs nightly with mysqldump --single-transaction for billing_radius, plus the ACS database and the uploads folder.
• Retention is 7 daily and 4 weekly copies; an optional off-server copy is configurable.
• Restore is documented and rehearsed on a clean server as part of release testing (NFR-REL-04).
Rollout rule
Every release is tried on one test server with one test customer before it reaches production, as upstream advises operators to do. Database jobs that touch many rows run in small batches with pauses, to avoid deadlocks with FreeRADIUS.
12. Release plan and milestones
The first usable release is phases 0 to 2, which give billing with automatic suspend on a real router; later phases follow in order of operator value. Calendar dates are set once team size is known (section 13).
Phase
Scope
Requirements
Exit criterion
0. Foundation
Repo and CI, migrations, staff accounts and roles, settings, installer skeleton, FreeRADIUS and database setup, health endpoint
NFR-SEC-01 to 04, FR-SYS-04, NFR-OBS-02, NFR-TST-01
Fresh VPS installs, admin logs in, and CI passes an end-to-end RADIUS Access-Accept test
1. Core billing
Customers, packages, invoices, manual payments, activity log, PDF invoices and receipts
FR-CUS-01 to 06, FR-PKG, FR-INV, FR-PAY-01 and 02, FR-SYS-02
A customer is created, authenticates through RADIUS, is invoiced and paid, and both PDF layouts print
2. Network and automation
RADIUS management, MikroTik sync and disconnect, sessions, dashboard, auto-suspend and restore
FR-RAD, FR-MTK, FR-SES, FR-SUS-02, 03 and 05, FR-PAY-03
A test customer on a real MikroTik is suspended when overdue and restored when paid, unattended
3. Messaging
WhatsApp gateway and bot, Telegram bot, reminders and notices
FR-WA, FR-TG, FR-SUS-01 and 04
Reminder, suspension notice and a customer bot command all work through WAHA
4. Online payments
Four gateway drivers, webhooks, payment links
FR-PGW
Sandbox payments pass on all four gateways, including forged and duplicate callbacks
5. Hotspot and resellers
Vouchers, templates, reseller panel and ledger
FR-VCH, FR-RSL
Reseller top-up, voucher batch, hotspot login and ledger reconcile to the rupiah
6. Field operations
Tickets, ODC/ODP map, TR-069 signal check
FR-TKT, FR-MAP, FR-ACS, FR-CUS-04 map linkage, UI-10
A technician takes a ticket, finds the customer on the map and checks the ONU signal
7. Reporting and hardening
Financial reports, backup and restore, REST API, load and restore tests, i18n and theme polish
FR-SYS-01 and 03, FR-API-01, NFR-PERF, NFR-REL, UI-03 and 04
5,000-customer load test and a restore drill pass, then a pilot with one operator
8. Deferred
In-app self-update, arm64 build, native mobile apps
None yet
Scheduled after the pilot
FR-CUS-04 stores coordinates and the ODP link in phase 1; the map view that uses them arrives in phase 6.
13. Risks, assumptions and open questions
The largest risks are legal (copying upstream) and operational (unofficial WhatsApp gateways and RADIUS data integrity); the largest open decision is who the product is for.
Licensing note
The upstream repository listing shows no license file, its README describes a license-activation system, and its install scripts point at a separate distribution repository. Treat the upstream code, assets and brand as all rights reserved unless the owner states otherwise. Implementers should work from this PRD and public documentation only, must not copy code, images, the mobile apps or the SimBill name, and should choose their own product name if the rebuild will be distributed. This is a project note, not legal advice.
Risks
Risk
Impact
Mitigation
Copying upstream code or branding
Infringement claim, forced takedown
Clean-room approach and own branding (licensing note above)
WhatsApp via unofficial gateways (WAHA, Baileys)
Number blocked, messages stop
Rate limits (FR-WA-02), opt-in messaging, and an official WhatsApp Business API driver behind the same interface later
App corrupts or deadlocks radacct
Wrong online status, billing disputes
Read-only rule for radacct, batch jobs with pauses
Forged or duplicated payment callbacks
Free service or double credit
FR-PGW-03 and 04, sandbox and fixture tests
Customer personal data (KTP photos, phone numbers) leaks
Regulatory and reputational harm
NFR-SEC-05 and 08; review retention and consent against Indonesia's Personal Data Protection Law (UU PDP) before launch
RouterOS 6 and 7 API differences
Provisioning or disconnect fails on some routers
Test both versions (NFR-CMP-02)
Ageing dependencies, as happened with Node.js 20
Unpatched runtime in production
Lockfile, dependency audit in CI, runtime upgrade in the release checklist
Scope is large for a small team
Late or half-finished release
Phases 0 to 2 form a usable first release
Reliance on ACS Lite (a third-party service)
ONU checks unavailable
Wrap it behind one client (FR-ACS-05); the rest of the product works without it
Assumptions
• One ISP per installation (single-tenant), rupiah only, and a monthly billing cycle.
• MikroTik is the only router vendor, and FreeRADIUS uses its standard SQL schema.
• The operator has root access to a VPS with a public address.
• Upstream features are taken from its README files; its source tree was not reviewed, so behaviours not written there are proposals.
Open questions
[ ] Is this rebuild for your own use or to be sold or distributed? This decides branding, licensing and whether multi-tenant hosting comes later.
[ ] Should the schema be drop-in compatible with an existing SimBill database, or start clean with an import script?
[ ] Do the existing upstream Android apps need to keep working, which would force API compatibility, or will new apps be built against /api/v1?
[ ] JavaScript or TypeScript for the codebase?
[ ] May the frontend use a small library such as Alpine.js or Vue (vendored, no build step), or must it be plain JavaScript?
[ ] Should invoices support proration for mid-month starts, partial payments and PPN tax?
[ ] Who bears payment gateway fees: the operator or the customer?
[ ] What are the default grace period and reminder offset for the first operator?
[ ] Team size and target dates, so section 12 can carry a calendar.
Sources
All pages were opened on 2026-09-21.
• danilsyah/SimBill-Project: README feature list, technology table, default install and configuration.
• idpanyoet/simbill-dist: architecture, data flows, install scripts, ports and troubleshooting.
• Node.js Release Working Group: LTS status and end-of-life dates for Node.js 20, 22, 24 and 26.