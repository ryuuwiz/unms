<?php

namespace Database\Seeders;

use App\Enums\Wa\KategoriTemplateWa;
use App\Models\WaTemplate;
use Illuminate\Database\Seeder;

class WaTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'kode' => 'pengingat_tagihan_h3',
                'nama' => 'Pengingat Tagihan H-3 (Tagihan Baru Terbit)',
                'kategori' => KategoriTemplateWa::Tagihan,
                'konten' => "Selamat pagi/siang Bapak/Ibu {nama_pelanggan}\n\nInformasi Tagihan\nJatuh Tempo: {jatuh_tempo}\n\nKami informasikan bahwa tagihan layanan internet Anda telah tersedia.\nTerima kasih telah menggunakan layanan {nama_brand}\nApabila pembayaran sudah dilakukan, mohon abaikan informasi ini.\n\nNo Registrasi: {no_reg}\nKode Bayar: {kode_bayar}\n\nRincian Tagihan:\n1. {site_id} - {nama_paket} ({total_tagihan})\n\nTotal Tagihan : {total_tagihan}\nStatus Internet: {status_internet}\n\nBayar online tanpa login melalui tautan berikut:\n{link_pembayaran}\n\nSilahkan selesaikan pembayaran sebelum jatuh tempo untuk menjaga layanan tetap aktif.\n\nWaspada Penipuan\nJangan lakukan pembayaran ke rekening pribadi/nomor yang tidak terdaftar. Informasi resmi hanya melalui kanal resmi {nama_brand}.\n\nHormat kami,\n{nama_brand}\n\nPesan ini dikirim oleh sistem informasi pelanggan {nama_brand}.",
                'keterangan' => 'Dikirimkan 3 hari sebelum tanggal jatuh tempo invoice.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'pengingat_tagihan_h1',
                'nama' => 'Pengingat Tagihan H-1 (Jatuh Tempo Besok)',
                'kategori' => KategoriTemplateWa::Tagihan,
                'konten' => "Selamat pagi/siang Bapak/Ibu {nama_pelanggan}\n\nPengingat: tagihan Anda akan JATUH TEMPO BESOK.\nJatuh Tempo: {jatuh_tempo}\n\nKami informasikan bahwa tagihan layanan internet Anda telah tersedia.\nTerima kasih telah menggunakan layanan {nama_brand}\nApabila pembayaran sudah dilakukan, mohon abaikan informasi ini.\n\nNo Registrasi: {no_reg}\nKode Bayar: {kode_bayar}\n\nRincian Tagihan:\n1. {site_id} - {nama_paket} ({total_tagihan})\n\nTotal Tagihan : {total_tagihan}\nStatus Internet: {status_internet}\n\nBayar online tanpa login melalui tautan berikut:\n{link_pembayaran}\n\nSilahkan selesaikan pembayaran sebelum jatuh tempo untuk menjaga layanan tetap aktif.\n\nWaspada Penipuan\nJangan lakukan pembayaran ke rekening pribadi/nomor yang tidak terdaftar. Informasi resmi hanya melalui kanal resmi {nama_brand}.\n\nHormat kami,\n{nama_brand}\n\nPesan ini dikirim oleh sistem informasi pelanggan {nama_brand}.",
                'keterangan' => 'Dikirimkan 1 hari sebelum tanggal jatuh tempo invoice.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'pengingat_tagihan_h0',
                'nama' => 'Pengingat Tagihan Hari-H (Jatuh Tempo Hari Ini)',
                'kategori' => KategoriTemplateWa::Tagihan,
                'konten' => "Selamat pagi/siang Bapak/Ibu {nama_pelanggan}\n\nPENTING: tagihan Anda JATUH TEMPO HARI INI.\nJatuh Tempo: {jatuh_tempo}\n\nKami informasikan bahwa tagihan layanan internet Anda telah tersedia.\nTerima kasih telah menggunakan layanan {nama_brand}\nApabila pembayaran sudah dilakukan, mohon abaikan informasi ini.\n\nNo Registrasi: {no_reg}\nKode Bayar: {kode_bayar}\n\nRincian Tagihan:\n1. {site_id} - {nama_paket} ({total_tagihan})\n\nTotal Tagihan : {total_tagihan}\nStatus Internet: {status_internet}\n\nBayar online tanpa login melalui tautan berikut:\n{link_pembayaran}\n\nMohon segera selesaikan pembayaran hari ini untuk menghindari pemutusan/isolir layanan otomatis oleh sistem.\n\nWaspada Penipuan\nJangan lakukan pembayaran ke rekening pribadi/nomor yang tidak terdaftar. Informasi resmi hanya melalui kanal resmi {nama_brand}.\n\nHormat kami,\n{nama_brand}\n\nPesan ini dikirim oleh sistem informasi pelanggan {nama_brand}.",
                'keterangan' => 'Dikirimkan pada hari-H tanggal jatuh tempo invoice.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'pengingat_tagihan_tunggakan',
                'nama' => 'Peringatan Tagihan Menunggak (Overdue / Isolir)',
                'kategori' => KategoriTemplateWa::Tagihan,
                'konten' => "Selamat pagi/siang Bapak/Ibu {nama_pelanggan}\n\nPERINGATAN: tagihan Anda telah melewati jatuh tempo dan berisiko isolir.\nJatuh Tempo: {jatuh_tempo}\n\nKami informasikan bahwa tagihan layanan internet Anda telah tersedia.\nTerima kasih telah menggunakan layanan {nama_brand}\nApabila pembayaran sudah dilakukan, mohon abaikan informasi ini.\n\nNo Registrasi: {no_reg}\nKode Bayar: {kode_bayar}\n\nRincian Tagihan:\n1. {site_id} - {nama_paket} ({total_tagihan})\n\nTotal Tagihan : {total_tagihan}\nStatus Internet: {status_internet}\n\nBayar online tanpa login melalui tautan berikut:\n{link_pembayaran}\n\nSegera lakukan pelunasan untuk menghindari pemutusan/isolir layanan otomatis oleh sistem.\nInfo & Bantuan: {whatsapp_perusahaan}\n\nWaspada Penipuan\nJangan lakukan pembayaran ke rekening pribadi/nomor yang tidak terdaftar. Informasi resmi hanya melalui kanal resmi {nama_brand}.\n\nHormat kami,\n{nama_brand}\n\nPesan ini dikirim oleh sistem informasi pelanggan {nama_brand}.",
                'keterangan' => 'Dikirimkan berkala setelah melewati tanggal jatuh tempo.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'pembayaran_konfirmasi',
                'nama' => 'Konfirmasi Pembayaran Lunas',
                'kategori' => KategoriTemplateWa::Pembayaran,
                'konten' => "KUITANSI PEMBAYARAN LUNAS\n\nTerima kasih Bapak/Ibu {nama_pelanggan}.\nPembayaran tagihan {no_invoice} sebesar {jumlah_dibayar} telah kami terima dengan sukses pada {tanggal_bayar}.\n\nMetode: {metode_bayar}\nRef: {referensi_transaksi}\nLayanan internet Anda ({nama_paket}) aktif.\n\nSalam,\n{nama_brand}",
                'keterangan' => 'Dikirimkan saat tagihan diverifikasi lunas.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'tiket_dibuat',
                'nama' => 'Konfirmasi Tiket Masuk (Ke Pelanggan)',
                'kategori' => KategoriTemplateWa::Tiket,
                'konten' => "Halo {nama_pelanggan},\n\nLaporan tiket kendala Anda telah kami terima dengan detail:\nNomor Tiket: {nomor_tiket}\nJenis: {jenis_tiket}\nKendala: {deskripsi}\nEstimasi Target Selesai: {sla_target}\n\nTim teknis kami sedang menindaklanjuti laporan Anda.\nCek status penanganan: {link_portal_tiket}\n\nSalam,\n{nama_brand}",
                'keterangan' => 'Dikirimkan ke pelanggan saat tiket baru terdaftar.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'tiket_status_update',
                'nama' => 'Pembaruan Status Tiket (Ke Pelanggan)',
                'kategori' => KategoriTemplateWa::Tiket,
                'konten' => "Update Tiket {nomor_tiket}:\n\nYth. {nama_pelanggan}, status laporan kendala Anda saat ini telah diperbarui menjadi: *{status_tiket}*.\nCatatan: {catatan_histori}\n\nDetail penanganan: {link_portal_tiket}\nSalam,\n{nama_brand}",
                'keterangan' => 'Dikirimkan ke pelanggan saat ada perubahan status atau catatan baru pada tiket.',
                'is_aktif' => true,
            ],
            [
                'kode' => 'tiket_penugasan_teknisi',
                'nama' => 'Disposisi Penugasan Tiket (Ke Teknisi / PIC)',
                'kategori' => KategoriTemplateWa::Tiket,
                'konten' => "[DISPOSISI TIKET KERJA] Halo {nama_pic},\n\nAnda ditugaskan menangani tiket berikut:\nNomor: {nomor_tiket}\nJenis: {jenis_tiket} (Prioritas: {prioritas})\nPelanggan: {nama_pelanggan} ({no_hp_pelanggan})\nAlamat: {alamat}\nKendala: {deskripsi}\nTarget SLA: {sla_target}\n\nBuka Tiket: {link_tiket}",
                'keterangan' => 'Dikirimkan ke nomor WhatsApp staf teknisi saat ditugaskan menangani tiket.',
                'is_aktif' => true,
            ],
        ];

        foreach ($templates as $template) {
            WaTemplate::updateOrCreate(
                ['kode' => $template['kode']],
                $template
            );
        }
    }
}
