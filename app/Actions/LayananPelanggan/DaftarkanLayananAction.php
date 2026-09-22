<?php

namespace App\Actions\LayananPelanggan;

use App\Enums\JenisTagihanPertama;
use App\Enums\PriceMode;
use App\Enums\StatusLayanan;
use App\Exceptions\DuplikatLayananAktifException;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Promo;
use App\Models\Ticket;
use App\Services\Billing\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DaftarkanLayananAction
{
    public function __construct(private BillingService $billing) {}

    /**
     * Registrasi Data Registrasi Billing baru: murni komersial (paket, harga, alamat, tagihan
     * pertama). Router, IP Pool, dan Username PPP BELUM diisi di sini -- itu baru terjadi lewat
     * Aktivasi Pemasangan di Ticket (lihat docs/plan/ticket-pemasangan-workflow.md dan
     * CONTEXT.md "Aktivasi Pemasangan"). Layanan tersimpan berstatus PROSES.
     *
     * @param  array{
     *     pelanggan_id: int, paket_layanan_id: int, price_mode: string, price_custom: float|null,
     *     nama_site: string|null, alamat_pemasangan: string|null, latitude: float|null, longitude: float|null,
     *     tanggal_mulai: string, jenis_tagihan_pertama: string, promo: Promo|null, ticket_id: int|null, dibuat_oleh: int|null,
     * }  $data
     */
    public function execute(array $data): LayananPelanggan
    {
        $paket = PaketLayanan::findOrFail($data['paket_layanan_id']);
        $mulai = Carbon::parse($data['tanggal_mulai']);
        $expired = $paket->masa_aktif_satuan->value === 'bulan'
            ? $mulai->copy()->addMonths($paket->masa_aktif_nilai)
            : $mulai->copy()->addDays($paket->masa_aktif_nilai);

        return DB::transaction(function () use ($data, $expired) {
            $layanan = LayananPelanggan::create([
                'pelanggan_id' => $data['pelanggan_id'],
                'paket_layanan_id' => $data['paket_layanan_id'],
                'price_mode' => $data['price_mode'],
                'price_custom' => $data['price_mode'] === PriceMode::Custom->value ? $data['price_custom'] : null,
                'nama_site' => $data['nama_site'] ?: null,
                'alamat_pemasangan' => $data['alamat_pemasangan'] ?: null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'status' => StatusLayanan::Proses,
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_expired' => $expired->toDateString(),
            ]);

            $this->billing->generateFirstInvoice(
                layanan: $layanan,
                jenis: JenisTagihanPertama::from($data['jenis_tagihan_pertama']),
                dibuatOleh: $data['dibuat_oleh'] ?? null,
                promo: $data['promo'] ?? null,
            );

            if ($data['ticket_id'] ?? null) {
                Ticket::whereKey($data['ticket_id'])
                    ->where('pelanggan_id', $data['pelanggan_id'])
                    ->where('perlu_aktivasi_manual', true)
                    ->update(['layanan_pelanggan_id' => $layanan->id, 'perlu_aktivasi_manual' => false]);
            }

            return $layanan;
        });
    }

    /**
     * Cek duplikasi: pelanggan tidak boleh memiliki layanan aktif/proses/suspend dengan
     * router & paket yang persis sama (tetap mendukung multi-site dengan paket/router berbeda).
     *
     * @throws DuplikatLayananAktifException
     */
    public function assertBelumAdaDuplikat(int $pelangganId, int $routerId, int $paketId): void
    {
        $ada = LayananPelanggan::where('pelanggan_id', $pelangganId)
            ->where('router_id', $routerId)
            ->where('paket_layanan_id', $paketId)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Proses, StatusLayanan::Suspend])
            ->exists();

        if ($ada) {
            throw new DuplikatLayananAktifException;
        }
    }
}
