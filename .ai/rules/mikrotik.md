---
paths:
  - app/Services/Mikrotik/MikrotikService.php
---

# Mikrotik

## getPppStatus() never throws — don't wrap its callers in try/catch
`MikrotikService::getPppStatus()` wraps its entire body in `try { ... } catch (Throwable $e)`, including client/connection setup. It never propagates `MikrotikConnectionException` or anything else — every failure is converted into its normal return array, with `router_online => false` and `error_message` set. Callers (e.g. `Show::loadPppStatuses()`) must not add a `catch (MikrotikConnectionException ...)` around it — that block would be dead code. Consumers should branch on the returned `router_online`/`error_message` keys instead. This does not apply to other `MikrotikService` methods (provisioning/sync methods do throw `MikrotikException`/`MikrotikConnectionException`).
