<?php

namespace App\Services\Whatsapp\Contracts;

interface WhatsappGatewayDriverInterface
{
    /**
     * Kirim pesan teks ke nomor tujuan.
     *
     * @return array{success: bool, status: string, message: string, data: array<string, mixed>}
     */
    public function sendMessage(string $phone, string $message): array;

    /**
     * Ping / Cek status konektivitas gateway.
     *
     * @return array{connected: bool, phone: string, quota: string, expired_at: ?string, message: string, session_status: string, raw: array<string, mixed>}
     */
    public function pingConnection(): array;

    /**
     * Ambil status detail sesi/perangkat.
     *
     * @return array{connected: bool, phone: string, quota: string, expired_at: ?string, message: string, session_status: string, raw: array<string, mixed>}
     */
    public function getDeviceInfo(): array;

    /**
     * Ambil QR Code pairing (Base64 atau string).
     *
     * @return array{success: bool, status: string, qr: ?string, message: string}
     */
    public function getQrCode(): array;

    /**
     * Jalankan session (Start).
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function startSession(): array;

    /**
     * Hentikan session (Stop).
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function stopSession(): array;

    /**
     * Muat ulang session (Restart).
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function restartSession(): array;

    /**
     * Logout session.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function logoutSession(): array;

    /**
     * Cek apakah nomor telepon terdaftar di WhatsApp.
     *
     * @return array{success: bool, exists: bool, phone: string, message: string}
     */
    public function checkNumberStatus(string $phone): array;

    /**
     * Dapatkan daftar seluruh session yang tersedia di gateway.
     *
     * @return array<int, array{name: string, status: string, phone: ?string, pushName: ?string, connected: bool, raw: array<string, mixed>}>
     */
    public function listSessions(): array;

    /**
     * Kirim status sedang mengetik (typing presence) ke chat.
     */
    public function startTyping(string $phone): bool;

    /**
     * Hentikan status sedang mengetik di chat.
     */
    public function stopTyping(string $phone): bool;

    /**
     * Tandai pesan telah dibaca (sendSeen).
     */
    public function sendSeen(string $phone): bool;
}
