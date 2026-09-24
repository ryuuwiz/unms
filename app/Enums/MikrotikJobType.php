<?php

namespace App\Enums;

enum MikrotikJobType: string
{
    case ProvisionPppoe = 'provision_pppoe';
    case EnablePppoe = 'enable_pppoe';
    case DisablePppoe = 'disable_pppoe';
    case DeletePppoe = 'delete_pppoe';
    case SyncIpPool = 'sync_ip_pool';
    case DeleteIpPool = 'delete_ip_pool';
    case Ping = 'ping';
    case TestConnection = 'test_connection';
    case ReconcilePppoe = 'reconcile_pppoe';
    case SyncProfilBandwidth = 'sync_profil_bandwidth';
    case ProvisionRouter = 'provision_router';
    case UpdatePppoeProfile = 'update_pppoe_profile';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProvisionPppoe => 'Provisi PPPoE',
            self::EnablePppoe => 'Aktivasi PPPoE',
            self::DisablePppoe => 'Isolir PPPoE',
            self::DeletePppoe => 'Hapus PPPoE',
            self::SyncIpPool => 'Sinkronisasi IP Pool',
            self::DeleteIpPool => 'Hapus IP Pool dari Router',
            self::Ping => 'Ping Router',
            self::TestConnection => 'Uji Koneksi',
            self::ReconcilePppoe => 'Auto-Recover PPPoE',
            self::SyncProfilBandwidth => 'Sinkronisasi Profil Bandwidth',
            self::ProvisionRouter => 'Provisi Penuh Router',
            self::UpdatePppoeProfile => 'Ubah Paket PPPoE',
        };
    }
}
