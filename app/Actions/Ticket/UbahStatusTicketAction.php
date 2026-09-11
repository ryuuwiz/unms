<?php

namespace App\Actions\Ticket;

use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use App\Exceptions\TransisiStatusTidakValidException;
use App\Models\AkunPelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketStatusBerubahNotification;
use App\Notifications\TicketStatusBerubahPelangganNotification;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UbahStatusTicketAction
{
    /**
     * Eksekusi perubahan status tiket secara aman dan atomic.
     *
     * @throws TransisiStatusTidakValidException
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function execute(Ticket $ticket, StatusTicket $statusBaru, User $actor, ?string $catatan = null): Ticket
    {
        // 1. Validasi matriks transisi state machine
        if (! in_array($statusBaru, $ticket->status->transisiValid(), true)) {
            throw new TransisiStatusTidakValidException($ticket->status, $statusBaru);
        }

        // 2. Validasi otorisasi peran via Policy
        if (! Gate::forUser($actor)->allows('ubahStatus', [$ticket, $statusBaru])) {
            throw new AuthorizationException(
                "Anda tidak memiliki hak akses untuk mengubah status tiket {$ticket->nomor_ticket} menjadi '{$statusBaru->label()}'."
            );
        }

        // 3. Validasi khusus pembatalan tiket (Wajib catatan min 5 karakter)
        if ($statusBaru === StatusTicket::Batal) {
            $catatanTrimmed = trim((string) $catatan);
            if (mb_strlen($catatanTrimmed) < 5) {
                throw new InvalidArgumentException('Catatan alasan pembatalan wajib diisi minimal 5 karakter.');
            }
        }

        $statusLama = $ticket->status;

        // 4. Eksekusi mutasi dalam database transaction
        DB::transaction(function () use ($ticket, $statusLama, $statusBaru, $actor, $catatan) {
            $updateData = [
                'status' => $statusBaru,
            ];

            // Flag aktivasi manual MikroTik untuk tiket pemasangan yang selesai (Fase 4 -> Fase 6)
            if ($statusBaru === StatusTicket::Selesai && $ticket->jenis === JenisTicket::Pemasangan) {
                $updateData['perlu_aktivasi_manual'] = true;
            }

            $ticket->update($updateData);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => $statusLama,
                'status_baru' => $statusBaru,
                'catatan' => $catatan ? trim($catatan) : null,
                'oleh_pengguna_id' => $actor->id,
            ]);
        });

        // 5. Kirim notifikasi internal database ke pihak terkait setelah transaksi sukses
        $this->dispatchNotifications($ticket, $statusLama, $statusBaru, $actor, $catatan);

        return $ticket->refresh()->load(['histori.olehPengguna', 'pic', 'dibuatOleh']);
    }

    /**
     * Kirim notifikasi internal ke pembuat tiket dan PIC jika bukan aktor pengubah.
     * Jika tiket berasal dari portal, kirim juga notifikasi ke AkunPelanggan pemilik tiket.
     */
    protected function dispatchNotifications(
        Ticket $ticket,
        StatusTicket $statusLama,
        StatusTicket $statusBaru,
        User $actor,
        ?string $catatan
    ): void {
        $recipients = collect();

        if ($ticket->dibuat_oleh && $ticket->dibuat_oleh !== $actor->id) {
            if ($ticket->dibuatOleh) {
                $recipients->push($ticket->dibuatOleh);
            }
        }

        if ($ticket->pic_id && $ticket->pic_id !== $actor->id && $ticket->pic_id !== $ticket->dibuat_oleh) {
            if ($ticket->pic) {
                $recipients->push($ticket->pic);
            }
        }

        foreach ($recipients->unique('id') as $recipient) {
            $recipient->notify(new TicketStatusBerubahNotification(
                ticket: $ticket,
                statusLama: $statusLama,
                statusBaru: $statusBaru,
                changedBy: $actor,
                catatan: $catatan,
            ));
        }

        // Notifikasi ke AkunPelanggan jika tiket berasal dari portal
        if ($ticket->sumber === SumberTicket::Portal) {
            $akunPelanggan = AkunPelanggan::where('pelanggan_id', $ticket->pelanggan_id)->first();
            if ($akunPelanggan) {
                $akunPelanggan->notify(new TicketStatusBerubahPelangganNotification(
                    ticket: $ticket,
                    statusLama: $statusLama,
                    statusBaru: $statusBaru,
                    catatan: $catatan,
                ));
            }
        }

        // Notifikasi WhatsApp ke Pelanggan
        if ($ticket->pelanggan && ! empty($ticket->pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildTicketParams($ticket, $catatan);
                $whatsappService->antrikanPesan(
                    noHp: $ticket->pelanggan->no_hp,
                    kodeTemplate: 'tiket_status_update',
                    params: $params,
                    referensi: $ticket,
                    jenis: "tiket_status_{$statusBaru->value}_{$ticket->histori()->count()}"
                );
            } catch (\Throwable $e) {
                Log::error('Gagal mengantrikan WA update tiket: '.$e->getMessage());
            }
        }
    }
}
