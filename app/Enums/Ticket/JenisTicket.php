<?php

namespace App\Enums\Ticket;

enum JenisTicket: string
{
    case Pemasangan = 'pemasangan';
    case Pencabutan = 'pencabutan';
    case Gangguan = 'gangguan';
    case PindahAlamat = 'pindah_alamat';

    public function label(): string
    {
        return match ($this) {
            self::Pemasangan => 'Pemasangan',
            self::Pencabutan => 'Pencabutan',
            self::Gangguan => 'Gangguan',
            self::PindahAlamat => 'Pindah Alamat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pemasangan => 'emerald',
            self::Pencabutan => 'zinc',
            self::Gangguan => 'rose',
            self::PindahAlamat => 'amber',
        };
    }

    /**
     * Panduan Alur Tiket (lihat CONTEXT.md): langkah kerja per divisi untuk jenis ini. Isi mengikuti
     * aturan sistem (TicketPolicy, StatusTicket::transisiValid(), gate Pemasangan) -- ubah bersama
     * aturannya.
     *
     * @return array{sebelum: list<string>, divisi: array<string, list<string>>, penutupan: list<string>}
     */
    public function panduan(): array
    {
        return match ($this) {
            self::Pemasangan => [
                'sebelum' => [
                    'Pelanggan didaftarkan (Sales/Admin).',
                    'Admin membuat Data Registrasi Billing (status PROSES, tagihan pertama langsung terbit).',
                    'Ticket Pemasangan dibuat mengacu ke layanan tersebut; keempat divisi ditugaskan otomatis.',
                ],
                'divisi' => [
                    DivisiTicket::Teknisi->value => [
                        'Pilih ODP dan port, lalu upload minimal 1 foto pemasangan (status Teknisi jadi On Progress).',
                        'Tunggu NOC menjalankan Aktivasi Pemasangan.',
                        'Upload foto speedtest, foto tanda tangan MOU, dan foto bersama pelanggan.',
                        'Klik "Tandai Selesai".',
                    ],
                    DivisiTicket::Noc->value => [
                        'Tunggu status Teknisi minimal On Progress (ODP + foto pemasangan sudah ada).',
                        'Klik "Aktivasi Pemasangan": pilih Router dan IP Pool; PPP username dibuat otomatis dan layanan jadi Aktif.',
                        'Bila provisioning ke MikroTik gagal, klik "Provisi Ulang".',
                        'Klik "Proses NOC", pilih Selesai, dan isi Catatan Proses.',
                    ],
                    DivisiTicket::CustomerService->value => [
                        'Konfirmasi ke pelanggan bahwa layanan sudah terpasang dan berjalan.',
                        'Klik "Proses Customer Service", pilih Selesai, dan isi hasil konfirmasi di Catatan Proses.',
                    ],
                    DivisiTicket::Admin->value => [
                        'Tunggu invoice pertama layanan berstatus Lunas.',
                        'Klik "Proses Admin", pilih Selesai (bisa sekaligus ubah paket bila perlu), dan isi Catatan Proses.',
                    ],
                ],
                'penutupan' => [
                    'Begitu keempat divisi Selesai, tiket otomatis Selesai dan status pelanggan jadi Pemasangan Selesai.',
                ],
            ],
            self::Gangguan => [
                'sebelum' => [],
                'divisi' => [
                    DivisiTicket::Noc->value => [
                        'Cek dari jarak jauh: status layanan dan sesi PPP di router.',
                        'Bila bisa diselesaikan tanpa ke lapangan: Ubah Status ke Diproses, lalu Menunggu Konfirmasi.',
                        'Bila perlu ke lapangan: klik "Assign PIC" dan pilih Teknisi.',
                    ],
                    DivisiTicket::Teknisi->value => [
                        'Ubah Status ke Diproses saat mulai menangani.',
                        'Kerjakan di lapangan, lalu "Tambah Catatan" beserta foto pengerjaan.',
                        'Ubah Status ke Menunggu Konfirmasi.',
                    ],
                ],
                'penutupan' => [
                    'NOC/Admin memastikan layanan normal, lalu Ubah Status ke Selesai.',
                    'Bila belum normal, kembalikan ke Diproses.',
                ],
            ],
            self::Pencabutan => [
                'sebelum' => [],
                'divisi' => [
                    DivisiTicket::CustomerService->value => [
                        'Konfirmasi alasan dan jadwal pencabutan ke pelanggan.',
                        'Catat hasilnya lewat "Tambah Catatan".',
                    ],
                    DivisiTicket::Teknisi->value => [
                        'Ubah Status ke Diproses saat berangkat.',
                        'Cabut perangkat (ONT/kabel) dan catat lewat "Tambah Catatan".',
                        'Kembalikan perangkat ke inventaris lewat Mutasi Barang.',
                        'Ubah Status ke Menunggu Konfirmasi.',
                    ],
                ],
                'penutupan' => [
                    'NOC/Admin mengubah status ke Selesai.',
                    'Layanan otomatis jadi Berhenti dan PPP secret dihapus dari router; tagihan lama yang belum lunas tetap terbuka.',
                ],
            ],
            self::PindahAlamat => [
                'sebelum' => [
                    'Alamat baru pelanggan diubah lebih dulu di data Pelanggan, baru tiket dibuat.',
                ],
                'divisi' => [
                    DivisiTicket::Noc->value => [
                        'Bila lokasi baru berbeda segmen jaringan, sesuaikan router/IP Pool lewat Edit layanan.',
                        'Catat perubahan lewat "Tambah Catatan".',
                    ],
                    DivisiTicket::Teknisi->value => [
                        'Ubah Status ke Diproses saat berangkat.',
                        'Pasang di alamat baru dan catat lewat "Tambah Catatan".',
                        'Ubah Status ke Menunggu Konfirmasi.',
                    ],
                ],
                'penutupan' => [
                    'Admin mengubah status ke Selesai.',
                    'Terbitkan invoice manual biaya pindah (banner muncul otomatis di tiket).',
                ],
            ],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pemasangan => 'wrench-screwdriver',
            self::Pencabutan => 'archive-box-x-mark',
            self::Gangguan => 'exclamation-triangle',
            self::PindahAlamat => 'arrow-path',
        };
    }
}
