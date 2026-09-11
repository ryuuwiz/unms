Sprint B1 — Lazy Load Status PPP & Isolasi Exception per Layanan
Konteks
Halaman Pelanggan/Show saat ini memanggil loadPppStatuses() di dalam mount(), yang melakukan koneksi live ke RouterOS untuk setiap layanan milik pelanggan tersebut secara sinkron sebelum halaman dirender. Kalau salah satu router lambat/down, seluruh halaman ikut lambat/error — padahal data lain di halaman itu (invoice, tiket, data pelanggan) tidak ada hubungannya dengan status router.
Independen dari Track A — bisa dikerjakan paralel.
Tujuan sprint ini:
Halaman render duluan tanpa menunggu status PPP.
Kalau satu router gagal dihubungi, hanya status layanan itu yang terdampak, bukan seluruh halaman.
Scope file
app/Livewire/Pelanggan/Show.php
resources/views/livewire/pelanggan/show.blade.php (atau nama file blade yang sesuai — konfirmasi dari render() method di file PHP-nya)
Test existing terkait (kemungkinan tests/Feature/Livewire/PelangganShowPppStatusTest.php — cek dulu apakah sudah ada sebelum membuat baru)
Langkah 0 — Baca dulu implementasi existing secara utuh
Sebelum mengubah, baca:

Isi lengkap mount() di Show.php untuk melihat urutan pemanggilan loadPppStatuses() relatif terhadap load data lain.

Isi loadPppStatuses() untuk melihat apakah sudah ada try/catch parsial, dan bagaimana struktur data $pppStatuses disimpan (array keyed by apa — layanan_id? index?) supaya perubahan tidak merusak bagian blade yang sudah membaca struktur ini.

Signature getPppStatus() di MikrotikService.php — exception apa saja yang bisa dilempar (MikrotikConnectionException? Exception generik?).
Implementasi
1. Pindahkan pemanggilan dari mount() ke wire:init
Di Show.php:
PHPpublic function mount(Pelanggan $pelanggan): void
{
    $this->pelanggan = $pelanggan;
    $this->pppStatuses = []; // inisialisasi kosong, akan diisi via wire:init

    // ... load data lain yang TIDAK terkait Mikrotik tetap di sini seperti biasa ...
    // HAPUS pemanggilan loadPppStatuses() dari sini
}

