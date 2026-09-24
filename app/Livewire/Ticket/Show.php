<?php

namespace App\Livewire\Ticket;

use App\Actions\LayananPelanggan\DaftarkanLayananAction;
use App\Actions\LayananPelanggan\UbahPaketLayananAction;
use App\Actions\Ticket\AssignPicAction;
use App\Actions\Ticket\UbahStatusDivisiTicketAction;
use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\JenisKoneksi;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\StatusRouter;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Exceptions\DuplikatLayananAktifException;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\PaketLayanan;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\TicketPemasangan;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Whatsapp\WhatsappService;
use Exception;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Detail Tiket')]
class Show extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // State Modal Ubah Status
    public bool $showUbahStatusModal = false;

    public string $statusBaru = '';

    public string $catatanStatus = '';

    // State Modal Assign PIC
    public bool $showAssignPicModal = false;

    public ?int $selectedPicId = null;

    public string $catatanAssign = '';

    // State Modal Tambah Catatan
    public bool $showCatatanModal = false;

    public string $catatanProses = '';

    public bool $catatanIsInternal = false;

    /** @var mixed */
    public $fotoPengerjaan = null;

    // State Pemasangan: progress lapangan Teknisi (tahap 1)
    public ?int $odp_id = null;

    public ?int $odp_port_id = null;

    /** @var array<int, mixed> */
    public $fotoPemasangan = [];

    // State Pemasangan: bukti tahap 2 Teknisi
    /** @var array<int, mixed> */
    public $fotoSpeedtest = [];

    /** @var mixed */
    public $fotoMou = null;

    /** @var array<int, mixed> */
    public $fotoBersama = [];

    // State Modal Aktivasi Pemasangan (NOC)
    public bool $showAktivasiModal = false;

    public ?int $aktivasiRouterId = null;

    public ?int $aktivasiIpPoolId = null;

    // State Modal Proses Divisi (NOC / Admin / Customer Service) -- lihat CONTEXT.md
    // "Proses Divisi (NOC/Admin/Customer Service)".
    public bool $showProsesModal = false;

    public string $prosesDivisi = '';

    public string $prosesStatusDivisi = 'progress';

    public string $prosesCatatan = '';

    // Proses NOC saja
    public string $prosesModeMikrotik = 'proses';

    public string $prosesPilihanPaket = 'bawaan';

    public ?int $prosesRouterId = null;

    public ?int $prosesIpPoolId = null;

    public ?int $prosesPaketLayananId = null;

    public string $prosesPppMode = 'auto';

    public string $prosesPppUsername = '';

    public string $prosesPppPassword = '';

    // Proses Admin saja
    public bool $prosesUbahPaket = false;

    // Reveal PPP Password di panel Informasi Layanan -- lihat ADR-0055.
    public string $revealedPppPassword = '';

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
        $this->loadTicket();

        $this->odp_id = $this->ticket->pemasangan?->odpPort?->odp_id;
        $this->odp_port_id = $this->ticket->pemasangan?->odp_port_id;
    }

    protected function loadTicket(): void
    {
        $this->revealedPppPassword = '';

        $this->ticket->load([
            'pelanggan.perumahan.kelurahan.kecamatan.kota',
            'pelanggan.dibuatOleh',
            'layananPelanggan.paketLayanan.profilBandwidth',
            'layananPelanggan.router',
            'layananPelanggan.ipPubliks',
            'layananPelanggan.odpPort.odp',
            'pemasangan.odpPort.odp',
            'pic',
            'dibuatOleh',
            'divisis',
            'histori.olehPengguna',
            'media',
        ]);
    }

    public function openUbahStatusModal(): void
    {
        $transisiValid = $this->ticket->status->transisiValid();
        $this->statusBaru = ! empty($transisiValid) ? $transisiValid[0]->value : '';
        $this->catatanStatus = '';
        $this->showUbahStatusModal = true;
    }

    public function prosesUbahStatus(UbahStatusTicketAction $action): void
    {
        $this->validate([
            'statusBaru' => ['required', 'string'],
            'catatanStatus' => ['nullable', 'string', 'max:1000'],
        ]);

        $statusBaruEnum = StatusTicket::tryFrom($this->statusBaru);
        if (! $statusBaruEnum) {
            Flux::toast(variant: 'danger', text: 'Status tujuan tidak valid.');

            return;
        }

        try {
            /** @var User $actor */
            $actor = Auth::guard('web')->user();
            $action->execute(
                ticket: $this->ticket,
                statusBaru: $statusBaruEnum,
                actor: $actor,
                catatan: $this->catatanStatus ?: null,
            );

            Flux::toast(variant: 'success', text: "Status tiket {$this->ticket->nomor_ticket} berhasil diubah ke {$statusBaruEnum->label()}.");
            $this->showUbahStatusModal = false;
            $this->loadTicket();
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openAssignPicModal(): void
    {
        $this->selectedPicId = $this->ticket->pic_id;
        $this->catatanAssign = '';
        $this->showAssignPicModal = true;
    }

    public function prosesAssignPic(AssignPicAction $action): void
    {
        $this->validate([
            'selectedPicId' => ['nullable', 'integer', 'exists:users,id'],
            'catatanAssign' => ['nullable', 'string', 'max:500'],
        ]);

        $newPic = $this->selectedPicId ? User::find($this->selectedPicId) : null;

        try {
            /** @var User $actor */
            $actor = Auth::guard('web')->user();
            $action->execute(
                ticket: $this->ticket,
                pic: $newPic,
                actor: $actor,
                catatan: $this->catatanAssign ?: null,
            );

            $picName = $newPic ? $newPic->name : 'Belum Ditugaskan';
            Flux::toast(variant: 'success', text: "PIC tiket {$this->ticket->nomor_ticket} berhasil diperbarui: {$picName}.");
            $this->showAssignPicModal = false;
            $this->loadTicket();
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openCatatanModal(): void
    {
        $this->catatanProses = '';
        $this->catatanIsInternal = false;
        $this->fotoPengerjaan = null;
        $this->showCatatanModal = true;
    }

    public function simpanCatatan(): void
    {
        $this->validate([
            'catatanProses' => ['required', 'string', 'min:3', 'max:1000'],
            'fotoPengerjaan' => ['nullable', 'image', 'max:5120'],
        ], [
            'catatanProses.required' => 'Catatan proses penanganan wajib diisi.',
            'fotoPengerjaan.image' => 'Bukti pengerjaan harus berupa berkas gambar (jpg, png, webp).',
            'fotoPengerjaan.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $histori = TicketHistori::create([
            'ticket_id' => $this->ticket->id,
            'status_lama' => $this->ticket->status,
            'status_baru' => $this->ticket->status,
            'catatan' => trim($this->catatanProses),
            'is_internal' => $this->catatanIsInternal,
            'oleh_pengguna_id' => Auth::id(),
        ]);

        if ($this->fotoPengerjaan) {
            try {
                $histori->addMediaFromDisk(
                    FileUploadConfiguration::path($this->fotoPengerjaan->getFilename(), false),
                    FileUploadConfiguration::disk()
                )
                    ->usingFileName($this->fotoPengerjaan->getClientOriginalName())
                    ->toMediaCollection('foto_pengerjaan');
            } catch (\Throwable $e) {
                Log::error('Gagal menyimpan foto pengerjaan: '.$e->getMessage());
            }
        }

        // Jika catatan publik, kirim notifikasi WhatsApp ke Pelanggan
        if (! $this->catatanIsInternal && $this->ticket->pelanggan && ! empty($this->ticket->pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildTicketParams($this->ticket, trim($this->catatanProses));
                $whatsappService->antrikanPesan(
                    noHp: $this->ticket->pelanggan->no_hp,
                    kodeTemplate: 'tiket_status_update',
                    params: $params,
                    referensi: $this->ticket,
                    jenis: "tiket_catatan_{$histori->id}"
                );
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA catatan baru: '.$e->getMessage());
            }
        }

        Flux::toast(variant: 'success', text: 'Catatan proses penanganan berhasil ditambahkan.');
        $this->showCatatanModal = false;
        $this->loadTicket();
    }

    /**
     * Reset pilihan port saat ODP berubah (port milik ODP lama tidak relevan lagi).
     *
     * Dipanggil otomatis oleh Livewire saat properti odp_id berubah.
     */
    public function updatedOdpId(): void
    {
        $this->odp_port_id = null;
    }

    /**
     * Progress lapangan tahap 1 Teknisi: pilih ODP+Port dan unggah foto bukti pemasangan.
     * Boleh dipanggil berkali-kali (unggah foto tambahan) tanpa meregresi status divisi.
     */
    public function simpanProgressLapangan(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        $this->validate([
            'odp_id' => ['required', 'integer', 'exists:odp,id'],
            'odp_port_id' => ['required', 'integer', Rule::exists('odp_port', 'id')->where('odp_id', $this->odp_id)],
            'fotoPemasangan.*' => ['image', 'max:5120'],
        ], [
            'odp_id.required' => 'ODP wajib dipilih.',
            'odp_port_id.required' => 'Port ODP wajib dipilih.',
            'odp_port_id.exists' => 'Port yang dipilih tidak valid atau bukan milik ODP terpilih.',
            'fotoPemasangan.*.image' => 'Setiap foto harus berupa berkas gambar (jpg, png, webp).',
            'fotoPemasangan.*.max' => 'Ukuran tiap foto maksimal 5 MB.',
        ]);

        TicketPemasangan::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            ['odp_port_id' => $this->odp_port_id],
        );

        foreach ($this->fotoPemasangan as $foto) {
            $this->ticket->addMediaFromDisk(
                FileUploadConfiguration::path($foto->getFilename(), false),
                FileUploadConfiguration::disk()
            )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_pemasangan');
        }
        $this->fotoPemasangan = [];

        if ($this->ticket->statusDivisi(DivisiTicket::Teknisi) === StatusDivisiTicket::Belum) {
            app(UbahStatusDivisiTicketAction::class)->execute(
                ticket: $this->ticket,
                divisi: DivisiTicket::Teknisi,
                statusBaru: StatusDivisiTicket::Progress,
                actor: Auth::guard('web')->user(),
            );
        }

        Flux::toast(variant: 'success', text: 'Progress lapangan berhasil disimpan.');
        $this->loadTicket();
    }

    public function openAktivasiModal(): void
    {
        $this->authorize('aktivasiPemasangan', $this->ticket);

        if (! $this->ticket->siapDiaktivasi()) {
            Flux::toast(variant: 'danger', text: 'Belum bisa diaktivasi: pastikan Teknisi sudah memilih ODP+Port dan mengunggah minimal 1 foto pemasangan.');

            return;
        }

        $this->aktivasiRouterId = null;
        $this->aktivasiIpPoolId = null;

        $onlineRouters = Router::where('status_koneksi', StatusRouter::Online)->get(['id']);
        if ($onlineRouters->count() === 1) {
            $this->aktivasiRouterId = $onlineRouters->first()->id;
            $this->updatedAktivasiRouterId();
        }

        $this->showAktivasiModal = true;
    }

    /**
     * Auto-select IP Pool jika router terpilih cuma punya 1 pool.
     *
     * Dipanggil otomatis oleh Livewire saat properti aktivasiRouterId berubah.
     */
    public function updatedAktivasiRouterId(): void
    {
        if ($this->aktivasiRouterId) {
            $pools = IpPool::where('router_id', $this->aktivasiRouterId)->get(['id']);
            $this->aktivasiIpPoolId = $pools->count() === 1 ? $pools->first()->id : null;
        } else {
            $this->aktivasiIpPoolId = null;
        }
    }

    /**
     * Aktivasi Pemasangan: isi router/IP Pool/PPP Username (satu-satunya pilihan manual asli
     * ada di router -- lihat CONTEXT.md "Aktivasi Pemasangan"), lalu provisi ke MikroTik.
     */
    public function prosesAktivasi(): void
    {
        $this->authorize('aktivasiPemasangan', $this->ticket);

        $this->validate([
            'aktivasiRouterId' => ['required', 'integer', 'exists:router,id'],
            'aktivasiIpPoolId' => ['required', 'integer', Rule::exists('ip_pool', 'id')->where('router_id', $this->aktivasiRouterId)],
        ], [
            'aktivasiRouterId.required' => 'Router wajib dipilih.',
            'aktivasiIpPoolId.required' => 'IP Pool wajib dipilih.',
            'aktivasiIpPoolId.exists' => 'IP Pool tidak valid atau bukan milik router terpilih.',
        ]);

        $layanan = $this->ticket->layananPelanggan;
        if (! $layanan) {
            Flux::toast(variant: 'danger', text: 'Tiket ini belum terhubung ke Data Registrasi Billing.');

            return;
        }

        try {
            app(DaftarkanLayananAction::class)->assertBelumAdaDuplikat(
                $layanan->pelanggan_id,
                $this->aktivasiRouterId,
                $layanan->paket_layanan_id,
            );
        } catch (DuplikatLayananAktifException $e) {
            $this->addError('aktivasiRouterId', $e->getMessage());

            return;
        }

        $pppUsername = LayananPelanggan::generatePppUsername($layanan->pelanggan);
        $odpPortId = $this->ticket->pemasangan?->odp_port_id;

        DB::transaction(function () use ($layanan, $pppUsername, $odpPortId) {
            $layanan->update([
                'router_id' => $this->aktivasiRouterId,
                'ip_pool_id' => $this->aktivasiIpPoolId,
                'ppp_username' => $pppUsername,
                'odp_port_id' => $odpPortId,
            ]);
            $layanan->isiPppPasswordJikaKosong();

            if ($odpPortId) {
                OdpPort::whereKey($odpPortId)->update([
                    'status' => StatusOdpPort::Terpakai->value,
                    'layanan_pelanggan_id' => $layanan->id,
                ]);
            }

            $this->ticket->pemasangan()->update([
                'diaktivasi_pada' => now(),
                'diaktivasi_oleh' => Auth::id(),
            ]);
        });

        $router = Router::findOrFail($this->aktivasiRouterId);

        try {
            app(MikrotikService::class)->createOrUpdatePppoeSecret($router, $layanan->fresh());

            $layanan->update(['status' => StatusLayanan::Aktif]);

            MikrotikJobLog::create([
                'router_id' => $this->aktivasiRouterId,
                'layanan_pelanggan_id' => $layanan->id,
                'job_type' => MikrotikJobType::ProvisionPppoe,
                'status' => MikrotikJobStatus::Success,
                'attempt_count' => 1,
                'finished_at' => now(),
            ]);

            Flux::toast(
                variant: 'success',
                heading: 'Aktivasi Pemasangan Berhasil',
                text: "{$pppUsername} aktif di {$router->nama_router}.",
                duration: 15000,
            );
        } catch (\Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $this->aktivasiRouterId,
                'layanan_pelanggan_id' => $layanan->id,
                'job_type' => MikrotikJobType::ProvisionPppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Flux::toast(
                variant: 'warning',
                heading: 'Router/IP Pool Tersimpan, Provisi Gagal',
                text: "Provisi ke router gagal: {$e->getMessage()} Gunakan tombol Provisi di daftar layanan untuk mencoba lagi.",
                duration: 20000,
            );
        }

        $this->showAktivasiModal = false;
        $this->loadTicket();
    }

    /**
     * Unggah bukti tahap akhir Teknisi: speedtest, tanda tangan MOU, foto bersama.
     * Boleh dipanggil berkali-kali sebelum menandai Teknisi selesai.
     */
    public function simpanFotoTahapDua(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        $this->validate([
            'fotoSpeedtest.*' => ['image', 'max:5120'],
            'fotoMou' => ['nullable', 'image', 'max:5120'],
            'fotoBersama.*' => ['image', 'max:5120'],
        ]);

        foreach ($this->fotoSpeedtest as $foto) {
            $this->ticket->addMediaFromDisk(
                FileUploadConfiguration::path($foto->getFilename(), false),
                FileUploadConfiguration::disk()
            )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_speedtest');
        }

        if ($this->fotoMou) {
            $this->ticket->addMediaFromDisk(
                FileUploadConfiguration::path($this->fotoMou->getFilename(), false),
                FileUploadConfiguration::disk()
            )->usingFileName($this->fotoMou->getClientOriginalName())->toMediaCollection('foto_tanda_tangan_mou');
        }

        foreach ($this->fotoBersama as $foto) {
            $this->ticket->addMediaFromDisk(
                FileUploadConfiguration::path($foto->getFilename(), false),
                FileUploadConfiguration::disk()
            )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_bersama_pelanggan_teknisi');
        }

        $this->fotoSpeedtest = [];
        $this->fotoMou = null;
        $this->fotoBersama = [];

        Flux::toast(variant: 'success', text: 'Foto bukti tahap akhir berhasil diunggah.');
        $this->loadTicket();
    }

    /**
     * Tandai satu divisi (Teknisi/NOC/Customer Service/Admin) selesai. Begitu keempat divisi
     * wajib selesai, status tiket keseluruhan otomatis berpindah ke Selesai -- lihat
     * UbahStatusDivisiTicketAction dan CONTEXT.md "Status Per-Divisi Tiket".
     *
     * Dipakai HANYA oleh divisi Teknisi (tombol "Tandai Selesai" tetap ada di sana karena
     * digate oleh siapTeknisiSelesai(), bukti foto tahap akhir). NOC/Admin/Customer Service
     * memakai modal "Proses {Divisi}" (lihat openProsesModal()/prosesDivisiSubmit()) yang
     * juga mewajibkan Catatan Proses -- lihat CONTEXT.md "Proses Divisi (NOC/Admin/Customer
     * Service)".
     */
    public function tandaiDivisiSelesai(string $divisiValue): void
    {
        $divisi = DivisiTicket::from($divisiValue);
        $this->authorize('ubahStatusDivisi', [$this->ticket, $divisi]);

        if ($divisi === DivisiTicket::Teknisi && ! $this->ticket->siapTeknisiSelesai()) {
            Flux::toast(variant: 'danger', text: 'Lengkapi foto speedtest, tanda tangan MOU, dan foto bersama sebelum menandai Teknisi selesai.');

            return;
        }

        try {
            app(UbahStatusDivisiTicketAction::class)->execute(
                ticket: $this->ticket,
                divisi: $divisi,
                statusBaru: StatusDivisiTicket::Selesai,
                actor: Auth::guard('web')->user(),
            );

            Flux::toast(variant: 'success', text: "{$divisi->label()} berhasil ditandai selesai.");
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->loadTicket();
    }

    /**
     * Buka modal "Proses {Divisi}" untuk NOC/Admin/Customer Service -- lihat CONTEXT.md
     * "Proses Divisi (NOC/Admin/Customer Service)". Teknisi tetap memakai tandaiDivisiSelesai()
     * + form Progress Lapangan/Foto Tahap 2 karena digate bukti foto, bukan catatan bebas.
     */
    public function openProsesModal(string $divisiValue): void
    {
        $divisi = DivisiTicket::from($divisiValue);
        $this->authorize('ubahStatusDivisi', [$this->ticket, $divisi]);

        $layanan = $this->ticket->layananPelanggan;

        $this->prosesDivisi = $divisi->value;
        $current = $this->ticket->statusDivisi($divisi);
        $this->prosesStatusDivisi = $current === StatusDivisiTicket::Selesai ? 'selesai' : 'progress';
        $this->prosesCatatan = '';
        $this->prosesModeMikrotik = 'proses';
        $this->prosesPilihanPaket = 'bawaan';
        $this->prosesRouterId = $layanan?->router_id;
        $this->prosesIpPoolId = $layanan?->ip_pool_id;
        $this->prosesPaketLayananId = $layanan?->paket_layanan_id;
        $this->prosesPppMode = 'auto';
        $this->prosesPppUsername = $layanan->ppp_username ?? '';
        $this->prosesPppPassword = '';
        $this->prosesUbahPaket = false;
        $this->showProsesModal = true;
    }

    /**
     * IP Pool Proses NOC mengikuti router terpilih: pertahankan pool bila masih milik router itu,
     * selain itu auto-select kalau router cuma punya 1 pool.
     *
     * Dipanggil otomatis oleh Livewire saat properti prosesRouterId berubah.
     */
    public function updatedProsesRouterId(): void
    {
        if (! $this->prosesRouterId) {
            $this->prosesIpPoolId = null;

            return;
        }

        $pools = IpPool::where('router_id', $this->prosesRouterId)->pluck('id');

        if (! $pools->contains($this->prosesIpPoolId)) {
            $this->prosesIpPoolId = $pools->count() === 1 ? $pools->first() : null;
        }
    }

    /**
     * Simpan hasil modal "Proses {Divisi}": aksi teknis khusus per divisi (NOC: router/paket/
     * Mikrotik/PPP; Admin: opsional ubah paket), lalu satu baris TicketHistori berisi Catatan
     * Proses wajib -- lihat UbahStatusDivisiTicketAction::execute(). Opsi status "Cancel"
     * membatalkan tiket keseluruhan lewat UbahStatusTicketAction, bukan status per-divisi.
     */
    public function prosesDivisiSubmit(): void
    {
        $divisi = DivisiTicket::from($this->prosesDivisi);
        $this->authorize('ubahStatusDivisi', [$this->ticket, $divisi]);

        $this->validate([
            'prosesStatusDivisi' => ['required', 'in:progress,selesai,cancel'],
            'prosesCatatan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'prosesCatatan.required' => 'Catatan Proses wajib diisi.',
            'prosesCatatan.min' => 'Catatan Proses minimal 5 karakter.',
        ]);

        if ($this->prosesStatusDivisi === 'cancel') {
            try {
                app(UbahStatusTicketAction::class)->execute(
                    ticket: $this->ticket,
                    statusBaru: StatusTicket::Batal,
                    actor: Auth::guard('web')->user(),
                    catatan: $this->prosesCatatan,
                );

                Flux::toast(variant: 'success', text: "Tiket {$this->ticket->nomor_ticket} berhasil dibatalkan.");
                $this->showProsesModal = false;
                $this->loadTicket();
            } catch (\Throwable $e) {
                Flux::toast(variant: 'danger', text: $e->getMessage());
            }

            return;
        }

        $layanan = $this->ticket->layananPelanggan;

        if ($divisi === DivisiTicket::Noc) {
            if (! $layanan) {
                Flux::toast(variant: 'danger', text: 'Tiket ini belum terhubung ke Data Registrasi Billing.');

                return;
            }

            $perluPasswordManual = $layanan->perluPasswordManual($this->prosesModeMikrotik === 'sudah');

            $this->validate([
                'prosesModeMikrotik' => ['required', 'in:proses,sudah'],
                'prosesPilihanPaket' => ['required', 'in:bawaan,berbeda'],
                'prosesRouterId' => ['required', 'integer', 'exists:router,id'],
                'prosesIpPoolId' => [
                    Rule::requiredIf($layanan->jenis_koneksi === JenisKoneksi::Pppoe),
                    'nullable', 'integer',
                    Rule::exists('ip_pool', 'id')->where('router_id', $this->prosesRouterId),
                ],
                'prosesPaketLayananId' => ['required', 'integer', 'exists:paket_layanan,id'],
                'prosesPppMode' => ['required', 'in:auto,manual'],
                'prosesPppUsername' => [
                    Rule::requiredIf($this->prosesPppMode === 'manual'),
                    'nullable', 'string', 'max:64',
                    Rule::unique('layanan_pelanggan', 'ppp_username')->ignore($layanan->id),
                ],
                'prosesPppPassword' => [Rule::requiredIf($perluPasswordManual), 'nullable', 'string', 'max:64'],
            ], [
                'prosesIpPoolId.required' => 'IP Pool wajib dipilih untuk koneksi PPPoE.',
                'prosesIpPoolId.exists' => 'IP Pool tidak valid atau bukan milik router terpilih.',
                'prosesPppUsername.required' => 'PPP Username manual wajib diisi.',
                'prosesPppUsername.unique' => 'PPP Username sudah dipakai layanan lain.',
                'prosesPppPassword.required' => 'Password PPP asli di router wajib diisi.',
            ]);

            $paketLayananId = $this->prosesPilihanPaket === 'bawaan' ? $layanan->paket_layanan_id : $this->prosesPaketLayananId;
            app(UbahPaketLayananAction::class)->execute($layanan, $paketLayananId, $this->prosesRouterId, $this->prosesIpPoolId);

            if ($this->prosesPppMode === 'manual') {
                $layanan->update(['ppp_username' => $this->prosesPppUsername]);
            } elseif (empty($layanan->ppp_username)) {
                $layanan->update(['ppp_username' => LayananPelanggan::generatePppUsername($layanan->pelanggan)]);
            }

            if ($perluPasswordManual) {
                $layanan->update(['ppp_password_terenkripsi' => $this->prosesPppPassword]);
            } elseif ($layanan->status === StatusLayanan::Proses) {
                $layanan->isiPppPasswordJikaKosong();
            }

            if ($this->prosesModeMikrotik === 'proses') {
                $router = Router::findOrFail($this->prosesRouterId);

                try {
                    app(MikrotikService::class)->createOrUpdatePppoeSecret($router, $layanan->fresh());

                    MikrotikJobLog::create([
                        'router_id' => $this->prosesRouterId,
                        'layanan_pelanggan_id' => $layanan->id,
                        'job_type' => MikrotikJobType::ProvisionPppoe,
                        'status' => MikrotikJobStatus::Success,
                        'attempt_count' => 1,
                        'finished_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    MikrotikJobLog::create([
                        'router_id' => $this->prosesRouterId,
                        'layanan_pelanggan_id' => $layanan->id,
                        'job_type' => MikrotikJobType::ProvisionPppoe,
                        'status' => MikrotikJobStatus::Failed,
                        'attempt_count' => 1,
                        'error_message' => $e->getMessage(),
                        'finished_at' => now(),
                    ]);

                    Flux::toast(variant: 'warning', heading: 'Registrasi Mikrotik Gagal', text: $e->getMessage(), duration: 20000);
                }
            }
        }

        if ($divisi === DivisiTicket::Admin && $this->prosesUbahPaket) {
            if (! $layanan) {
                Flux::toast(variant: 'danger', text: 'Tiket ini belum terhubung ke Data Registrasi Billing.');

                return;
            }

            $this->validate(['prosesPaketLayananId' => ['required', 'integer', 'exists:paket_layanan,id']]);
            app(UbahPaketLayananAction::class)->execute($layanan, $this->prosesPaketLayananId);
        }

        $statusBaruEnum = $this->prosesStatusDivisi === 'selesai' ? StatusDivisiTicket::Selesai : StatusDivisiTicket::Progress;

        try {
            app(UbahStatusDivisiTicketAction::class)->execute(
                ticket: $this->ticket,
                divisi: $divisi,
                statusBaru: $statusBaruEnum,
                actor: Auth::guard('web')->user(),
                catatan: $this->prosesCatatan,
            );

            Flux::toast(variant: 'success', text: "Proses {$divisi->label()} berhasil disimpan.");
            $this->showProsesModal = false;
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->loadTicket();
    }

    /**
     * Ungkap PPP Password layanan tiket ini -- dibatasi TicketPolicy::lihatKredensialPpp dan
     * beraudit trail. Lihat ADR-0055.
     */
    public function revealPppPassword(): void
    {
        $this->authorize('lihatKredensialPpp', $this->ticket);

        $layanan = $this->ticket->layananPelanggan;
        abort_unless($layanan && $layanan->ppp_password_terenkripsi, 404);

        activity('layanan_pelanggan')
            ->performedOn($layanan)
            ->causedBy(Auth::guard('web')->user())
            ->withProperties([
                'action' => 'reveal_ppp_password',
                'ticket' => $this->ticket->nomor_ticket,
                'ip' => request()->ip(),
            ])
            ->log("Mengungkap PPP Password layanan {$layanan->ppp_username} lewat tiket {$this->ticket->nomor_ticket}");

        $this->revealedPppPassword = $layanan->ppp_password_terenkripsi;
    }

    public function sembunyikanPppPassword(): void
    {
        $this->revealedPppPassword = '';
    }

    public function render(): View
    {
        /** @var Collection<int, User> $staffList */
        $staffList = User::query()->active()->role('teknisi')->orderBy('name')->get();

        $transisiValid = $this->ticket->status->transisiValid();

        $odps = Odp::orderBy('nama_odp')->get(['id', 'nama_odp']);
        $odpPorts = $this->odp_id
            ? OdpPort::where('odp_id', $this->odp_id)
                ->where(function ($q) {
                    $q->where('status', StatusOdpPort::Kosong)->orWhere('id', $this->odp_port_id);
                })
                ->orderBy('nomor_port')
                ->get()
            : collect();

        $onlineRouters = Router::where('status_koneksi', StatusRouter::Online)->orderBy('nama_router')->get();
        $aktivasiIpPools = $this->aktivasiRouterId
            ? IpPool::where('router_id', $this->aktivasiRouterId)->orderBy('nama_pool')->get()
            : collect();
        $prosesIpPools = $this->prosesRouterId
            ? IpPool::where('router_id', $this->prosesRouterId)->orderBy('nama_pool')->get()
            : collect();

        // Paket Layanan aktif untuk dropdown "Paket Berbeda" (Proses NOC) / "Ubah Paket Layanan"
        // (Proses Admin) -- tidak difilter per-router, lihat CONTEXT.md "Proses Divisi".
        $paketLayananList = PaketLayanan::aktif()->orderBy('nama_paket')->get();

        return view('livewire.ticket.show', [
            'staffList' => $staffList,
            'transisiValid' => $transisiValid,
            'odps' => $odps,
            'odpPorts' => $odpPorts,
            'onlineRouters' => $onlineRouters,
            'aktivasiIpPools' => $aktivasiIpPools,
            'prosesIpPools' => $prosesIpPools,
            'paketLayananList' => $paketLayananList,
        ]);
    }
}
