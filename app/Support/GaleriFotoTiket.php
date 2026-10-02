<?php

namespace App\Support;

use App\Enums\Ticket\JenisTicket;
use App\Models\Ticket;
use App\Models\TicketHistori;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Galeri Foto Tiket: satu-satunya tempat foto bukti sebuah tiket tampil besar, dikelompokkan
 * per kategori -- lihat CONTEXT.md "Galeri Foto Tiket" dan "Foto Pengerjaan Lapangan".
 */
final class GaleriFotoTiket
{
    /**
     * Kategori Foto Pengerjaan Lapangan & Foto Kendala milik tiket: koleksi media => [label, wajib].
     */
    private const KATEGORI_TIKET = [
        'foto_speedtest' => ['Speedtest', true],
        'foto_tanda_tangan_mou' => ['Tanda Tangan MOU', true],
        'foto_pemasangan' => ['Bukti Pemasangan', false],
        'foto_bersama_pelanggan_teknisi' => ['Foto Bersama Pelanggan & Teknisi', false],
        'foto_kendala' => ['Foto Kendala', false],
    ];

    /**
     * @param  list<array{kunci: string, label: string, wajib: bool, foto: list<array{id: int, url: string, waktu: string, kategori: string}>}>  $kelompok
     */
    private function __construct(public readonly array $kelompok) {}

    /**
     * Kategori wajib (Speedtest, MOU) hanya relevan di Ticket Pemasangan yang mengacu ke layanan,
     * dan tetap tampil walau kosong. Kategori lain hanya tampil bila sudah ada fotonya.
     */
    public static function untuk(Ticket $ticket): self
    {
        $pakaiKategoriWajib = $ticket->jenis === JenisTicket::Pemasangan && $ticket->layanan_pelanggan_id !== null;
        $kelompok = [];

        foreach (self::KATEGORI_TIKET as $koleksi => [$label, $wajib]) {
            $kelompok[] = self::kelompok($koleksi, $label, $wajib && $pakaiKategoriWajib, $ticket->getMedia($koleksi)->all());
        }

        $fotoHistori = $ticket->histori
            ->flatMap(fn (TicketHistori $histori) => $histori->getMedia('foto_pengerjaan'))
            ->sortBy('id')
            ->all();
        $kelompok[] = self::kelompok('foto_pengerjaan', 'Foto Bukti Pengerjaan', false, $fotoHistori);

        return new self(array_values(array_filter(
            $kelompok,
            fn (array $item): bool => $item['wajib'] || $item['foto'] !== [],
        )));
    }

    /**
     * @param  array<int, Media>  $media
     * @return array{kunci: string, label: string, wajib: bool, foto: list<array{id: int, url: string, waktu: string, kategori: string}>}
     */
    private static function kelompok(string $kunci, string $label, bool $wajib, array $media): array
    {
        return [
            'kunci' => $kunci,
            'label' => $label,
            'wajib' => $wajib,
            'foto' => array_values(array_map(fn (Media $item): array => [
                'id' => $item->id,
                'url' => $item->getUrl(),
                'waktu' => $item->created_at?->format('d M Y, H:i') ?? '-',
                'kategori' => $label,
            ], $media)),
        ];
    }

    /**
     * Semua foto berurutan untuk navigasi lightbox.
     *
     * @return list<array{id: int, url: string, waktu: string, kategori: string}>
     */
    public function semuaFoto(): array
    {
        return array_merge(...array_map(fn (array $item): array => $item['foto'], $this->kelompok));
    }

    public function kosong(): bool
    {
        return $this->kelompok === [];
    }
}
