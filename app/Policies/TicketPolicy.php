<?php

namespace App\Policies;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ticket.lihat');
    }

    /**
     * Determine whether the user can view the specific ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->can('ticket.lihat')) {
            return false;
        }

        if ($user->hasRole('teknisi')) {
            return $ticket->pic_id === $user->id;
        }

        if ($user->hasRole('sales')) {
            return $ticket->dibuat_oleh === $user->id
                || $ticket->pelanggan?->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->can('ticket.buat');
    }

    /**
     * Determine whether the user can update ticket basic info.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->can('ticket.ubah')) {
            return false;
        }

        if ($user->hasRole('teknisi')) {
            return $ticket->pic_id === $user->id;
        }

        if ($user->hasRole('sales')) {
            return $ticket->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('ticket.hapus');
    }

    /**
     * Determine whether the user can transition ticket status according to role matrix.
     */
    public function ubahStatus(User $user, Ticket $ticket, StatusTicket $statusBaru): bool
    {
        if (! $user->can('ticket.ubah')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        $statusLama = $ticket->status;

        // NOC matrix
        if ($user->hasRole('noc')) {
            if (in_array($ticket->jenis, [JenisTicket::Gangguan, JenisTicket::Pemasangan, JenisTicket::Pencabutan], true)) {
                return match ([$statusLama, $statusBaru]) {
                    [StatusTicket::Baru, StatusTicket::Diproses],
                    [StatusTicket::Diproses, StatusTicket::MenungguKonfirmasi],
                    [StatusTicket::MenungguKonfirmasi, StatusTicket::Selesai],
                    [StatusTicket::MenungguKonfirmasi, StatusTicket::Diproses],
                    [StatusTicket::Baru, StatusTicket::Batal],
                    [StatusTicket::Diproses, StatusTicket::Batal] => true,
                    default => false,
                };
            }

            return false;
        }

        // Teknisi matrix
        if ($user->hasRole('teknisi')) {
            if ($ticket->pic_id !== $user->id) {
                return false;
            }

            return match ([$statusLama, $statusBaru]) {
                [StatusTicket::Baru, StatusTicket::Diproses],
                [StatusTicket::Diproses, StatusTicket::MenungguKonfirmasi] => true,
                default => false,
            };
        }

        // Sales matrix (only cancel tickets they created)
        if ($user->hasRole('sales')) {
            if ($ticket->dibuat_oleh !== $user->id) {
                return false;
            }

            return match ([$statusLama, $statusBaru]) {
                [StatusTicket::Baru, StatusTicket::Batal],
                [StatusTicket::Diproses, StatusTicket::Batal] => true,
                default => false,
            };
        }

        return false;
    }

    /**
     * Apakah user boleh menandai status sign-off suatu divisi pada Ticket Pemasangan --
     * lihat CONTEXT.md "Status Per-Divisi Tiket". Satu peran hanya boleh menandai divisi
     * miliknya sendiri; Teknisi tambahan harus jadi PIC tiket ini.
     */
    public function ubahStatusDivisi(User $user, Ticket $ticket, DivisiTicket $divisi): bool
    {
        if (! $user->can('ticket.ubah')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return match ($divisi) {
            DivisiTicket::Teknisi => $user->hasRole('teknisi') && $ticket->pic_id === $user->id,
            DivisiTicket::Noc => $user->hasRole('noc'),
            DivisiTicket::CustomerService => $user->hasRole('customer_service'),
            default => false,
        };
    }

    /**
     * Apakah user boleh menjalankan Aktivasi Pemasangan (mengisi router/IP Pool/PPP username
     * pada layanan yang terhubung ke tiket ini) -- lihat CONTEXT.md "Aktivasi Pemasangan".
     */
    public function aktivasiPemasangan(User $user, Ticket $ticket): bool
    {
        return $user->can('layanan_pelanggan.aktivasi');
    }

    /**
     * Determine whether the user can assign or reassign PIC for the ticket.
     */
    public function assignPic(User $user, Ticket $ticket, ?User $pic = null): bool
    {
        if (! $user->can('ticket.assign')) {
            return false;
        }

        if ($pic && ! $pic->isActive()) {
            return false;
        }

        return true;
    }
}
