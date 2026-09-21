# ISP billing: automating ticket → activation → billing

Question: how do established ISP billing/OSS systems automate the path from an installation ticket to activation, first invoice, recurring billing, suspension, relocation and termination — to inform automating UNMS (Pemasangan Selesai → layanan + first invoice + PPPoE; Pencabutan Selesai → Berhenti; Pindah Alamat → manual invoice; Pelanggan.status transitions).

Researched 2026-09-21. Every claim below names its source and how it was read. Gaps are listed at the end; nothing is filled in from memory.

## Findings

### 1. The account/service status is the pivot, not the ticket
- Sonar: statuses "control whether or not an account will be billed, whether or not their services are active", and work with Address Lists/RADIUS groups to set service level. Read directly: [Account Statuses](https://docs.sonar.expert/accounts/account-statuses-overview-example-use-cases).
- Sonar's default/custom statuses map closely to UNMS's `Pelanggan.status`: *Lead*, *Pending Install* (inactive, "install scheduled but not yet completed"), *Failed Install*, *Active* ("all accounts with this status will be billed"), *Inactive* (left the network), *Collections*. Same source.
- Splynx: statuses are New, Active, Blocked, Inactive (plus Online filters). Read via search extract of the official wiki (the page body did not render in the fetch tool): [Customer information](https://wiki.splynx.com/customer_management/customer_information).

### 2. A completed install job changes status and opens follow-up tickets, configurably
- Sonar job types carry "Account Status upon Completion" and "Account Status upon Failure", plus "Ticket Action Upon Completion / Failure" (none, create new ticket, or update the linked ticket). Read directly: [Job Types: Best Practices](https://docs.sonar.expert/jobs/job-types-best-practices).
- A job booked from a ticket is auto-assigned to the ticket's assignee. Read directly: [Creating and Booking a Job](https://docs.sonar.expert/jobs/scheduling-how-to-creating-and-booking-a-job).
- The completion example in the same job-types page: account becomes active, a ticket is created for Sales, and the customer is charged the installation service. The page does **not** itself describe provisioning or IP assignment on completion; the claim that Sonar "provisions, assigns an IP and sends an invoice" comes only from a search summary of Sonar marketing, not a doc page.
- Failure is a first-class outcome: a *Failed Install* status and a ticket back to the installs group.

### 3. First invoice fires on the first transition to an active status
- Sonar setting "Generate Invoice On Initial Activation Of Account": "Creates an invoice when an account is moved to an active status for the first time." Optional auto-pay on that invoice. Activation-day billing also requires "Prorate Account Status Change", otherwise the invoice waits for the next billing period. Read directly: [Billing Settings](https://docs.sonar.expert/billing/billing-parameters).
- Splynx prorates automatically for mid-cycle starts, upgrades and downgrades (search summary of official wiki, not fetched: [Change plan](https://wiki.splynx.com/configuring_tariff_plans/tariff_change), [Recurring billing](https://splynx.com/blog/billing/recurring-billing-engine/)).

### 4. Non-payment is two stages: suspend, then deactivate after a grace period
- Splynx: unpaid past due date → **Blocked** (service suspended, still billed). Pays → automatically **Active**. Still unpaid after the "Deactivation period" (days after blocking) → **Inactive**, service stops and billing stops accounting for it. A customer blocked manually by an admin is not auto-unblocked. Source: search extract of the official wiki ([Customer information](https://wiki.splynx.com/customer_management/customer_information), [Automation](https://wiki.splynx.com/configuration/finance/automation)); the page body could not be fetched, so wording is secondhand.
- UISP CRM: a service that is neither Active nor Suspended has its traffic blocked; an "active to" date pauses the service and stops invoices. Source: search extract of [UISP Help Center](https://help.uisp.com/hc/en-us/sections/22589678486167-UISP-CRM); direct fetch returned 403.
- Sonar: cancelling can be prorated with the remainder refunded ("Prorate Account Status Change"). Same billing-settings page as above.

### 5. Enforcement on the router is a per-secret flag
- MikroTik `/ppp secret` has `disabled` ("Whether secret will be used"); a secret overrides its profile, except a single IP always beats a pool; `/ppp active print` lists live sessions. Read directly: [PPP AAA](https://help.mikrotik.com/docs/spaces/ROS/pages/132350049/PPP+AAA).
- UNMS already does this: `HandleLayananStatusChangedListener` disables or enables the secret on Suspend, Aktif and Berhenti. No new router work is needed for the workflow.

### 6. Relocation is treated as a service/customer edit, not an automatic charge
- Splynx has a "Migrate services" tool for moving services between customers, keeping statistics; it does not describe an automatic relocation fee (search summary of the official wiki: [Migrate services](https://wiki.splynx.com/configuration/tools/migrate_services)). This matches your decision that Pindah Alamat is billed manually by an admin.

## What this means for UNMS

1. **Trigger on the status change, not on the ticket close.** Make ticket Selesai a *state change with a configured outcome* (Sonar's "upon completion" rule), and put invoicing on the *first activation* of the layanan so it fires once whichever path activates it.
2. **Model "pending install" explicitly.** UNMS already has `StatusLayanan::Proses` and `Pelanggan.status` steps `ReqPemasangan` and `PemasanganSelesai`. Sonar's pattern defines the service before the install and activates on completion. That differs from your answer Q1=a (install first, admin creates the layanan afterwards), so the two options are in the questions below.
3. **Add a failure outcome.** A failed Pemasangan should produce a visible state and a follow-up ticket, not just Batal.
4. **Two-stage non-payment.** UNMS has Suspend after `tanggal_expired` but no time-based step to Berhenti (Splynx's deactivation period).
5. **Termination and relocation stay explicit.** Pencabutan Selesai → Berhenti (already agreed); Pindah Alamat Selesai → no automatic invoice.

## Not found

- No primary documentation for Indonesian ISP billing systems (RTRWNet/Mikbill/ISPmanager/Mikhmon) was found through search; nothing about them is claimed here. Indonesian practice for isolir (moving a suspended customer to an isolir profile or address-list versus disabling the secret) is therefore unverified.
- Splynx wiki pages and the UISP help article did not render in the fetch tool (JS-rendered / 403). Those claims rest on search extracts of the official pages and should be re-read in a browser before being cited elsewhere.
- Sonar: no doc page found that describes provisioning or IP assignment on install completion.
