<?php

namespace App\Livewire\Ticket;

use App\Actions\LayananPelanggan\DaftarkanLayananAction;
use App\Actions\LayananPelanggan\UbahPaketLayananAction;
use App\Actions\Ticket\AssignPicAction;
use App\Actions\Ticket\HapusSecretPencabutanAction;
use App\Actions\Ticket\LepasPortOdpPencabutanAction;
use App\Actions\Ticket\UbahStatusDivisiTicketAction;
use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\StatusRouter;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\StatusUsulanOdp;
use App\Exceptions\DuplikatLayananAktifException;
use App\Exceptions\MikrotikException;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\PaketLayanan;
use App\Models\Router;
use App\Models\RouterPaket;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\TicketPemasangan;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\NotifikasiNoc;
use App\Support\PetaPortOdp;
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

    /** Alasan Teknisi menolak Usulan ODP -- lihat CONTEXT.md "Usulan ODP". */
    public string $alasanGantiOdp = '';

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

        $pemasangan = $this->ticket->pemasangan;
        $this->odp_port_id = $pemasangan?->odp_port_id;
        $this->odp_id = $pemasangan?->odpPort?->odp_id
            ?? ($pemasangan?->status_usulan_odp === StatusUsulanOdp::Disetujui ? $pemasangan->odp_usulan_id : null);
    }

    /**
     * Teknisi menyetujui Usulan ODP: port hanya boleh dipilih dari ODP itu.
     */
    public function setujuiUsulanOdp(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        $pemasangan = $this->ticket->pemasangan;
        if ($pemasangan?->status_usulan_odp !== StatusUsulanOdp::Menunggu) {
            return;
        }

        $pemasangan->update(['status_usulan_odp' => StatusUsulanOdp::Disetujui]);
        $this->catatHistoriUsulanOdp("Usulan ODP {$pemasangan->odpUsulan?->nama_odp} disetujui Teknisi.");

        $this->odp_id = $pemasangan->odp_usulan_id;
        $this->odp_port_id = null;

        Flux::toast(variant: 'success', text: 'Usulan ODP disetujui. Silakan pilih port.');
        $this->loadTicket();
    }

    /**
     * Teknisi menolak Usulan ODP dengan alasan, lalu memilih ODP lain sendiri (tanpa kembali ke pembuat tiket).
     */
    public function gantiUsulanOdp(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        $pemasangan = $this->ticket->pemasangan;
        if ($pemasangan?->status_usulan_odp !== StatusUsulanOdp::Menunggu) {
            return;
        }

        $this->validate(
            ['alasanGantiOdp' => ['required', 'string', 'min:5', 'max:500']],
            ['alasanGantiOdp.required' => 'Alasan mengganti Usulan ODP wajib diisi.', 'alasanGantiOdp.min' => 'Alasan minimal 5 karakter.'],
        );

        $pemasangan->update(['status_usulan_odp' => StatusUsulanOdp::Diganti]);
        $this->catatHistoriUsulanOdp("Usulan ODP {$pemasangan->odpUsulan?->nama_odp} diganti Teknisi. Alasan: ".trim($this->alasanGantiOdp));

        $this->alasanGantiOdp = '';
        $this->odp_id = null;
        $this->odp_port_id = null;

        Flux::toast(variant: 'success', text: 'Usulan ODP diganti. Silakan pilih ODP dan port.');
        $this->loadTicket();
    }

    private function catatHistoriUsulanOdp(string $catatan): void
    {
        TicketHistori::create([
            'ticket_id' => $this->ticket->id,
            'status_lama' => $this->ticket->status,
            'status_baru' => $this->ticket->status,
            'catatan' => $catatan,
            'is_internal' => true,
            'oleh_pengguna_id' => Auth::id(),
        ]);
    }

    protected function loadTicket(): void
    {
        $this->revealedPppPassword = '';

        $this->ticket->load([
            'pelanggan.perumahan.kelurahan.kecamatan.kota',
            'pelanggan.dibuatOleh.roles',
            'pelanggan.dibuatOleh.media',
            'layananPelanggan.paketLayanan.profilBandwidth',
            'layananPelanggan.router',
            'layananPelanggan.ipPubliks',
            'layananPelanggan.odpPort.odp',
            'pemasangan.odpPort.odp',
            'pemasangan.odpUsulan',
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

    /**
     * Proses NOC pada tiket Pencabutan: hapus PPP Secret di router -- lihat CONTEXT.md "Pencabutan".
     */
    public function hapusSecretPencabutan(HapusSecretPencabutanAction $action): void
    {
        $this->eksekusiAksiPencabutan(
            abilitas: 'hapusSecretPencabutan',
            aksi: fn (User $actor) => $action->execute($this->ticket, $actor),
            pesanSukses: 'PPP Secret berhasil dihapus dari router. Tiket sudah bisa ditandai Selesai.',
        );
    }

    /**
     * Teknisi mencabut perangkat dan melepas port ODP pada tiket Pencabutan.
     */
    public function lepasPortOdpPencabutan(LepasPortOdpPencabutanAction $action): void
    {
        $this->eksekusiAksiPencabutan(
            abilitas: 'lepasPortOdpPencabutan',
            aksi: fn (User $actor) => $action->execute($this->ticket, $actor),
            pesanSukses: 'Port ODP berhasil dilepas.',
        );
    }

    /**
     * Jalankan satu aksi divisi tiket Pencabutan yang digerbang Policy: cek otorisasi, eksekusi,
     * lalu tampilkan toast hasil dan muat ulang tiket.
     */
    private function eksekusiAksiPencabutan(string $abilitas, \Closure $aksi, string $pesanSukses): void
    {
        $this->authorize($abilitas, $this->ticket);

        try {
            /** @var User $actor */
            $actor = Auth::guard('web')->user();
            $aksi($actor);

            Flux::toast(variant: 'success', text: $pesanSukses);
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
     * Teknisi mengetuk satu kotak di Peta Port ODP. Hanya port Kosong yang tidak Dipesan tiket
     * lain, atau port milik tiket ini, yang diterima -- lihat CONTEXT.md "Peta Port ODP".
     * Pilihan baru tersimpan lewat simpanProgressLapangan()/tandaiDivisiSelesai().
     */
    public function pilihPort(int $portId): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);
        $this->resetErrorBag('odp_port_id');

        if (! $this->formPortTeknisiTersedia()) {
            $this->addError('odp_port_id', 'Port hanya bisa dipilih Teknisi pada Ticket Pemasangan.');

            return;
        }

        $peta = $this->petaPort();
        $alasan = $peta ? $peta->alasanTidakDapatDipilih($portId) : 'Pilih ODP terlebih dahulu.';
        if ($alasan !== null) {
            $this->addError('odp_port_id', $alasan);

            return;
        }

        $this->odp_port_id = $portId;
    }

    /**
     * Form ODP+Port Teknisi hanya ada di Ticket Pemasangan yang mengacu ke layanan, setelah
     * Usulan ODP divalidasi -- lihat CONTEXT.md "Usulan ODP".
     */
    public function formPortTeknisiTersedia(): bool
    {
        return $this->ticket->jenis === JenisTicket::Pemasangan
            && $this->ticket->layanan_pelanggan_id !== null
            && $this->ticket->pemasangan?->status_usulan_odp !== StatusUsulanOdp::Menunggu;
    }

    /**
     * Peta Port ODP baca-saja untuk port layanan tiket, kecuali saat Teknisi sedang memakai
     * peta interaktif di form ODP+Port (agar peta tidak tampil dua kali).
     */
    protected function petaPortLayanan(): ?PetaPortOdp
    {
        $port = $this->ticket->layananPelanggan?->odpPort;
        $formInteraktifTampil = $this->formPortTeknisiTersedia()
            && Auth::user()?->can('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        return $port && ! $formInteraktifTampil ? PetaPortOdp::untuk($port->odp, portTerpilihId: $port->id) : null;
    }

    protected function petaPort(): ?PetaPortOdp
    {
        $odp = $this->odp_id ? Odp::find($this->odp_id) : null;

        return $odp ? PetaPortOdp::untuk($odp, $this->ticket->id, $this->ticket->pemasangan?->odp_port_id, $this->odp_port_id) : null;
    }

    /**
     * Simpan ODP dan Port ODP yang dipilih Teknisi.
     */
    protected function simpanOdpPortTeknisi(): bool
    {
        $this->validate([
            'odp_id' => ['required', 'integer', 'exists:odp,id'],
            'odp_port_id' => ['required', 'integer', Rule::exists('odp_port', 'id')->where('odp_id', $this->odp_id)],
        ], [
            'odp_id.required' => 'ODP wajib dipilih.',
            'odp_port_id.required' => 'Port ODP wajib dipilih.',
            'odp_port_id.exists' => 'Port yang dipilih tidak valid atau bukan milik ODP terpilih.',
        ]);

        $pemasangan = $this->ticket->pemasangan;
        if ($pemasangan?->status_usulan_odp === StatusUsulanOdp::Menunggu) {
            $this->addError('odp_id', 'Validasi Usulan ODP dulu (setujui atau ganti) sebelum memilih port.');

            return false;
        }
        if ($pemasangan?->status_usulan_odp === StatusUsulanOdp::Disetujui && $this->odp_id !== $pemasangan->odp_usulan_id) {
            $this->addError('odp_id', 'Usulan ODP sudah disetujui; port harus dari ODP tersebut.');

            return false;
        }
        $alasan = $this->petaPort()?->alasanTidakDapatDipilih($this->odp_port_id);
        if ($alasan !== null) {
            $this->addError('odp_port_id', $alasan);

            return false;
        }

        TicketPemasangan::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            ['odp_port_id' => $this->odp_port_id],
        );

        return true;
    }

    /**
     * Unggah foto-foto teknisi yang masih tertunda di Livewire temporary upload.
     */
    protected function uploadPendingFotoTeknisi(): void
    {
        $rules = [];
        if (! empty($this->fotoPemasangan)) {
            $rules['fotoPemasangan.*'] = ['image', 'max:5120'];
        }
        if (! empty($this->fotoSpeedtest)) {
            $rules['fotoSpeedtest.*'] = ['image', 'max:5120'];
        }
        if ($this->fotoMou) {
            $rules['fotoMou'] = ['image', 'max:5120'];
        }
        if (! empty($this->fotoBersama)) {
            $rules['fotoBersama.*'] = ['image', 'max:5120'];
        }

        if (! empty($rules)) {
            $this->validate($rules, [
                'fotoPemasangan.*.image' => 'Setiap foto pemasangan harus berupa berkas gambar (jpg, png, webp).',
                'fotoPemasangan.*.max' => 'Ukuran foto pemasangan maksimal 5 MB.',
                'fotoSpeedtest.*.image' => 'Foto speedtest harus berupa berkas gambar (jpg, png, webp).',
                'fotoSpeedtest.*.max' => 'Ukuran foto speedtest maksimal 5 MB.',
                'fotoMou.image' => 'Foto MOU harus berupa berkas gambar (jpg, png, webp).',
                'fotoMou.max' => 'Ukuran foto MOU maksimal 5 MB.',
                'fotoBersama.*.image' => 'Foto bersama harus berupa berkas gambar (jpg, png, webp).',
                'fotoBersama.*.max' => 'Ukuran foto bersama maksimal 5 MB.',
            ]);

            foreach ($this->fotoPemasangan as $foto) {
                $this->ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($foto->getFilename(), false),
                    FileUploadConfiguration::disk()
                )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_pemasangan');
            }
            $this->fotoPemasangan = [];

            foreach ($this->fotoSpeedtest as $foto) {
                $this->ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($foto->getFilename(), false),
                    FileUploadConfiguration::disk()
                )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_speedtest');
            }
            $this->fotoSpeedtest = [];

            if ($this->fotoMou) {
                $this->ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($this->fotoMou->getFilename(), false),
                    FileUploadConfiguration::disk()
                )->usingFileName($this->fotoMou->getClientOriginalName())->toMediaCollection('foto_tanda_tangan_mou');
                $this->fotoMou = null;
            }

            foreach ($this->fotoBersama as $foto) {
                $this->ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($foto->getFilename(), false),
                    FileUploadConfiguration::disk()
                )->usingFileName($foto->getClientOriginalName())->toMediaCollection('foto_bersama_pelanggan_teknisi');
            }
            $this->fotoBersama = [];
        }
    }

    /**
     * Progress lapangan Teknisi: simpan pilihan ODP+Port dan unggah berkas bukti foto.
     */
    public function simpanProgressLapangan(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        if (! $this->simpanOdpPortTeknisi()) {
            return;
        }

        $this->uploadPendingFotoTeknisi();

        if ($this->ticket->statusDivisi(DivisiTicket::Teknisi) === StatusDivisiTicket::Belum) {
            app(UbahStatusDivisiTicketAction::class)->execute(
                ticket: $this->ticket,
                divisi: DivisiTicket::Teknisi,
                statusBaru: StatusDivisiTicket::Progress,
                actor: Auth::guard('web')->user(),
            );
        }

        Flux::toast(variant: 'success', text: 'Progress pengerjaan lapangan berhasil disimpan.');
        $this->loadTicket();
    }

    public function openAktivasiModal(): void
    {
        $this->authorize('aktivasiPemasangan', $this->ticket);

        if (! $this->ticket->siapDiaktivasi()) {
            Flux::toast(variant: 'danger', text: 'Belum bisa diaktivasi: pastikan Teknisi sudah memilih ODP+Port dan mengunggah minimal 1 foto pemasangan.');

            return;
        }

        $routers = $this->routerOnlineUntukPaket($this->ticket->layananPelanggan?->paket_layanan_id);
        $this->aktivasiRouterId = $routers->count() === 1 ? $routers->first()->id : null;

        $this->showAktivasiModal = true;
    }

    /**
     * NOC hanya boleh memilih router yang sudah menjadi Router Paket untuk paket itu (ADR-0063).
     */
    private function validasiRouterPaket(?int $paketLayananId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($paketLayananId): void {
            if (! RouterPaket::terdaftar($value, $paketLayananId)) {
                $fail(RouterPaket::PESAN_BELUM_TERDAFTAR);
            }
        };
    }

    /**
     * @return Collection<int, Router>
     */
    private function routerOnlineUntukPaket(?int $paketLayananId): Collection
    {
        return Router::where('status_koneksi', StatusRouter::Online)
            ->whereHas('routerPakets', fn ($query) => $query->where('paket_layanan_id', $paketLayananId))
            ->orderBy('nama_router')
            ->get();
    }

    /**
     * Aktivasi Pemasangan: isi router/PPP Username (router satu-satunya pilihan manual; IP Pool
     * dari Rantai IP Pool Router -- lihat CONTEXT.md "Aktivasi Pemasangan"), lalu provisi ke MikroTik.
     */
    public function prosesAktivasi(): void
    {
        $this->authorize('aktivasiPemasangan', $this->ticket);

        $this->validate([
            'aktivasiRouterId' => ['required', 'integer', 'exists:router,id', $this->validasiRouterPaket($this->ticket->layananPelanggan?->paket_layanan_id)],
        ], [
            'aktivasiRouterId.required' => 'Router wajib dipilih.',
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
            $log = MikrotikJobLog::create([
                'router_id' => $this->aktivasiRouterId,
                'layanan_pelanggan_id' => $layanan->id,
                'job_type' => MikrotikJobType::ProvisionPppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'payload' => ['username' => $pppUsername],
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            app(NotifikasiNoc::class)->kirim($log, whatsapp: true);

            // Galat koneksi dicoba ulang otomatis di antrean; galat data/konfigurasi butuh perbaikan NOC dulu.
            $dicobaLagi = MikrotikException::bisaDicobaLagi($e);
            if ($dicobaLagi) {
                ProvisionPppoeAccountJob::dispatch($layanan->fresh());
            }

            Flux::toast(
                variant: 'warning',
                heading: 'Router/IP Pool Tersimpan, Provisi Gagal',
                text: "Provisi ke router gagal: {$e->getMessage()} ".($dicobaLagi ? 'Sistem mencoba ulang otomatis dan NOC diberi tahu.' : 'Perbaiki penyebabnya lalu klik Coba Provisi Lagi.'),
                duration: 20000,
            );
        }

        $this->showAktivasiModal = false;
        $this->loadTicket();
    }

    /**
     * Coba ulang provisi layanan tiket ini lewat antrean (callout "Provisi gagal").
     */
    public function provisiUlang(): void
    {
        $layanan = $this->ticket->layananPelanggan;
        abort_if($layanan === null || $layanan->router_id === null, 404);
        $this->authorize('aktivasiPemasangan', $this->ticket);

        ProvisionPppoeAccountJob::dispatch($layanan);
        Flux::toast(variant: 'info', text: "Provisi {$layanan->ppp_username} diantrekan. Hasilnya muncul di lonceng notifikasi.");
    }

    /**
     * Unggah bukti tahap akhir Teknisi: speedtest, tanda tangan MOU, foto bersama.
     * Boleh dipanggil berkali-kali sebelum menandai Teknisi selesai.
     */
    public function simpanFotoTahapDua(): void
    {
        $this->authorize('ubahStatusDivisi', [$this->ticket, DivisiTicket::Teknisi]);

        $this->uploadPendingFotoTeknisi();

        Flux::toast(variant: 'success', text: 'Foto bukti berhasil disimpan.');
        $this->loadTicket();
    }

    /**
     * Tandai satu divisi (Teknisi/NOC/Customer Service/Admin) selesai. Begitu keempat divisi
     * wajib selesai, status tiket keseluruhan otomatis berpindah ke Selesai -- lihat
     * UbahStatusDivisiTicketAction dan CONTEXT.md "Status Per-Divisi Tiket".
     *
     * Dipakai HANYA oleh divisi Teknisi (tombol "Tandai Selesai" tetap ada di sana karena
     * digate oleh siapTeknisiSelesai(), bukti foto kerja). NOC/Admin/Customer Service
     * memakai modal "Proses {Divisi}" (lihat openProsesModal()/prosesDivisiSubmit()) yang
     * juga mewajibkan Catatan Proses -- lihat CONTEXT.md "Proses Divisi (NOC/Admin/Customer
     * Service)".
     */
    public function tandaiDivisiSelesai(string $divisiValue): void
    {
        $divisi = DivisiTicket::from($divisiValue);
        $this->authorize('ubahStatusDivisi', [$this->ticket, $divisi]);

        if ($divisi === DivisiTicket::Teknisi) {
            // Simpan ODP port jika baru dipilih di komponen
            if ($this->odp_id && $this->odp_port_id && $this->ticket->pemasangan?->odp_port_id !== $this->odp_port_id) {
                if (! $this->simpanOdpPortTeknisi()) {
                    return;
                }
            }

            // Simpan foto yang masih tertunda di upload Livewire
            $this->uploadPendingFotoTeknisi();
            $this->loadTicket();

            if (! $this->ticket->siapTeknisiSelesai()) {
                if (! $this->ticket->pemasangan?->odp_port_id) {
                    Flux::toast(variant: 'danger', text: 'Pilih dan simpan Port ODP sebelum menandai Teknisi selesai.');
                } elseif ($this->ticket->getMedia('foto_speedtest')->isEmpty()) {
                    Flux::toast(variant: 'danger', text: 'Unggah foto speedtest sebelum menandai Teknisi selesai.');
                } elseif ($this->ticket->getMedia('foto_tanda_tangan_mou')->isEmpty()) {
                    Flux::toast(variant: 'danger', text: 'Unggah foto tanda tangan MOU sebelum menandai Teknisi selesai.');
                } else {
                    Flux::toast(variant: 'danger', text: 'Lengkapi port ODP, foto speedtest, dan foto MOU sebelum menandai Teknisi selesai.');
                }

                return;
            }
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
        $this->prosesPaketLayananId = $layanan?->paket_layanan_id;
        $this->prosesPppMode = 'auto';
        $this->prosesPppUsername = $layanan->ppp_username ?? '';
        $this->prosesPppPassword = '';
        $this->prosesUbahPaket = false;
        $this->showProsesModal = true;
    }

    public function perluPasswordManualNoc(): bool
    {
        return $this->ticket->layananPelanggan?->perluPasswordManual($this->prosesModeMikrotik === 'sudah') ?? false;
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

            $perluPasswordManual = $this->perluPasswordManualNoc();

            $this->validate([
                'prosesModeMikrotik' => ['required', 'in:proses,sudah'],
                'prosesPilihanPaket' => ['required', 'in:bawaan,berbeda'],
                'prosesRouterId' => [
                    'required',
                    'integer',
                    'exists:router,id',
                    $this->validasiRouterPaket($this->prosesPilihanPaket === 'bawaan' ? $layanan->paket_layanan_id : $this->prosesPaketLayananId),
                ],
                'prosesPaketLayananId' => ['required', 'integer', 'exists:paket_layanan,id'],
                'prosesPppMode' => ['required', 'in:auto,manual'],
                'prosesPppUsername' => [
                    Rule::requiredIf($this->prosesPppMode === 'manual'),
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('layanan_pelanggan', 'ppp_username')->ignore($layanan->id),
                ],
                'prosesPppPassword' => [Rule::requiredIf($perluPasswordManual), 'nullable', 'string', 'max:64'],
            ], [
                'prosesPppUsername.required' => 'PPP Username manual wajib diisi.',
                'prosesPppUsername.unique' => 'PPP Username sudah dipakai layanan lain.',
                'prosesPppPassword.required' => 'Password PPP asli di router wajib diisi.',
            ]);

            $paketLayananId = $this->prosesPilihanPaket === 'bawaan' ? $layanan->paket_layanan_id : $this->prosesPaketLayananId;
            app(UbahPaketLayananAction::class)->execute($layanan, $paketLayananId, $this->prosesRouterId);

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

        $usulanDisetujui = $this->ticket->pemasangan?->status_usulan_odp === StatusUsulanOdp::Disetujui;
        $odps = Odp::orderBy('nama_odp')
            ->when($usulanDisetujui, fn ($query) => $query->whereKey($this->ticket->pemasangan->odp_usulan_id))
            ->get(['id', 'nama_odp']);

        $layananTiket = $this->ticket->layananPelanggan;
        $routersAktivasi = $this->routerOnlineUntukPaket($layananTiket?->paket_layanan_id);
        $routersProses = $this->routerOnlineUntukPaket($this->prosesPilihanPaket === 'berbeda' ? $this->prosesPaketLayananId : $layananTiket?->paket_layanan_id);

        // Paket Layanan aktif untuk dropdown "Paket Berbeda" (Proses NOC) / "Ubah Paket Layanan"
        // (Proses Admin) -- tidak difilter per-router, lihat CONTEXT.md "Proses Divisi".
        $paketLayananList = PaketLayanan::aktif()->orderBy('nama_paket')->get();

        return view('livewire.ticket.show', [
            'staffList' => $staffList,
            'transisiValid' => $transisiValid,
            'odps' => $odps,
            'petaPort' => $this->petaPort(),
            'petaPortLayanan' => $this->petaPortLayanan(),
            'routersAktivasi' => $routersAktivasi,
            'routersProses' => $routersProses,
            'paketLayananList' => $paketLayananList,
            // Pemasangan lama tanpa layanan memakai alur yang sudah ditinggalkan -- tanpa Panduan Alur Tiket.
            'panduan' => $this->ticket->jenis->panduan(),
            'punyaPanduan' => $this->ticket->jenis !== JenisTicket::Pemasangan || $this->ticket->layanan_pelanggan_id,
        ]);
    }
}
