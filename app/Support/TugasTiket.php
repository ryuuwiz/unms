<?php

namespace App\Support;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Models\Ticket;
use App\Models\User;

/**
 * Isi panel "Tugas Anda" di detail tiket: aksi divisi yang masih tertunda dan boleh dikerjakan
 * user, diurutkan mengikuti alur Teknisi → NOC → Customer Service → Admin. Aksi yang belum
 * bisa dijalankan tetap muncul dengan alasannya. Otorisasi tetap milik TicketPolicy.
 */
final class TugasTiket
{
    /**
     * @return list<array{divisi: DivisiTicket, aksi: string, judul: string, tersedia: bool, catatan: string|null}>
     */
    public static function untuk(Ticket $ticket, User $user): array
    {
        if ($ticket->status->isTerminal()) {
            return [];
        }

        return match (true) {
            $ticket->jenis === JenisTicket::Pemasangan && $ticket->layanan_pelanggan_id !== null => self::pemasangan($ticket, $user),
            $ticket->jenis === JenisTicket::Pencabutan => self::pencabutan($ticket, $user),
            default => [],
        };
    }

    /**
     * @return list<array{divisi: DivisiTicket, aksi: string, judul: string, tersedia: bool, catatan: string|null}>
     */
    private static function pemasangan(Ticket $ticket, User $user): array
    {
        $tugas = [];

        if (self::divisiTertunda($ticket, $user, DivisiTicket::Teknisi)) {
            $tugas[] = self::tugas(DivisiTicket::Teknisi, 'lapangan', 'Pengerjaan Lapangan');
        }

        if (! $ticket->pemasangan?->sudahDiaktivasi() && $user->can('aktivasiPemasangan', $ticket)) {
            $siap = $ticket->siapDiaktivasi();
            $tugas[] = self::tugas(DivisiTicket::Noc, 'aktivasi', 'Aktivasi Pemasangan', $siap,
                $siap ? null : 'Teknisi harus memilih ODP+Port dan mengunggah minimal 1 foto bukti dulu.');
        } elseif (self::divisiTertunda($ticket, $user, DivisiTicket::Noc)) {
            $tugas[] = self::tugas(DivisiTicket::Noc, 'proses', 'Proses NOC');
        }

        if (self::divisiTertunda($ticket, $user, DivisiTicket::CustomerService)) {
            $tugas[] = self::tugas(DivisiTicket::CustomerService, 'proses', 'Proses Customer Service');
        }

        if (self::divisiTertunda($ticket, $user, DivisiTicket::Admin)) {
            $tugas[] = self::tugas(DivisiTicket::Admin, 'proses', 'Proses Admin', catatan: $ticket->layananPelanggan?->invoicePertamaLunas()
                ? null
                : 'Invoice pertama belum Lunas: Admin baru bisa ditandai selesai setelah pelanggan membayar.');
        }

        return $tugas;
    }

    /**
     * @return list<array{divisi: DivisiTicket, aksi: string, judul: string, tersedia: bool, catatan: string|null}>
     */
    private static function pencabutan(Ticket $ticket, User $user): array
    {
        $tugas = [];

        if ($ticket->layananPelanggan?->odp_port_id && $user->can('lepasPortOdpPencabutan', $ticket)) {
            $tugas[] = self::tugas(DivisiTicket::Teknisi, 'lepas_port', 'Lepas Port ODP');
        }

        if (! $ticket->secretSudahDihapus() && $user->can('hapusSecretPencabutan', $ticket)) {
            $tugas[] = self::tugas(DivisiTicket::Noc, 'hapus_secret', 'Hapus PPP Secret');
        }

        return $tugas;
    }

    private static function divisiTertunda(Ticket $ticket, User $user, DivisiTicket $divisi): bool
    {
        return $ticket->statusDivisi($divisi) !== StatusDivisiTicket::Selesai
            && $user->can('ubahStatusDivisi', [$ticket, $divisi]);
    }

    /**
     * @return array{divisi: DivisiTicket, aksi: string, judul: string, tersedia: bool, catatan: string|null}
     */
    private static function tugas(DivisiTicket $divisi, string $aksi, string $judul, bool $tersedia = true, ?string $catatan = null): array
    {
        return ['divisi' => $divisi, 'aksi' => $aksi, 'judul' => $judul, 'tersedia' => $tersedia, 'catatan' => $catatan];
    }
}
