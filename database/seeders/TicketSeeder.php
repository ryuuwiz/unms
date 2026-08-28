<?php

namespace Database\Seeders;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    /**
     * Seed realistic sample tickets with chronological history logs.
     */
    public function run(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first() ?? User::first();
        $admin = User::where('email', 'admin@example.com')->first() ?? $superAdmin;
        $sales = User::where('email', 'sales@example.com')->first() ?? $admin;
        $teknisi = User::where('email', 'teknisi@example.com')->first() ?? $admin;
        $noc = User::where('email', 'noc@example.com')->first() ?? $admin;

        foreach ($this->daftarTiketContoh() as $data) {
            $pelanggan = Pelanggan::where('email', $data['pelanggan_email'])->first();

            if (! $pelanggan) {
                continue;
            }

            $layanan = $pelanggan->layanans()->first();

            $creator = match ($data['dibuat_oleh_role']) {
                'sales' => $sales,
                'teknisi' => $teknisi,
                'noc' => $noc,
                'super_admin' => $superAdmin,
                default => $admin,
            };

            $pic = match ($data['pic_role']) {
                'sales' => $sales,
                'teknisi' => $teknisi,
                'noc' => $noc,
                'admin' => $admin,
                default => null,
            };

            $createdDate = Carbon::now()->subDays($data['days_ago'])->setHour(rand(8, 16))->setMinute(rand(10, 50));
            $prioritas = $data['prioritas'];
            $slaTarget = $createdDate->copy()->addHours($prioritas->durasiSlaHours());

            // Buat Ticket
            $ticket = Ticket::create([
                'jenis' => $data['jenis'],
                'pelanggan_id' => $pelanggan->id,
                'layanan_pelanggan_id' => $data['pakai_layanan'] ? $layanan?->id : null,
                'prioritas' => $prioritas,
                'pic_id' => $pic?->id,
                'status' => $data['status_akhir'],
                'sumber' => $data['sumber'] ?? SumberTicket::Manual,
                'sla_target_selesai' => $slaTarget,
                'perlu_aktivasi_manual' => $data['perlu_aktivasi_manual'] ?? false,
                'deskripsi' => $data['deskripsi'],
                'dijadwalkan_pada' => $data['jadwal_days_ago'] !== null ? Carbon::now()->subDays($data['jadwal_days_ago'])->setHour(10)->setMinute(0) : null,
                'dibuat_oleh' => $creator->id,
                'created_at' => $createdDate,
                'updated_at' => $createdDate,
            ]);

            // Buat Divisi Pivot
            $divisis = is_array($data['divisi']) ? $data['divisi'] : [$data['divisi']];
            foreach ($divisis as $divisiItem) {
                DB::table('ticket_divisi')->insertOrIgnore([
                    'ticket_id' => $ticket->id,
                    'divisi' => $divisiItem instanceof DivisiTicket ? $divisiItem->value : $divisiItem,
                ]);
            }

            // Buat Histori Kronologis
            foreach ($data['histori'] as $hIndex => $historiItem) {
                $historiDate = $createdDate->copy()->addHours(($hIndex + 1) * 2);

                $actor = match ($historiItem['oleh_role']) {
                    'sales' => $sales,
                    'teknisi' => $teknisi,
                    'noc' => $noc,
                    'super_admin' => $superAdmin,
                    default => $admin,
                };

                TicketHistori::create([
                    'ticket_id' => $ticket->id,
                    'status_lama' => $historiItem['status_lama'],
                    'status_baru' => $historiItem['status_baru'],
                    'catatan' => $historiItem['catatan'],
                    'is_internal' => $historiItem['is_internal'] ?? false,
                    'oleh_pengguna_id' => $actor->id,
                    'created_at' => $historiDate,
                ]);
            }
        }
    }

    /**
     * Data realistis tiket operasional UNMS.
     *
     * @return array<int, array{
     *     jenis: JenisTicket,
     *     pelanggan_email: string,
     *     pakai_layanan: bool,
     *     prioritas: PrioritasTicket,
     *     divisi: DivisiTicket|array<int, DivisiTicket>,
     *     status_akhir: StatusTicket,
     *     dibuat_oleh_role: string,
     *     pic_role: ?string,
     *     days_ago: int,
     *     jadwal_days_ago: ?int,
     *     perlu_aktivasi_manual?: bool,
     *     sumber?: SumberTicket,
     *     deskripsi: string,
     *     histori: array<int, array{status_lama: ?StatusTicket, status_baru: StatusTicket, catatan: string, oleh_role: string, is_internal?: bool}>
     * }>
     */
    private function daftarTiketContoh(): array
    {
        return [
            // 1. Tiket Gangguan Darurat - Kabel Putus (Selesai)
            [
                'jenis' => JenisTicket::Gangguan,
                'pelanggan_email' => 'ahmad.fauzi@example.com',
                'pakai_layanan' => true,
                'prioritas' => PrioritasTicket::Darurat,
                'divisi' => DivisiTicket::Noc,
                'status_akhir' => StatusTicket::Selesai,
                'dibuat_oleh_role' => 'noc',
                'pic_role' => 'teknisi',
                'days_ago' => 3,
                'jadwal_days_ago' => 3,
                'deskripsi' => 'Kabel dropcore tertimpa ranting pohon di depan rumah pelanggan. Lampu indikator LOS merah kedip pada modem ONT Huawei.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Tiket gangguan dibuat otomatis oleh sistem NOC setelah deteksi optical link down.',
                        'oleh_role' => 'noc',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Diproses,
                        'catatan' => 'Tiket ditugaskan ke Teknisi Bambang. Tim meluncur ke lokasi dengan membawa splicer & kabel dropcore 100m.',
                        'oleh_role' => 'noc',
                    ],
                    [
                        'status_lama' => StatusTicket::Diproses,
                        'status_baru' => StatusTicket::MenungguKonfirmasi,
                        'catatan' => 'Penyambungan core FO selesai dilakukan. Hasil ukur optical power meter: -18.6 dBm (Normal). Koneksi internet kembali aktif.',
                        'oleh_role' => 'teknisi',
                    ],
                    [
                        'status_lama' => StatusTicket::MenungguKonfirmasi,
                        'status_baru' => StatusTicket::Selesai,
                        'catatan' => 'Verifikasi traffic live pada MikroTik router normal. Pelanggan telah mengonfirmasi koneksi lancar.',
                        'oleh_role' => 'admin',
                    ],
                ],
            ],

            // 2. Tiket Pemasangan Baru (Menunggu Konfirmasi & Perlu Aktivasi MikroTik)
            [
                'jenis' => JenisTicket::Pemasangan,
                'pelanggan_email' => 'yanti.susanti@example.com',
                'pakai_layanan' => false,
                'prioritas' => PrioritasTicket::Sedang,
                'divisi' => DivisiTicket::Teknisi,
                'status_akhir' => StatusTicket::MenungguKonfirmasi,
                'dibuat_oleh_role' => 'sales',
                'pic_role' => 'teknisi',
                'days_ago' => 1,
                'jadwal_days_ago' => 1,
                'deskripsi' => 'Pemasangan instalasi baru paket Home 30 Mbps di Cluster Arsyila Blok C No. 12. Penarikan kabel dari ODP-ARS-01 Port 3 (jarak survey 85 meter).',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Prospek mendaftar via sales. Tiket pemasangan diterbitkan.',
                        'oleh_role' => 'sales',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Diproses,
                        'catatan' => 'Teknisi berangkat ke lokasi instalasi.',
                        'oleh_role' => 'admin',
                    ],
                    [
                        'status_lama' => StatusTicket::Diproses,
                        'status_baru' => StatusTicket::MenungguKonfirmasi,
                        'catatan' => 'Instalasi kabel dropcore dan modem ZTE F609 selesai terpasang rapi. Redaman OPM -19.2 dBm. Menunggu aktivasi PPP Secret di NOC.',
                        'oleh_role' => 'teknisi',
                    ],
                ],
            ],

            // 3. Tiket Pemasangan Baru (Selesai dengan Flag Perlu Aktivasi)
            [
                'jenis' => JenisTicket::Pemasangan,
                'pelanggan_email' => 'zainal.abidin@example.com',
                'pakai_layanan' => false,
                'prioritas' => PrioritasTicket::Tinggi,
                'divisi' => DivisiTicket::Teknisi,
                'status_akhir' => StatusTicket::Selesai,
                'dibuat_oleh_role' => 'sales',
                'pic_role' => 'teknisi',
                'days_ago' => 4,
                'jadwal_days_ago' => 4,
                'perlu_aktivasi_manual' => true,
                'deskripsi' => 'Instalasi pelanggan baru paket Bisnis 50 Mbps. Butuh IP Static dan routing khusus.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Tiket pemasangan dibuat.',
                        'oleh_role' => 'sales',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Diproses,
                        'catatan' => 'Penugasan teknisi Bambang.',
                        'oleh_role' => 'admin',
                    ],
                    [
                        'status_lama' => StatusTicket::Diproses,
                        'status_baru' => StatusTicket::MenungguKonfirmasi,
                        'catatan' => 'Pemasangan fisik selesai. ONT telah dialiri daya.',
                        'oleh_role' => 'teknisi',
                    ],
                    [
                        'status_lama' => StatusTicket::MenungguKonfirmasi,
                        'status_baru' => StatusTicket::Selesai,
                        'catatan' => 'Disetujui admin. Ditandai perlu aktivasi manual ke MikroTik router.',
                        'oleh_role' => 'admin',
                    ],
                ],
            ],

            // 4. Tiket Gangguan Sedang (Sedang Diproses)
            [
                'jenis' => JenisTicket::Gangguan,
                'pelanggan_email' => 'budi.santoso@example.com',
                'pakai_layanan' => true,
                'prioritas' => PrioritasTicket::Sedang,
                'divisi' => DivisiTicket::Noc,
                'status_akhir' => StatusTicket::Diproses,
                'dibuat_oleh_role' => 'admin',
                'pic_role' => 'noc',
                'days_ago' => 0,
                'jadwal_days_ago' => 0,
                'deskripsi' => 'Pelanggan melaporkan kecepatan internet sering drop pada malam hari (pukul 20:00 - 22:00 WIB). Perlu pengecekan queue dan utilisasi bandwidth port ODP.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Aduan diterima customer service via telepon.',
                        'oleh_role' => 'admin',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Diproses,
                        'catatan' => 'NOC sedang melakukan monitoring utilisasi simple queue dan pengecekan profil bandwidth di MikroTik.',
                        'oleh_role' => 'noc',
                    ],
                ],
            ],

            // 5. Tiket Gangguan Baru (Belum Ditugaskan / Menunggu PIC)
            [
                'jenis' => JenisTicket::Gangguan,
                'pelanggan_email' => 'eka.pratama@example.com',
                'pakai_layanan' => true,
                'prioritas' => PrioritasTicket::Tinggi,
                'divisi' => DivisiTicket::Noc,
                'status_akhir' => StatusTicket::Baru,
                'dibuat_oleh_role' => 'admin',
                'pic_role' => null,
                'days_ago' => 0,
                'jadwal_days_ago' => null,
                'deskripsi' => 'Router kantor pelanggan restart berulang kali setelah pemadaman listrik PLN. Sinyal WiFi internal tidak muncul.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Aduan masuk dari kontak WhatsApp pelanggan.',
                        'oleh_role' => 'admin',
                    ],
                ],
            ],

            // 6. Tiket Pindah Alamat (Baru)
            [
                'jenis' => JenisTicket::PindahAlamat,
                'pelanggan_email' => 'citra.lestari@example.com',
                'pakai_layanan' => true,
                'prioritas' => PrioritasTicket::Sedang,
                'divisi' => DivisiTicket::Teknisi,
                'status_akhir' => StatusTicket::Baru,
                'dibuat_oleh_role' => 'sales',
                'pic_role' => null,
                'days_ago' => 2,
                'jadwal_days_ago' => null,
                'deskripsi' => 'Pelanggan pindah rumah dari Blok A4 No. 2 ke Blok B2 No. 10 dalam perumahan yang sama. Perlu relokasi kabel dropcore dan pemindahan ONT.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Permohonan relokasi alamat dicatat.',
                        'oleh_role' => 'sales',
                    ],
                ],
            ],

            // 7. Tiket Pencabutan (Selesai)
            [
                'jenis' => JenisTicket::Pencabutan,
                'pelanggan_email' => 'chandra.wijaya@example.com',
                'pakai_layanan' => true,
                'prioritas' => PrioritasTicket::Rendah,
                'divisi' => DivisiTicket::Teknisi,
                'status_akhir' => StatusTicket::Selesai,
                'dibuat_oleh_role' => 'admin',
                'pic_role' => 'teknisi',
                'days_ago' => 10,
                'jadwal_days_ago' => 9,
                'deskripsi' => 'Pelanggan mengajukan pemutusan layanan karena pindah tugas ke luar kota. Pengambilan modem ONT dan adaptor.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Permohonan terminasi layanan disetujui administrasi.',
                        'oleh_role' => 'admin',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Diproses,
                        'catatan' => 'Penugasan teknisi untuk pengambilan perangkat.',
                        'oleh_role' => 'admin',
                    ],
                    [
                        'status_lama' => StatusTicket::Diproses,
                        'status_baru' => StatusTicket::MenungguKonfirmasi,
                        'catatan' => 'Perangkat ONT ZTE & adaptor telah diambil dalam kondisi baik. Kabel dropcore telah dicabut dari ODP.',
                        'oleh_role' => 'teknisi',
                    ],
                    [
                        'status_lama' => StatusTicket::MenungguKonfirmasi,
                        'status_baru' => StatusTicket::Selesai,
                        'catatan' => 'Barang masuk ke inventaris gudang. Tiket resmi ditutup.',
                        'oleh_role' => 'admin',
                    ],
                ],
            ],

            // 8. Tiket Batal (Dibatalkan oleh Sales)
            [
                'jenis' => JenisTicket::Pemasangan,
                'pelanggan_email' => 'arya.saloka@example.com',
                'pakai_layanan' => false,
                'prioritas' => PrioritasTicket::Sedang,
                'divisi' => DivisiTicket::Teknisi,
                'status_akhir' => StatusTicket::Batal,
                'dibuat_oleh_role' => 'sales',
                'pic_role' => null,
                'days_ago' => 5,
                'jadwal_days_ago' => null,
                'deskripsi' => 'Permohonan pemasangan baru di perumahan bukit asri.',
                'histori' => [
                    [
                        'status_lama' => null,
                        'status_baru' => StatusTicket::Baru,
                        'catatan' => 'Tiket permohonan dibuat.',
                        'oleh_role' => 'sales',
                    ],
                    [
                        'status_lama' => StatusTicket::Baru,
                        'status_baru' => StatusTicket::Batal,
                        'catatan' => 'Prospek membatalkan pemasangan karena belum mendapat izin dari pengelola perumahan.',
                        'oleh_role' => 'sales',
                    ],
                ],
            ],
        ];
    }
}
