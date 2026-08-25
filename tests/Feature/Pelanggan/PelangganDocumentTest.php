<?php

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Create;
use App\Livewire\Pelanggan\Show;
use App\Models\Pelanggan;
use App\Models\User;
use App\Services\CustomerDocumentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->sales1 = User::factory()->create(['name' => 'Sales Rian', 'status' => UserStatus::Active]);
    $this->sales1->assignRole('sales');

    $this->sales2 = User::factory()->create(['name' => 'Sales Budi', 'status' => UserStatus::Active]);
    $this->sales2->assignRole('sales');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');
});

test('can create pelanggan with encrypted ktp and mou document', function () {
    $this->actingAs($this->sales1);

    $ktpFile = UploadedFile::fake()->image('ktp_budi.jpg', 600, 400);
    $mouFile = UploadedFile::fake()->create('kontrak_langganan.pdf', 500, 'application/pdf');

    Livewire::test(Create::class)
        ->set('no_reg', 'BF2608202601')
        ->set('tipe_pelanggan', TipePelanggan::Rumah->value)
        ->set('nama_depan', 'Budi')
        ->set('nama_belakang', 'Santoso')
        ->set('no_hp', '081234567890')
        ->set('alamat_lengkap', 'Jl. Merdeka No. 10')
        ->set('status', StatusPelanggan::Prospek->value)
        ->set('foto_ktp', $ktpFile)
        ->set('dokumen_mou', $mouFile)
        ->set('jenis_dokumen', 'MOU / Kontrak')
        ->set('nomor_dokumen', 'MOU/2026/001')
        ->set('keterangan_dokumen', 'Kontrak 12 Bulan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pelanggan.index'));

    $pelanggan = Pelanggan::where('no_reg', 'BF2608202601')->first();
    expect($pelanggan)->not->toBeNull()
        ->and($pelanggan->hasKtp())->toBeTrue();

    $ktpMedia = $pelanggan->getKtpMedia();
    expect($ktpMedia)->not->toBeNull();

    // Verifikasi enkripsi at-rest: isi berkas fisik di disk adalah ciphertext
    $rawStorageContent = file_get_contents($ktpMedia->getPath());
    expect($rawStorageContent)->not->toBeEmpty();

    // Ciphertext harus dapat didekripsi via Crypt
    $decrypted = Crypt::decryptString($rawStorageContent);
    expect($decrypted)->not->toBeEmpty();

    // Verifikasi koleksi dokumen MOU
    $mouMedia = $pelanggan->getFirstMedia('dokumen');
    expect($mouMedia)->not->toBeNull()
        ->and($mouMedia->getCustomProperty('jenis_dokumen'))->toBe('MOU / Kontrak')
        ->and($mouMedia->getCustomProperty('nomor_dokumen'))->toBe('MOU/2026/001');
});

test('preview ktp endpoint returns watermarked image for authorized staff and logs activity', function () {
    $pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $this->sales1->id]);

    $ktpFile = UploadedFile::fake()->image('ktp_asli.jpg', 800, 500);
    app(CustomerDocumentService::class)->storeEncryptedMedia($pelanggan, $ktpFile, 'ktp');

    // Sales pembuat dapat melihat
    $this->actingAs($this->sales1);
    $response = $this->get(route('pelanggan.ktp.preview', $pelanggan));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    // Cek Spatie Activitylog
    $activity = Activity::where('subject_type', Pelanggan::class)
        ->where('subject_id', $pelanggan->id)
        ->where('causer_id', $this->sales1->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Melihat foto KTP');
});

test('preview ktp endpoint denies unauthorized staff (sales lain & noc)', function () {
    $pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $this->sales1->id]);

    $ktpFile = UploadedFile::fake()->image('ktp_asli.jpg', 800, 500);
    app(CustomerDocumentService::class)->storeEncryptedMedia($pelanggan, $ktpFile, 'ktp');

    // Sales lain ditolak (403)
    $this->actingAs($this->sales2);
    $this->get(route('pelanggan.ktp.preview', $pelanggan))->assertForbidden();

    // NOC ditolak (403)
    $this->actingAs($this->noc);
    $this->get(route('pelanggan.ktp.preview', $pelanggan))->assertForbidden();
});

test('stream dokumen endpoint delivers decrypted file for authorized staff and logs activity', function () {
    $pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $this->sales1->id]);

    $docFile = UploadedFile::fake()->create('mou_resmi.pdf', 300, 'application/pdf');
    $media = app(CustomerDocumentService::class)->storeEncryptedMedia(
        $pelanggan,
        $docFile,
        'dokumen',
        ['jenis_dokumen' => 'MOU / Kontrak']
    );

    $this->actingAs($this->sales1);
    $response = $this->get(route('pelanggan.dokumen.stream', [$pelanggan, $media]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Cek Activity log
    $activity = Activity::where('subject_type', Pelanggan::class)
        ->where('subject_id', $pelanggan->id)
        ->where('causer_id', $this->sales1->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Membuka dokumen');
});

test('can upload and delete additional document from show component', function () {
    $pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $this->sales1->id]);
    $this->actingAs($this->sales1);

    $docFile = UploadedFile::fake()->create('formulir_berlangganan.pdf', 200, 'application/pdf');

    $component = Livewire::test(Show::class, ['pelanggan' => $pelanggan])
        ->set('docFile', $docFile)
        ->set('docJenis', 'Formulir Berlangganan')
        ->set('docNomor', 'FORM/2026/001')
        ->call('saveDokumen')
        ->assertHasNoErrors();

    $media = $pelanggan->getFirstMedia('dokumen');
    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty('jenis_dokumen'))->toBe('Formulir Berlangganan');

    // Hapus dokumen
    $component->call('deleteDokumen', $media->id);
    expect($pelanggan->fresh()->getMedia('dokumen'))->toBeEmpty();
});
