<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Perusahaan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Instance Invoice yang ditagihkan.
     */
    public Invoice $invoice;

    /**
     * Data tambahan opsional untuk kustomisasi pesan.
     */
    public array $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(Invoice $invoice, array $data = [])
    {
        $this->invoice = $invoice;
        $this->data = $data;
    }

    /**
     * Tentukan channel pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Representasi email dari notifikasi pengingat tagihan.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // 1. Ambil nama perusahaan secara aman menggunakan Operator Null Coalescing (??)
        // Menggunakan Perusahaan::query() agar terhindar dari konflik method/parameter
        $namaPerusahaan = $this->data['nama_perusahaan']
            ?? Perusahaan::query()->first()?->nama_perusahaan
            ?? config('app.name', 'MYARSYILA INDONESIA INTERKONEKSI');

        // 2. Format atribut pendukung invoice
        $nomorInvoice = $this->invoice->nomor_invoice ?? 'INV-' . $this->invoice->id;
        $totalTagihan = number_format($this->invoice->total ?? 0, 0, ',', '.');
        $jatuhTempo = $this->invoice->tanggal_jatuh_tempo
            ? $this->invoice->tanggal_jatuh_tempo->format('d/m/Y')
            : '-';

        // 3. URL Portal Pelanggan
        $urlPembayaran = route('portal.invoice.show', $this->invoice->id);

        return (new MailMessage)
            ->subject("Pengingat Tagihan: {$nomorInvoice} - {$namaPerusahaan}")
            ->greeting("Halo " . ($notifiable->nama_pelanggan ?? $notifiable->name ?? 'Pelanggan') . ",")
            ->line("Ini adalah pengingat pembayaran untuk tagihan **{$nomorInvoice}** sebesar **Rp {$totalTagihan}** dengan tanggal jatuh tempo **{$jatuhTempo}**.")
            ->line("Mohon segera melakukan pembayaran untuk memastikan layanan internet Anda tetap aktif dan menghindari pemutusan/isolir otomatis.")
            ->action('Lihat & Bayar Invoice', $urlPembayaran)
            ->line('Jika Anda sudah melakukan pembayaran, silakan abaikan pesan ini.')
            ->salutation("Hormat kami,\n" . $namaPerusahaan);
    }

    /**
     * Simpan rekaman notifikasi ke dalam database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $namaPerusahaan = $this->data['nama_perusahaan']
            ?? Perusahaan::query()->first()?->nama_perusahaan
            ?? config('app.name', 'MYARSYILA INDONESIA INTERKONEKSI');

        return [
            'invoice_id' => $this->invoice->id,
            'nomor_invoice' => $this->invoice->nomor_invoice,
            'total' => $this->invoice->total,
            'tanggal_jatuh_tempo' => $this->invoice->tanggal_jatuh_tempo?->toDateTimeString(),
            'nama_perusahaan' => $namaPerusahaan,
            'pesan' => "Pengingat tagihan {$this->invoice->nomor_invoice} sebesar Rp " . number_format($this->invoice->total ?? 0, 0, ',', '.'),
        ];
    }
}
