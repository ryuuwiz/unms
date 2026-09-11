Sprint A3 — UI Debounce Tombol Provision (Quick Win)
Konteks
Admin bisa tidak sengaja klik tombol "Provision"/"Re-provision" PPPoE dua kali berturut-turut (misalnya karena UI terasa lambat merespon), memicu dua dispatch job untuk layanan yang sama. Sprint A1 sudah menangani ini di level backend (ShouldBeUnique), sprint ini menangani di level UX supaya user juga dapat feedback visual yang jelas dan tidak bingung kenapa tombol "tidak merespon" saat diklik berkali-kali.
Tidak bergantung pada sprint lain — independen, bisa dikerjakan kapan saja, termasuk paralel dengan sprint lain.
Langkah 0 — Investigasi
Cari file Livewire component dan blade view yang memiliki tombol untuk memicu provisioning/re-provisioning PPPoE. Kemungkinan lokasi:

app/Livewire/Pelanggan/Show.php + resources/views/livewire/pelanggan/show.blade.php

Atau komponen terpisah khusus layanan, misalnya app/Livewire/Layanan/*.php
Cari method di Livewire component yang men-dispatch ProvisionPppoeAccountJob atau job Mikrotik terkait lainnya. Catat nama method tersebut (misalnya provisionSekarang(), reprovision(), dll — sesuaikan dengan nama asli di kode).
Laporkan file dan nama method yang ditemukan sebelum lanjut.
Scope file
Blade view hasil investigasi (satu atau lebih file, tergantung berapa banyak tempat tombol serupa muncul)
Livewire component PHP terkait (hanya kalau perlu menambah property loading state — biasanya tidak perlu perubahan PHP untuk wire:loading, cukup blade)
Implementasi
1. Tambahkan wire:loading pada tombol
Contoh (sesuaikan nama method dan teks tombol dengan yang asli):
Auto<button
    type="button"
    wire:click="provisionSekarang"
    wire:loading.attr="disabled"
    wire:target="provisionSekarang"
    class="{{-- gunakan class existing tombol ini, jangan ubah styling --}}"
>
    <span wire:loading.remove wire:target="provisionSekarang">Provision Sekarang</span>
    <span wire:loading wire:target="provisionSekarang">Memproses...</span>
</button>




Poin penting:

wire:target harus persis sama dengan nama method yang di-dispatch tombol tersebut — kalau salah, wire:loading tidak akan trigger pada tombol yang benar.

Jangan ubah class/styling existing tombol, hanya tambahkan atribut wire:loading.attr dan wire:target, plus opsional teks loading di dalam <span>.

Kalau tombol ini memakai komponen Flux (<flux:button> — aplikasi ini pakai Livewire Flux berdasarkan stack yang ada), cek dokumentasi/pola existing di codebase untuk cara idiomatis menambahkan loading state ke <flux:button> (biasanya ada prop bawaan seperti wire:loading juga didukung langsung, atau ada prop loading khusus — verifikasi dari komponen Flux lain yang sudah punya pola serupa di codebase ini sebelum menulis dari nol).
2. Terapkan pola yang sama ke semua tombol sejenis
Kalau ada beberapa tombol dengan fungsi serupa (provision, re-provision, enable, disable — apapun yang men-dispatch job Mikrotik dari sprint A1), terapkan pola yang sama di semuanya untuk konsistensi UX, bukan hanya satu tombol.
Test
Sprint ini UX-level, tidak wajib test otomatis baru. Kalau ada test Livewire existing untuk komponen ini (Livewire::test(...)), jalankan untuk pastikan tidak ada regresi (wire:loading murni atribut HTML, tidak akan mengubah hasil assertion Livewire test yang sudah ada, tapi tetap verifikasi).
Opsional (kalau ingin lebih thorough): tambahkan 1 test Livewire yang memverifikasi method tetap bisa dipanggil dan tidak error dengan perubahan blade ini — ini lebih untuk memastikan tidak ada typo di wire:target yang menyebabkan mismatch nama method.
Acceptance criteria

[ ] Semua tombol yang men-dispatch job provisioning/enable/disable Mikrotik punya wire:loading.attr="disabled" dengan wire:target yang benar.

[ ] Tidak ada perubahan pada styling/class existing tombol, kecuali penambahan state loading.

[ ] Test Livewire existing (kalau ada) tetap lulus.
Yang TIDAK boleh dilakukan di sprint ini

Jangan ubah logic PHP di Livewire component (method yang dipanggil tombol) — sprint ini murni perubahan blade/UI.

Jangan tambahkan validasi baru atau confirm dialog kecuali diminta terpisah (di luar scope sprint ini).
Setelah selesai
Laporkan:
File blade dan nama method yang ditemukan dan diubah.
Apakah aplikasi memakai komponen Flux untuk tombol ini, dan pola loading state apa yang akhirnya dipakai (atribut wire:loading manual atau prop bawaan Flux).



