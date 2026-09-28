<?php

use App\Actions\Invoice\HapusPermanenInvoiceAction;
use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\UserStatus;
use App\Livewire\Invoice\Index;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

test('super_admin dapat menghapus permanen invoice dibatalkan yang tidak pernah punya riwayat pembayaran', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    $invoice->delete();

    app(HapusPermanenInvoiceAction::class)->execute($invoice->fresh(), $this->superAdmin, 'Invoice salah terbit');

    expect(Invoice::withTrashed()->find($invoice->id))->toBeNull();

    $log = Activity::where('subject_type', Invoice::class)->where('subject_id', $invoice->id)
        ->where('causer_id', $this->superAdmin->id)->latest('id')->first();
    expect($log->getProperty('action'))->toBe('hapus_permanen_invoice')
        ->and($log->getProperty('alasan'))->toBe('Invoice salah terbit');
});

test('invoice yang belum berstatus dibatalkan tidak bisa dihapus permanen', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::MenungguPembayaran]);

    expect(fn () => app(HapusPermanenInvoiceAction::class)->execute($invoice, $this->superAdmin, 'Alasan'))
        ->toThrow(Exception::class, 'Dibatalkan');

    expect(Invoice::find($invoice->id))->not->toBeNull();
});

test('invoice yang pernah punya riwayat pembayaran (walau sudah soft-delete) tidak bisa dihapus permanen', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    $pembayaran = Pembayaran::factory()->create(['invoice_id' => $invoice->id]);
    $pembayaran->delete();
    $invoice->delete();

    expect(fn () => app(HapusPermanenInvoiceAction::class)->execute($invoice->fresh(), $this->superAdmin, 'Alasan'))
        ->toThrow(Exception::class, 'riwayat pembayaran');

    expect(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();
});

test('invoice yang pernah punya transaksi payment gateway tidak bisa dihapus permanen', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'external_id' => 'ext-'.$invoice->id,
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => 100000,
    ]);
    $invoice->delete();

    expect(fn () => app(HapusPermanenInvoiceAction::class)->execute($invoice->fresh(), $this->superAdmin, 'Alasan'))
        ->toThrow(Exception::class, 'payment gateway');

    expect(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();
});

test('database menolak forceDelete langsung pada invoice yang masih punya baris pembayaran', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    Pembayaran::factory()->create(['invoice_id' => $invoice->id]);
    $invoice->delete();

    expect(fn () => DB::table('invoice')->where('id', $invoice->id)->delete())
        ->toThrow(QueryException::class);
});

test('UI: hanya pemegang izin invoice.hapus_permanen yang bisa, dan alasan wajib', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    $invoice->delete();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('permanentDeleteId', $invoice->id)
        ->set('alasanHapusPermanen', 'Invoice duplikat')
        ->call('hapusPermanenInvoice')
        ->assertForbidden();

    expect(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('permanentDeleteId', $invoice->id)
        ->set('alasanHapusPermanen', '')
        ->call('hapusPermanenInvoice')
        ->assertHasErrors(['alasanHapusPermanen' => 'required']);

    expect(Invoice::withTrashed()->find($invoice->id))->not->toBeNull();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('permanentDeleteId', $invoice->id)
        ->set('alasanHapusPermanen', 'Invoice duplikat')
        ->call('hapusPermanenInvoice')
        ->assertHasNoErrors();

    expect(Invoice::withTrashed()->find($invoice->id))->toBeNull();
});

test('tombol hapus permanen muncul di daftar invoice untuk super_admin dan tidak untuk admin', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::Dibatalkan]);
    $invoice->delete();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertSeeHtml('confirmHapusPermanen('.$invoice->id.')');

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertDontSeeHtml('confirmHapusPermanen('.$invoice->id.')');
});
