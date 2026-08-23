<?php

namespace App\Enums;

enum MikrotikJobType: string
{
    case ProvisionPppoe = 'provision_pppoe';
    case EnablePppoe = 'enable_pppoe';
    case DisablePppoe = 'disable_pppoe';
    case SyncIpPool = 'sync_ip_pool';
    case Ping = 'ping';
    case TestConnection = 'test_connection';
    case ReconcilePppoe = 'reconcile_pppoe';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProvisionPppoe => 'Provisi PPPoE',
            self::EnablePppoe => 'Aktivasi PPPoE',
            self::DisablePppoe => 'Isolir PPPoE',
            self::SyncIpPool => 'Sinkronisasi IP Pool',
            self::Ping => 'Ping Router',
            self::TestConnection => 'Uji Koneksi',
            self::ReconcilePppoe => 'Auto-Recover PPPoE',
        };
    }
}