public function loadPppStatuses(): void
{
    foreach ($this->pelanggan->layanans as $layanan) {
        try {
            $this->pppStatuses[$layanan->id] = app(MikrotikService::class)
                ->getPppStatus($layanan->router, $layanan->ppp_username);
        } catch (MikrotikConnectionException $e) {
            $this->pppStatuses[$layanan->id] = [
                'error' => true,
                'message' => 'Router tidak dapat dihubungi',
                'online' => null,
            ];

            Log::warning('Gagal memuat status PPP', [
                'layanan_id' => $layanan->id,
                'router_id' => $layanan->router_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}




Sesuaikan exception class yang ditangkap dengan hasil investigasi Langkah 0 — kalau getPppStatus() bisa melempar lebih dari satu jenis exception yang relevan (misalnya timeout vs auth failure), tangkap semua yang relevan, tapi jangan catch \Throwable generik di sini supaya error yang benar-benar tidak terduga (bug) tetap terlihat di log/error tracking, bukan tertelan diam-diam sebagai "router tidak dapat dihubungi".
Di blade view, pada bagian yang menampilkan status PPP:
Auto<div wire:init="loadPppStatuses">
    @if (empty($pppStatuses))
        <div class="{{-- gunakan class skeleton/loading existing kalau ada pola serupa di codebase --}}">
            Memuat status koneksi...
        </div>
    @else
        @foreach ($pelanggan->layanans as $layanan)
            @php $status = $pppStatuses[$layanan->id] ?? null; @endphp

            @if ($status && ($status['error'] ?? false))
                <span class="{{-- class warning/error existing --}}">{{ $status['message'] }}</span>
            @elseif ($status)
                {{-- render status normal seperti sebelumnya, sesuaikan dengan markup existing --}}
            @endif
        @endforeach
    @endif
</div>




Penting: Markup asli untuk menampilkan status "online"/"offline" per layanan kemungkinan sudah ada di blade view sebelumnya — jangan tulis ulang dari nol, adaptasikan struktur if/else di atas ke markup yang sudah ada, cukup tambahkan cabang untuk kasus error.
2. Pastikan method refreshPppStatus() (kalau sudah ada) tidak terpengaruh
Kalau sudah ada method terpisah untuk refresh manual (disebutkan di analisis sebelumnya), pastikan method itu tetap berfungsi independen dari loadPppStatuses() — method refresh manual sebaiknya memanggil ulang untuk satu layanan spesifik, bukan seluruh daftar, dan tetap pakai try/catch yang sama.
Test
Update atau buat tests/Feature/Livewire/PelangganShowPppStatusTest.php:
PHPit('halaman tetap render sukses walau salah satu router tidak dapat dihubungi', function () {
    $pelanggan = Pelanggan::factory()->has(LayananPelanggan::factory())->create();

    $this->mock(MikrotikService::class, function ($mock) {
        $mock->shouldReceive('getPppStatus')
            ->andThrow(new MikrotikConnectionException('Connection timeout'));
    });

    Livewire::test(Show::class, ['pelanggan' => $pelanggan])
        ->call('loadPppStatuses')
        ->assertOk()
        ->assertSee('Router tidak dapat dihubungi'); // sesuaikan dengan teks asli yang dipakai
});

it('loadPppStatuses tidak dipanggil otomatis saat mount, hanya via wire:init', function () {
    $pelanggan = Pelanggan::factory()->has(LayananPelanggan::factory())->create();

    $mock = Mockery::mock(MikrotikService::class);
    $mock->shouldNotReceive('getPppStatus'); // belum dipanggil sebelum wire:init trigger
    $this->app->instance(MikrotikService::class, $mock);

    Livewire::test(Show::class, ['pelanggan' => $pelanggan])
        ->assertOk();
    // Tidak memanggil ->call('loadPppStatuses') di sini — memverifikasi mount() saja tidak trigger panggilan Mikrotik
});

it('satu layanan gagal tidak menghalangi layanan lain menampilkan status sukses', function () {
    $pelanggan = Pelanggan::factory()->create();
    $layananSukses = LayananPelanggan::factory()->for($pelanggan)->create();
    $layananGagal = LayananPelanggan::factory()->for($pelanggan)->create();

    $this->mock(MikrotikService::class, function ($mock) use ($layananSukses, $layananGagal) {
        $mock->shouldReceive('getPppStatus')
            ->withArgs(fn ($router, $username) => $username === $layananSukses->ppp_username)
            ->andReturn(['online' => true]);

        $mock->shouldReceive('getPppStatus')
            ->withArgs(fn ($router, $username) => $username === $layananGagal->ppp_username)
            ->andThrow(new MikrotikConnectionException('timeout'));
    });

    Livewire::test(Show::class, ['pelanggan' => $pelanggan])
        ->call('loadPppStatuses')
        ->assertOk();

    // assert kedua layanan tetap tampil, satu dengan status normal satu dengan status error
});




Acceptance criteria

[ ] loadPppStatuses() tidak lagi dipanggil di mount(), dipanggil via wire:init dari blade.

[ ] Halaman tetap render sukses (assertOk()) walau salah satu/semua router gagal dihubungi.

[ ] Exception per-layanan diisolasi — satu layanan gagal tidak mempengaruhi tampilan layanan lain.

[ ] Log warning tercatat saat terjadi kegagalan (untuk observability, bukan silent fail total).

[ ] Semua test baru dan existing lulus.
Yang TIDAK boleh dilakukan di sprint ini

Jangan tambahkan caching di sprint ini — itu Sprint B2, dikerjakan terpisah supaya kalau ada masalah, mudah diketahui sprint mana penyebabnya.

Jangan ubah getPppStatus() di MikrotikService.php — sprint ini hanya menyentuh sisi pemanggil (Livewire), bukan service-nya.

Jangan catch \Throwable generik — hanya exception spesifik yang relevan dengan kegagalan koneksi router.
Setelah selesai
Laporkan:
Struktur $pppStatuses final (keyed by apa) dan apakah ini mengubah kontrak yang dipakai bagian blade lain.
Exception class yang ditangkap dan sumber konfirmasinya (dari MikrotikService.php yang mana).
Hasil test run.



