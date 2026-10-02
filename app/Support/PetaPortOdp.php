<?php

namespace App\Support;

use App\Enums\StatusOdpPort;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\TicketPemasangan;

/**
 * Peta Port ODP: seluruh port satu ODP beserta status tampilannya (Kosong, Terpakai, Rusak,
 * Dipesan) dan apakah port boleh dipilih tiket yang sedang dibuka -- lihat CONTEXT.md
 * "Peta Port ODP" dan "Port ODP". Dipesan tetap status turunan, tidak pernah disimpan.
 */
final class PetaPortOdp
{
    /**
     * @param  list<array{id: int, nomor: int, status: string, label: string, keterangan: string|null, dapat_dipilih: bool, alasan: string|null, terpilih: bool}>  $ports
     */
    private function __construct(
        public readonly Odp $odp,
        public readonly array $ports,
    ) {}

    /**
     * @param  int|null  $ticketId  Tiket yang sedang dibuka; pesanannya sendiri tidak dihitung Dipesan.
     * @param  int|null  $portMilikTiketId  Port yang sudah tersimpan untuk tiket ini; tetap boleh dipilih walau Terpakai.
     * @param  int|null  $portTerpilihId  Port yang disorot (pilihan Teknisi saat ini, atau port layanan pada peta baca-saja).
     */
    public static function untuk(Odp $odp, ?int $ticketId = null, ?int $portMilikTiketId = null, ?int $portTerpilihId = null): self
    {
        $portDipesan = TicketPemasangan::portDipesan($ticketId);

        $ports = $odp->ports()
            ->with('layananPelanggan.pelanggan')
            ->orderBy('nomor_port')
            ->get()
            ->map(fn (OdpPort $port): array => self::petakan($port, $portDipesan[$port->id] ?? null, $portMilikTiketId, $portTerpilihId))
            ->all();

        return new self($odp, $ports);
    }

    /**
     * @return array{id: int, nomor: int, status: string, label: string, keterangan: string|null, dapat_dipilih: bool, alasan: string|null, terpilih: bool}
     */
    private static function petakan(OdpPort $port, ?string $dipesanOleh, ?int $portMilikTiketId, ?int $portTerpilihId): array
    {
        $milikTiket = $port->id === $portMilikTiketId;
        $layanan = $port->layananPelanggan;

        [$status, $label, $keterangan, $alasan] = match (true) {
            $port->status === StatusOdpPort::Rusak => ['rusak', 'Rusak', null, 'Port rusak dan tidak bisa dipakai.'],
            $port->status === StatusOdpPort::Terpakai => [
                'terpakai',
                'Terpakai',
                $layanan ? "{$layanan->site_id} · {$layanan->pelanggan?->namaLengkap()} · {$layanan->pelanggan?->no_reg}" : null,
                'Port sudah terpakai layanan lain.',
            ],
            $dipesanOleh !== null => ['dipesan', 'Dipesan', "Dipesan oleh {$dipesanOleh}", "Port sudah dipesan tiket {$dipesanOleh}."],
            default => ['kosong', 'Kosong', null, null],
        };

        return [
            'id' => $port->id,
            'nomor' => $port->nomor_port,
            'status' => $status,
            'label' => $label,
            'keterangan' => $keterangan,
            'dapat_dipilih' => $milikTiket || $alasan === null,
            'alasan' => $milikTiket ? null : $alasan,
            'terpilih' => $port->id === $portTerpilihId,
        ];
    }

    public function jumlahTerpakai(): int
    {
        return count(array_filter($this->ports, fn (array $port): bool => $port['status'] === 'terpakai'));
    }

    public function jumlahPort(): int
    {
        return count($this->ports);
    }

    /**
     * Alasan port tidak boleh dipilih tiket ini, atau null bila boleh.
     */
    public function alasanTidakDapatDipilih(int $portId): ?string
    {
        foreach ($this->ports as $port) {
            if ($port['id'] === $portId) {
                return $port['alasan'];
            }
        }

        return 'Port yang dipilih bukan milik ODP terpilih.';
    }
}
