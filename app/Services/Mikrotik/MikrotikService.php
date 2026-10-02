<?php

namespace App\Services\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\MikrotikException;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\RouterPaket;
use App\Support\PppDeletionContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikrotikService
{
    /**
     * Cache "router_id:profil_id" / "router_id:pool_id" already synced in THIS
     * request/job. ensurePaketProfile()/syncIpPool() are correct but redundant
     * when called per-secret right after a bulk sync already did the same
     * work (autoRecoverPppSecrets, provisionRouterFull): this skips the
     * repeat RouterOS round-trips that were spiking router CPU during mass
     * recovery/provisioning. ponytail: instance-level memo, not cross-request
     * -- resets naturally each time the service is resolved for a new job.
     *
     * @var array<string, true>
     */
    private array $ensuredProfileCache = [];

    /** @var array<string, true> */
    private array $syncedPoolCache = [];

    /**
     * Komentar yang menandai secret milik teknisi/sistem: tidak pernah dihapus atau ditimpa otomatis.
     */
    private const PROTECTED_COMMENT_PATTERN = '/^(MANUAL:|NOC:|SYSTEM:|WHITELIST:)/i';

    /** @var array<int, string> */
    private const SYSTEM_PROTECTED_USERS = ['admin', 'api', 'support', 'guest', 'default-encryption'];

    /** Jumlah penghapusan secret pada eksekusi berjalan (lihat config mikrotik.max_deletes_per_run). */
    private int $deleteBudgetUsed = 0;

    private function resetDeleteBudget(): void
    {
        $this->deleteBudgetUsed = 0;
    }

    private function consumeDeleteBudget(): bool
    {
        if ($this->deleteBudgetUsed >= (int) config('mikrotik.max_deletes_per_run', 10)) {
            return false;
        }

        $this->deleteBudgetUsed++;

        return true;
    }

    /**
     * Secret yang tidak boleh dihapus/ditimpa otomatis: akun sistem bawaan atau berkomentar MANUAL:/NOC:/SYSTEM:/WHITELIST:.
     *
     * @param  array<string, mixed>  $secret
     */
    public static function isProtectedSecret(array $secret): bool
    {
        return in_array(strtolower((string) ($secret['name'] ?? '')), self::SYSTEM_PROTECTED_USERS, true)
            || (bool) preg_match(self::PROTECTED_COMMENT_PATTERN, (string) ($secret['comment'] ?? ''));
    }

    /**
     * Library RouterOS tidak melempar exception untuk `!trap`; galat hanya muncul sebagai after.message.
     * Tanpa pengecekan ini respons galat terbaca sebagai daftar kosong atau mutasi "berhasil".
     *
     * @throws MikrotikException
     */
    private function assertNoTrap(mixed $response, string $konteks): void
    {
        if (is_array($response) && isset($response['after']['message'])) {
            throw new MikrotikException("RouterOS menolak {$konteks}: {$response['after']['message']}");
        }
    }

    /**
     * Snapshot secret untuk audit penghapusan (tanpa password).
     *
     * @param  array<string, mixed>  $secret
     * @return array<string, mixed>
     */
    private function snapshotSecret(array $secret): array
    {
        unset($secret['password']);

        return $secret;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function recordDeletion(Router $router, string $username, PppDeletionContext $context, string $outcome, ?array $snapshot, ?int $layananId = null): void
    {
        MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $layananId,
            'job_type' => MikrotikJobType::DeletePppoe,
            'status' => $outcome === 'deleted' ? MikrotikJobStatus::Success : MikrotikJobStatus::Dilewati,
            'attempt_count' => 1,
            'payload' => array_merge($context->toArray(), [
                'username' => $username,
                'outcome' => $outcome,
                'snapshot' => $snapshot,
            ]),
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * Inisialisasi koneksi RouterOS Client.
     *
     * @throws MikrotikConnectionException
     */
    public function getClient(Router $router, int $timeout = 10, int $socketTimeout = 15, int $attempts = 1, int $delay = 1): Client
    {
        try {
            $config = new Config([
                'host' => $router->ip_address,
                'user' => $router->username,
                'pass' => $router->password_terenkripsi,
                'port' => (int) $router->port,
                'timeout' => $timeout,
                'socket_timeout' => $socketTimeout,
                'attempts' => $attempts,
                'delay' => $delay,
                'throw_timeout_exception' => false,
                'socket_options' => [
                    'tcp_nodelay' => true,
                ],
            ]);

            return new Client($config);
        } catch (Throwable $e) {
            throw new MikrotikConnectionException(
                "Gagal terhubung ke router {$router->nama_router} ({$router->ip_address}:{$router->port}): {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Uji koneksi ke router dan perbarui metrik sistem.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function testConnection(Router $router, int $timeout = 3, ?Client $client = null): array
    {
        $previousStatus = $router->status_koneksi;

        try {
            $client = $client ?? $this->getClient($router, $timeout, $timeout, 1);
            $query = new Query('/system/resource/print');
            $response = $client->query($query)->read();

            if (empty($response) || isset($response['after']['message'])) {
                $errMsg = $response['after']['message'] ?? 'Respons tidak valid dari RouterOS';
                throw new MikrotikException($errMsg);
            }

            $res = $response[0] ?? [];
            $cpuLoad = isset($res['cpu-load']) ? (int) $res['cpu-load'] : null;
            $freeMemory = isset($res['free-memory']) ? (int) $res['free-memory'] : null;
            $totalMemory = isset($res['total-memory']) ? (int) $res['total-memory'] : null;
            $uptime = $res['uptime'] ?? null;
            $boardName = $res['board-name'] ?? null;
            $version = $res['version'] ?? null;

            $router->update([
                'status_koneksi' => StatusRouter::Online,
                'last_ping_at' => Carbon::now(),
                'last_ping_status' => 'success',
                'last_ping_message' => 'Koneksi berhasil dan responsif',
                'cpu_load' => $cpuLoad,
                'memory_free' => $freeMemory,
                'memory_total' => $totalMemory,
                'uptime' => $uptime,
                'board_name' => $boardName,
                'routeros_version' => $version,
            ]);

            if ($previousStatus !== StatusRouter::Online) {
                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'job_type' => MikrotikJobType::TestConnection,
                    'status' => MikrotikJobStatus::Success,
                    'attempt_count' => 1,
                    'payload' => [
                        'version' => $version,
                        'cpu_load' => $cpuLoad,
                        'uptime' => $uptime,
                        'board_name' => $boardName,
                    ],
                    'finished_at' => Carbon::now(),
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'Koneksi berhasil',
                'resources' => $res,
            ];
        } catch (Throwable $e) {
            $router->update([
                'status_koneksi' => StatusRouter::Offline,
                'last_ping_at' => Carbon::now(),
                'last_ping_status' => 'failed',
                'last_ping_message' => Str::limit($e->getMessage(), 250),
            ]);

            // Hanya saat transisi (seperti log sukses di atas): ping tiap 10 dtk ke router mati tidak membanjiri log.
            if ($previousStatus !== StatusRouter::Offline) {
                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'job_type' => MikrotikJobType::TestConnection,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'finished_at' => Carbon::now(),
                ]);
            }

            throw new MikrotikException(
                "Uji koneksi gagal pada {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Dapatkan informasi system resource router.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function getSystemResource(Router $router, ?Client $client = null): array
    {
        $client = $client ?? $this->getClient($router);
        $query = new Query('/system/resource/print');
        $response = $client->query($query)->read();

        if (empty($response) || ! isset($response[0])) {
            throw new MikrotikException("Gagal mengambil data resource dari router {$router->nama_router}");
        }

        return $response[0];
    }

    /**
     * Pastikan PPP Profile sebuah Router Paket tersedia di RouterOS (ADR-0063): nama = nama paket,
     * `rate-limit` dari profil bandwidth paket, `local-address` = `.1` pool terpilih (alamat MikroTik),
     * `remote-address` = pool terpilih (RouterOS yang membagikan IP pelanggan). Pool disinkronkan dulu.
     * Tanpa komentar: kepemilikan ditentukan nama.
     *
     * @throws MikrotikException
     */
    public function ensurePaketProfile(RouterPaket $routerPaket, ?Client $client = null): string
    {
        $routerPaket->loadMissing(['router', 'ipPool', 'paketLayanan.profilBandwidth']);
        $profil = $routerPaket->paketLayanan->profilBandwidth;
        $profileName = $routerPaket->namaProfile();

        if ($profileName === '' || ! $profil) {
            throw new MikrotikException("Paket Router #{$routerPaket->id} tidak punya nama paket atau profil bandwidth yang valid. Sinkronisasi PPP Profile dibatalkan.");
        }

        return $this->ensureProfile($routerPaket->router, $profileName, $routerPaket->ipPool, ['rate-limit' => $profil->routerOsRateLimit()], $client);
    }

    /**
     * Pastikan profile `ISOLIR` router (CONTEXT.md "Isolir") dari IP Pool Isolir router itu; tanpa rate-limit.
     *
     * @throws MikrotikException
     */
    public function ensureIsolirProfile(Router $router, ?Client $client = null): string
    {
        $pool = $router->ipPoolIsolir;

        if (! $pool) {
            throw new MikrotikException("Router {$router->nama_router} belum punya IP Pool Isolir. Pilih IP Pool Isolir di halaman Edit Router agar layanan bisa diisolir.");
        }

        return $this->ensureProfile($router, Router::PROFILE_ISOLIR, $pool, [], $client);
    }

    /**
     * Buat atau perbarui satu PPP Profile billing: `local-address` = `.1` pool (alamat MikroTik),
     * `remote-address` = pool (RouterOS yang membagikan IP pelanggan), tanpa komentar. Pool disinkronkan dulu.
     *
     * @param  array<string, string>  $atributTambahan
     *
     * @throws MikrotikException
     */
    private function ensureProfile(Router $router, string $profileName, IpPool $pool, array $atributTambahan, ?Client $client): string
    {
        $cacheKey = "{$router->id}:{$profileName}";

        if (isset($this->ensuredProfileCache[$cacheKey])) {
            return $profileName;
        }

        $attributes = $atributTambahan + [
            'local-address' => $pool->getGatewayAddress(),
            'remote-address' => $pool->nama_pool,
        ];

        try {
            $client = $client ?? $this->getClient($router);
            $this->syncIpPool($router, $pool, $client);
            $existing = $this->findProfileEntries($client, $profileName);

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                if ($this->createProfileEntry($client, $router, $profileName, $attributes)) {
                    $this->ensuredProfileCache[$cacheKey] = true;

                    return $profileName;
                }

                // Race sempit terdeteksi di createProfileEntry(): entry sudah ada, re-query untuk update di bawah.
                $existing = $this->findProfileEntries($client, $profileName);
            }

            $this->updateProfileEntry($client, $existing[0]['.id'], $profileName, $attributes);
            $this->removeDuplicateProfileEntries($client, $router, $existing, $profileName);

            $this->ensuredProfileCache[$cacheKey] = true;

            return $profileName;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal sinkronisasi PPP Profile {$profileName} di router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findProfileEntries(Client $client, string $profileName): array
    {
        $query = (new Query('/ppp/profile/print'))->where('name', $profileName);

        return $client->query($query)->read();
    }

    /**
     * @param  array<string, string>  $attributes
     *
     * @throws MikrotikException Bila create gagal karena alasan selain race dengan proses lain.
     */
    private function createProfileEntry(Client $client, Router $router, string $profileName, array $attributes): bool
    {
        try {
            $addQuery = new Query('/ppp/profile/add');
            $addQuery->equal('name', $profileName);
            foreach ($attributes as $key => $value) {
                $addQuery->equal($key, $value);
            }
            $res = $client->query($addQuery)->read();
            if (isset($res['after']['message'])) {
                throw new MikrotikException($res['after']['message']);
            }

            return true;
        } catch (Throwable $e) {
            // Race sempit: profile mungkin sudah dibuat proses lain di antara query & create di atas.
            // Re-query sekali untuk verifikasi state aktual, baru menyerah kalau memang bukan soal duplikat.
            $existing = $this->findProfileEntries($client, $profileName);

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                throw $e;
            }

            Log::warning('Create PPP profile gagal tapi entry ternyata sudah ada (race sempit), melanjutkan ke update.', [
                'profile_name' => $profileName,
                'router_id' => $router->id,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function updateProfileEntry(Client $client, string $profileId, string $profileName, array $attributes): void
    {
        $setQuery = (new Query('/ppp/profile/set'))->equal('.id', $profileId)->equal('comment', '');
        foreach ($attributes as $key => $value) {
            $setQuery->equal($key, $value);
        }
        $this->assertNoTrap($client->query($setQuery)->read(), "pembaruan PPP Profile {$profileName}");
    }

    /**
     * Hapus duplikat profile jika ada lebih dari 1 di RouterOS (hanya yang namanya persis sama).
     *
     * @param  array<int, array<string, mixed>>  $existing
     */
    private function removeDuplicateProfileEntries(Client $client, Router $router, array $existing, string $profileName): void
    {
        for ($i = 1; $i < count($existing); $i++) {
            if (! isset($existing[$i]['.id']) || ($existing[$i]['name'] ?? $profileName) !== $profileName) {
                continue;
            }

            try {
                $client->query((new Query('/ppp/profile/remove'))->equal('.id', $existing[$i]['.id']))->read();
            } catch (Throwable $e) {
                Log::warning('Gagal menghapus duplikat PPP profile, dilewati.', [
                    'router_id' => $router->id,
                    'profile_name' => $profileName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Ganti nama PPP Profile paket di router (paket di-rename). Tidak melakukan apa-apa bila profile
     * lama tidak ada; secret yang merujuknya ikut ternamai ulang oleh RouterOS.
     *
     * @throws MikrotikException
     */
    public function renamePaketProfile(Router $router, string $namaLama, string $namaBaru, ?Client $client = null): void
    {
        try {
            $client = $client ?? $this->getClient($router);
            $lama = $client->query((new Query('/ppp/profile/print'))->where('name', $namaLama))->read();
            $baru = $client->query((new Query('/ppp/profile/print'))->where('name', $namaBaru))->read();

            if (isset($lama[0]['.id']) && ! isset($baru[0]['.id'])) {
                $this->assertNoTrap(
                    $client->query((new Query('/ppp/profile/set'))->equal('.id', $lama[0]['.id'])->equal('name', $namaBaru))->read(),
                    "rename PPP Profile {$namaLama}"
                );
            }
        } catch (Throwable $e) {
            throw new MikrotikException("Gagal mengganti nama PPP Profile {$namaLama} di router {$router->nama_router}: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Hapus PPP Profile paket dari router bila tidak ada secret yang masih memakainya (Router Paket dihapus).
     *
     * @return bool true bila dihapus
     *
     * @throws MikrotikException
     */
    public function hapusPaketProfile(Router $router, string $namaProfile, ?Client $client = null): bool
    {
        try {
            $client = $client ?? $this->getClient($router);
            $profiles = $client->query((new Query('/ppp/profile/print'))->where('name', $namaProfile))->read();
            $dipakai = $client->query((new Query('/ppp/secret/print'))->where('profile', $namaProfile))->read();

            if (! isset($profiles[0]['.id']) || isset($dipakai[0]['.id'])) {
                return false;
            }

            $this->assertNoTrap(
                $client->query((new Query('/ppp/profile/remove'))->equal('.id', $profiles[0]['.id']))->read(),
                "hapus PPP Profile {$namaProfile}"
            );

            return true;
        } catch (Throwable $e) {
            throw new MikrotikException("Gagal menghapus PPP Profile {$namaProfile} di router {$router->nama_router}: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Profile yang seharusnya dipakai secret layanan: `ISOLIR` bila Suspend, selain itu profile paketnya.
     * Paket tetap wajib terdaftar di router itu walau sedang diisolir.
     *
     * @throws MikrotikException
     */
    public function profilTujuan(Router $router, LayananPelanggan $layanan, ?Client $client = null): string
    {
        $profilePaket = $this->ensurePaketProfile($this->routerPaketLayanan($router, $layanan), $client);

        return $layanan->status === StatusLayanan::Suspend ? $this->ensureIsolirProfile($router, $client) : $profilePaket;
    }

    /**
     * Router Paket untuk paket layanan di router ini; gagal jelas bila paket belum didaftarkan ke router itu.
     *
     * @throws MikrotikException
     */
    private function routerPaketLayanan(Router $router, LayananPelanggan $layanan): RouterPaket
    {
        $routerPaket = RouterPaket::query()
            ->where('router_id', $router->id)
            ->where('paket_layanan_id', $layanan->paket_layanan_id)
            ->first();

        if (! $routerPaket) {
            $namaPaket = $layanan->paketLayanan->nama_paket ?? "#{$layanan->paket_layanan_id}";
            throw new MikrotikException("Paket {$namaPaket} belum didaftarkan ke router {$router->nama_router}. Tambahkan router di Detail Paket, lalu provisi ulang.");
        }

        return $routerPaket;
    }

    /**
     * Buat atau perbarui akun PPPoE Secret di RouterOS secara idempoten.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function createOrUpdatePppoeSecret(Router $router, LayananPelanggan $layanan, ?Client $client = null): array
    {
        try {
            $username = trim((string) $layanan->ppp_username);
            $password = (string) $layanan->ppp_password_terenkripsi;

            // 1. Validasi Format Username PPPoE (kompatibilitas MikroTik & UNMS)
            if (! preg_match('/^[a-zA-Z0-9._-]+$/', $username) || strlen($username) < 3 || strlen($username) > 64) {
                throw new MikrotikException("Format username PPPoE '{$username}' tidak valid. Hanya karakter alfanumerik, titik, strip, dan underscore (3-64 karakter) yang diperbolehkan.");
            }

            if (empty($password)) {
                throw new MikrotikException("Password PPPoE untuk '{$username}' tidak boleh kosong.");
            }

            // Layanan Berhenti tidak boleh dibuat ulang di router: secretnya sengaja dihapus (ADR 0053).
            if ($layanan->status === StatusLayanan::Berhenti) {
                throw new MikrotikException("Layanan {$username} berstatus Berhenti; provisi ke router dibatalkan.");
            }

            // 2. Strict Guard: Pastikan Paket Layanan & Profil Bandwidth terdefinisi valid di UNMS
            $paket = $layanan->paketLayanan;
            $profil = $paket?->profilBandwidth;

            if (! $paket || ! $profil || empty($profil->nama_bandwidth)) {
                throw new MikrotikException("Layanan {$username} tidak memiliki paket layanan atau profil bandwidth yang valid di UNMS. Provisi dibatalkan.");
            }

            // 3. Strict Guard: paket harus terdaftar di router ini (Router Paket, ADR-0063), sebelum membuka koneksi
            $this->routerPaketLayanan($router, $layanan);

            $client = $client ?? $this->getClient($router);

            // 4. Profile paket (atau ISOLIR bila Suspend) membawa pool; alamat literal di secret (IP Statis/Publik) menimpanya.
            $profileName = $this->profilTujuan($router, $layanan, $client);

            // 6. local/remote-address hanya untuk alamat literal (IP Publik / IP Statis); null = dinamis, dibiarkan kosong.
            $remoteAddress = $layanan->resolveRemoteAddress();
            $localAddress = $layanan->resolveLocalAddress();

            // Isolir memakai profile ISOLIR, bukan disable (CONTEXT.md "Isolir").
            $isDisabled = 'no';

            // 6. Cek apakah secret sudah ada di RouterOS
            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();
            $action = 'created';

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                try {
                    // Buat secret baru
                    $addQuery = (new Query('/ppp/secret/add'))
                        ->equal('name', $username)
                        ->equal('password', $password)
                        ->equal('service', 'pppoe')
                        ->equal('profile', $profileName)
                        ->equal('disabled', $isDisabled);

                    if ($remoteAddress !== null) {
                        $addQuery->equal('remote-address', $remoteAddress);
                    }

                    if ($localAddress !== null) {
                        $addQuery->equal('local-address', $localAddress);
                    }

                    $result = $client->query($addQuery)->read();
                    if (isset($result['after']['message'])) {
                        throw new MikrotikException($result['after']['message']);
                    }

                    $action = 'created';
                } catch (Throwable $e) {
                    // Race sempit: secret mungkin sudah dibuat proses lain di antara query & create di atas.
                    // Re-query sekali untuk verifikasi state aktual, baru menyerah kalau memang bukan soal duplikat.
                    $existing = $client->query($findQuery)->read();

                    if (empty($existing) || ! isset($existing[0]['.id'])) {
                        throw $e;
                    }

                    Log::warning('Create PPP secret gagal tapi entry ternyata sudah ada (race sempit), melanjutkan ke update.', [
                        'layanan_id' => $layanan->id,
                        'username' => $username,
                        'router_id' => $router->id,
                    ]);
                }
            }

            if (! empty($existing) && isset($existing[0]['.id'])) {
                // Update secret eksisting utama
                $secretId = $existing[0]['.id'];
                $setQuery = (new Query('/ppp/secret/set'))
                    ->equal('.id', $secretId)
                    ->equal('password', $password)
                    ->equal('service', 'pppoe')
                    ->equal('profile', $profileName)
                    // Billing tidak memberi komentar; komentar `UNMS:` lama dikosongkan (ADR-0063).
                    ->equal('comment', '')
                    ->equal('disabled', $isDisabled);

                if ($remoteAddress !== null) {
                    $setQuery->equal('remote-address', $remoteAddress);
                }

                if ($localAddress !== null) {
                    $setQuery->equal('local-address', $localAddress);
                }

                $result = $client->query($setQuery)->read();
                $action = 'updated';

                // Jika layanan tidak lagi memiliki remote-address namun di RouterOS masih ada, unset
                if ($remoteAddress === null && ! empty($existing[0]['remote-address'])) {
                    try {
                        $unsetQuery = (new Query('/ppp/secret/unset'))
                            ->equal('.id', $secretId)
                            ->equal('value-name', 'remote-address');
                        $client->query($unsetQuery)->read();
                    } catch (Throwable $e) {
                        Log::warning('Gagal unset remote-address PPP secret, dilewati.', [
                            'router_id' => $router->id,
                            'username' => $username,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Jika layanan tidak lagi memiliki local-address namun di RouterOS masih ada, unset
                if ($localAddress === null && ! empty($existing[0]['local-address'])) {
                    try {
                        $unsetQuery = (new Query('/ppp/secret/unset'))
                            ->equal('.id', $secretId)
                            ->equal('value-name', 'local-address');
                        $client->query($unsetQuery)->read();
                    } catch (Throwable $e) {
                        Log::warning('Gagal unset local-address PPP secret, dilewati.', [
                            'router_id' => $router->id,
                            'username' => $username,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Hapus entri duplikat jika ada lebih dari 1 secret dengan username yang sama di RouterOS
                if (count($existing) > 1) {
                    for ($i = 1; $i < count($existing); $i++) {
                        // Username ini terdaftar di billing, tetapi entri berkomentar MANUAL:/NOC:/... tetap tidak disentuh.
                        if (isset($existing[$i]['.id']) && ! self::isProtectedSecret($existing[$i])) {
                            try {
                                $removeDupQuery = (new Query('/ppp/secret/remove'))->equal('.id', $existing[$i]['.id']);
                                $this->assertNoTrap($client->query($removeDupQuery)->read(), "penghapusan duplikat {$username}");
                                $this->recordDeletion(
                                    $router,
                                    $username,
                                    PppDeletionContext::system('provisi', 'Provisi PPPoE: entri duplikat bernama sama untuk username terdaftar di billing'),
                                    'deleted',
                                    $this->snapshotSecret($existing[$i]),
                                    $layanan->id,
                                );
                            } catch (Throwable $e) {
                                Log::warning('Gagal menghapus duplikat PPP secret, dilewati.', [
                                    'router_id' => $router->id,
                                    'username' => $username,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            }

            if (isset($result['after']['message'])) {
                throw new MikrotikException($result['after']['message']);
            }

            $layanan->update([
                'terprovisi_pada' => Carbon::now(),
                'provisioning_status' => ProvisioningStatus::Success,
                'last_provisioning_error' => null,
            ]);

            return [
                'status' => 'success',
                'action' => $action,
                'username' => $username,
            ];
        } catch (Throwable $e) {
            $layanan->update([
                'provisioning_status' => ProvisioningStatus::Failed,
                'last_provisioning_error' => $e->getMessage(),
            ]);

            throw new MikrotikException(
                "Gagal provisi PPPoE {$layanan->ppp_username} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Update profil PPP Secret di RouterOS saat terjadi perubahan paket (Upgrade/Downgrade).
     * Memastikan profil baru ada di router, mengubah secret profile, dan memutus sesi aktif
     * agar limit rate-limit baru langsung diterapkan RouterOS.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function updatePppoeProfile(Router $router, LayananPelanggan $layanan, bool $kickActive = true, ?Client $client = null): array
    {
        try {
            $username = trim((string) $layanan->ppp_username);
            $paket = $layanan->paketLayanan;
            $profil = $paket?->profilBandwidth;

            if (! $paket || ! $profil || empty($profil->nama_bandwidth)) {
                throw new MikrotikException("Layanan {$username} tidak memiliki paket layanan atau profil bandwidth yang valid di UNMS.");
            }

            $client = $client ?? $this->getClient($router);
            $profileName = $this->profilTujuan($router, $layanan, $client);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                // Jika secret belum ada di RouterOS, jalankan createOrUpdate
                return $this->createOrUpdatePppoeSecret($router, $layanan, $client);
            }

            $secretId = $existing[0]['.id'];
            $setQuery = (new Query('/ppp/secret/set'))
                ->equal('.id', $secretId)
                ->equal('profile', $profileName);

            $result = $client->query($setQuery)->read();

            if (isset($result['after']['message'])) {
                throw new MikrotikException($result['after']['message']);
            }

            // Putus sesi aktif jika user sedang online agar paket baru langsung aktif
            $kicked = false;
            if ($kickActive) {
                $kicked = $this->removeActiveSession($router, $username, $client);
            }

            $layanan->update([
                'terprovisi_pada' => Carbon::now(),
                'provisioning_status' => ProvisioningStatus::Success,
                'last_provisioning_error' => null,
            ]);

            return [
                'status' => 'success',
                'action' => 'profile_updated',
                'username' => $username,
                'profile' => $profileName,
                'session_kicked' => $kicked,
            ];
        } catch (Throwable $e) {
            $layanan->update([
                'provisioning_status' => ProvisioningStatus::Failed,
                'last_provisioning_error' => $e->getMessage(),
            ]);

            throw new MikrotikException(
                "Gagal update profil PPPoE {$layanan->ppp_username} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Buka isolir: kembalikan secret ke profile paketnya lalu putus sesi agar pelanggan tersambung ulang
     * ke pool paket. Secret yang belum ada diprovisi penuh.
     *
     * @throws MikrotikException
     */
    public function bukaIsolirPppoeSecret(Router $router, LayananPelanggan $layanan, ?Client $client = null): bool
    {
        return $this->pindahProfileLalu(
            $router,
            $layanan,
            fn (Client $client): string => $this->ensurePaketProfile($this->routerPaketLayanan($router, $layanan), $client),
            'membuka isolir',
            $client,
        );
    }

    /**
     * Isolir: pindahkan secret ke profile `ISOLIR` lalu putus sesi agar pelanggan tersambung ulang ke pool
     * isolir. Secret tidak pernah di-disable (CONTEXT.md "Isolir").
     *
     * @throws MikrotikException
     */
    public function isolirPppoeSecret(Router $router, LayananPelanggan $layanan, ?Client $client = null): bool
    {
        return $this->pindahProfileLalu(
            $router,
            $layanan,
            fn (Client $client): string => $this->ensureIsolirProfile($router, $client),
            'mengisolir',
            $client,
        );
    }

    /**
     * @param  \Closure(Client): string  $profile
     *
     * @throws MikrotikException
     */
    private function pindahProfileLalu(Router $router, LayananPelanggan $layanan, \Closure $profile, string $aksi, ?Client $client): bool
    {
        $username = (string) $layanan->ppp_username;

        try {
            $username = $this->requireUsername($layanan->ppp_username);
            $client = $client ?? $this->getClient($router);

            $existing = $client->query((new Query('/ppp/secret/print'))->where('name', $username))->read();
            $this->assertNoTrap($existing, 'pembacaan PPP Secret');

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                $this->createOrUpdatePppoeSecret($router, $layanan, $client);
            } else {
                $setQuery = (new Query('/ppp/secret/set'))
                    ->equal('.id', $existing[0]['.id'])
                    ->equal('profile', $profile($client))
                    ->equal('disabled', 'no');
                $this->assertNoTrap($client->query($setQuery)->read(), "{$aksi} secret {$username}");
            }

            $this->removeActiveSession($router, $username, $client);

            return true;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal {$aksi} PPPoE {$username} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Username kosong/null berbahaya: `where('name', null)` menjadi `?name` yang cocok dengan SEMUA secret.
     *
     * @throws MikrotikException
     */
    private function requireUsername(mixed $username): string
    {
        $username = trim((string) $username);

        if ($username === '') {
            throw new MikrotikException('Username PPPoE kosong; operasi dibatalkan karena query tanpa nama mencocokkan semua secret.');
        }

        return $username;
    }

    /**
     * Sesi yang sudah berakhir di antara print dan remove ("no such item") bukan kegagalan.
     */
    private function assertSessionRemoved(mixed $response, string $username): void
    {
        $message = is_array($response) ? ($response['after']['message'] ?? null) : null;

        if ($message !== null && ! str_contains(strtolower((string) $message), 'no such item')) {
            throw new MikrotikException("RouterOS menolak pemutusan sesi {$username}: {$message}");
        }
    }

    /**
     * Putus sesi aktif PPPoE pelanggan di RouterOS.
     *
     * @throws MikrotikException
     */
    public function removeActiveSession(Router $router, string $username, ?Client $client = null): bool
    {
        try {
            $client = $client ?? $this->getClient($router);
            $findQuery = (new Query('/ppp/active/print'))->where('name', $username);
            $actives = $client->query($findQuery)->read();

            if (! empty($actives)) {
                foreach ($actives as $active) {
                    if (isset($active['.id'])) {
                        $removeQuery = (new Query('/ppp/active/remove'))->equal('.id', $active['.id']);
                        $this->assertSessionRemoved($client->query($removeQuery)->read(), $username);
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal memutus sesi aktif user {$username} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Putus seluruh sesi aktif PPPoE pada router.
     *
     * @return int Jumlah sesi yang berhasil diputus
     *
     * @throws MikrotikException
     */
    public function removeAllActiveSessions(Router $router, ?Client $client = null): int
    {
        try {
            $client = $client ?? $this->getClient($router);
            $actives = $client->query(new Query('/ppp/active/print'))->read();

            if (empty($actives)) {
                return 0;
            }

            $count = 0;
            foreach ($actives as $active) {
                if (isset($active['.id'])) {
                    $removeQuery = (new Query('/ppp/active/remove'))->equal('.id', $active['.id']);
                    $client->query($removeQuery)->read();
                    $count++;
                }
            }

            return $count;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal memutuskan seluruh sesi aktif PPPoE pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Hapus PPPoE Secret dari RouterOS berdasarkan nama username atau model LayananPelanggan.
     *
     * Guard level service (bukan hanya UI): wajib membawa $context (siapa + alasan), menolak username
     * kosong, dan menolak secret berkomentar MANUAL:/NOC:/SYSTEM:/WHITELIST: atau akun sistem
     * (dicatat `Dilewati`). Setiap penghapusan/penolakan dicatat di MikrotikJobLog beserta snapshot
     * secret (tanpa password). Nama yang terdaftar di billing dianggap milik billing.
     *
     * Idempoten: false jika secret tidak ditemukan atau ditolak karena dilindungi, true jika dihapus.
     *
     * @throws MikrotikException
     */
    public function deletePppoeSecret(Router $router, LayananPelanggan|string $target, PppDeletionContext $context, ?Client $client = null): bool
    {
        try {
            $username = $this->requireUsername($target instanceof LayananPelanggan ? $target->ppp_username : $target);
            $client = $client ?? $this->getClient($router);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();
            $this->assertNoTrap($existing, 'pembacaan PPP Secret');

            $layananId = $target instanceof LayananPelanggan ? $target->id : null;

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                if ($target instanceof LayananPelanggan) {
                    $target->update([
                        'terprovisi_pada' => null,
                        'provisioning_status' => ProvisioningStatus::Pending,
                        'last_provisioning_error' => null,
                    ]);
                }

                return false;
            }

            foreach ($existing as $item) {
                if (self::isProtectedSecret($item)) {
                    $this->recordDeletion($router, $username, $context, 'protected_skipped', $this->snapshotSecret($item), $layananId);

                    return false;
                }
            }

            $snapshot = $this->snapshotSecret($existing[0]);

            foreach ($existing as $item) {
                if (isset($item['.id'])) {
                    $removeQuery = (new Query('/ppp/secret/remove'))->equal('.id', $item['.id']);
                    $this->assertNoTrap($client->query($removeQuery)->read(), "penghapusan secret {$username}");
                }
            }

            // Putus sesi aktif jika ada
            $this->removeActiveSession($router, $username, $client);

            $this->recordDeletion($router, $username, $context, 'deleted', $snapshot, $layananId);

            if ($target instanceof LayananPelanggan) {
                $target->update([
                    'terprovisi_pada' => null,
                    'provisioning_status' => ProvisioningStatus::Pending,
                    'last_provisioning_error' => null,
                ]);
            }

            return true;
        } catch (Throwable $e) {
            $username = $target instanceof LayananPelanggan ? $target->ppp_username : $target;

            throw new MikrotikException(
                "Gagal menghapus PPPoE secret '{$username}' pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bersihkan jejak sebuah IP Pool dari router: `/ip/pool`, queue `POOL-{nama}`, dan PPP Profile `*@{nama}`.
     *
     * Kepemilikan dari nama (ADR-0063): pool bernama itu, queue `POOL-{nama}`, dan profile lama `{bandwidth billing}@{nama}`. Objek yang masih dipakai
     * RouterOS (mis. profile dirujuk secret manual) menolak dihapus dan dilaporkan di `skipped`, bukan dipaksa.
     * Dipanggil hanya untuk pool yang sudah tidak dipakai layanan (dihapus / dipindah router).
     *
     * @return array{removed: array<int, string>, skipped: array<int, string>}
     *
     * @throws MikrotikException
     */
    public function removeIpPool(Router $router, string $poolName, PppDeletionContext $context, ?Client $client = null): array
    {
        try {
            $client = $client ?? $this->getClient($router);
            $removed = [];
            $skipped = [];

            $targets = [];
            $profileLama = array_flip(ProfilBandwidth::query()->pluck('nama_bandwidth')->map(fn ($bw) => "{$bw}@{$poolName}")->all());
            foreach ($client->query((new Query('/ppp/profile/print')))->read() as $profile) {
                if (isset($profile['.id'], $profile['name'], $profileLama[$profile['name']])) {
                    $targets[] = ['/ppp/profile/remove', $profile['.id'], "profile {$profile['name']}"];
                }
            }
            foreach ($client->query((new Query('/queue/simple/print'))->where('name', "POOL-{$poolName}"))->read() as $queue) {
                if (isset($queue['.id'])) {
                    $targets[] = ['/queue/simple/remove', $queue['.id'], "queue POOL-{$poolName}"];
                }
            }
            foreach ($client->query((new Query('/ip/pool/print'))->where('name', $poolName))->read() as $pool) {
                if (isset($pool['.id'])) {
                    $targets[] = ['/ip/pool/remove', $pool['.id'], "pool {$poolName}"];
                }
            }

            foreach ($targets as [$endpoint, $id, $label]) {
                try {
                    $this->assertNoTrap($client->query((new Query($endpoint))->equal('.id', $id))->read(), "penghapusan {$label}");
                    $removed[] = $label;
                } catch (Throwable $e) {
                    $skipped[] = "{$label}: {$e->getMessage()}";
                }
            }

            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::DeleteIpPool,
                'status' => $skipped === [] ? MikrotikJobStatus::Success : MikrotikJobStatus::Dilewati,
                'attempt_count' => 1,
                'payload' => array_merge($context->toArray(), ['pool' => $poolName, 'removed' => $removed, 'skipped' => $skipped]),
                'finished_at' => Carbon::now(),
            ]);

            return ['removed' => $removed, 'skipped' => $skipped];
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal membersihkan IP Pool {$poolName} dari router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Sinkronisasikan konfigurasi IP Pool & Simple Queue ke RouterOS.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function syncIpPool(Router $router, IpPool $ipPool, ?Client $client = null): array
    {
        $cacheKey = "{$router->id}:{$ipPool->id}";

        if (isset($this->syncedPoolCache[$cacheKey])) {
            return [
                'status' => 'success',
                'pool_name' => $ipPool->nama_pool,
                'queue_name' => "POOL-{$ipPool->nama_pool}",
            ];
        }

        try {
            $client = $client ?? $this->getClient($router);
            $poolName = $ipPool->nama_pool;
            $poolRanges = "{$ipPool->rentang_ip_awal}-{$ipPool->rentang_ip_akhir}";
            $queueName = "POOL-{$poolName}";
            $queueTarget = "{$ipPool->ip_network}/{$ipPool->cidr}";
            $queuePriority = "{$ipPool->priority_tx}/{$ipPool->priority_rx}";

            // 1. Sinkronisasi /ip/pool
            $findPoolQuery = (new Query('/ip/pool/print'))->where('name', $poolName);
            $existingPool = $client->query($findPoolQuery)->read();

            if (! empty($existingPool) && isset($existingPool[0]['.id'])) {
                $poolId = $existingPool[0]['.id'];
                // next-pool=none: rantai pool (ADR-0060) dibongkar; tiap profile memakai tepat pool Router Paket-nya (ADR-0063).
                $setPoolQuery = (new Query('/ip/pool/set'))
                    ->equal('.id', $poolId)
                    ->equal('ranges', $poolRanges)
                    ->equal('next-pool', 'none')
                    ->equal('comment', '');
                $this->assertNoTrap($client->query($setPoolQuery)->read(), "pembaruan pool {$poolName}");

                if (count($existingPool) > 1) {
                    for ($i = 1; $i < count($existingPool); $i++) {
                        if (isset($existingPool[$i]['.id'])) {
                            try {
                                $client->query((new Query('/ip/pool/remove'))->equal('.id', $existingPool[$i]['.id']))->read();
                            } catch (Throwable $e) {
                                Log::warning('Gagal menghapus duplikat IP pool, dilewati.', [
                                    'router_id' => $router->id,
                                    'pool_name' => $poolName,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            } else {
                $addPoolQuery = (new Query('/ip/pool/add'))
                    ->equal('name', $poolName)
                    ->equal('ranges', $poolRanges);
                $this->assertNoTrap($client->query($addPoolQuery)->read(), "pembuatan pool {$poolName}");
            }

            // 2. Sinkronisasi /queue/simple
            $findQueueQuery = (new Query('/queue/simple/print'))->where('name', $queueName);
            $existingQueue = $client->query($findQueueQuery)->read();

            if (! empty($existingQueue) && isset($existingQueue[0]['.id'])) {
                $queueId = $existingQueue[0]['.id'];
                $setQueueQuery = (new Query('/queue/simple/set'))
                    ->equal('.id', $queueId)
                    ->equal('target', $queueTarget)
                    ->equal('priority', $queuePriority)
                    ->equal('comment', '');
                $this->assertNoTrap($client->query($setQueueQuery)->read(), "pembaruan queue {$queueName}");
            } else {
                $addQueueQuery = (new Query('/queue/simple/add'))
                    ->equal('name', $queueName)
                    ->equal('target', $queueTarget)
                    ->equal('priority', $queuePriority);
                $this->assertNoTrap($client->query($addQueueQuery)->read(), "pembuatan queue {$queueName}");
            }

            $ipPool->update([
                'applied_to_router_at' => Carbon::now(),
                'sync_status' => 'success',
                'last_sync_error' => null,
            ]);

            $this->syncedPoolCache[$cacheKey] = true;

            return [
                'status' => 'success',
                'pool_name' => $poolName,
                'queue_name' => $queueName,
            ];
        } catch (Throwable $e) {
            $ipPool->update([
                'sync_status' => 'failed',
                'last_sync_error' => $e->getMessage(),
            ]);

            throw new MikrotikException(
                "Gagal sinkronisasi IP Pool {$ipPool->nama_pool} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Auto-Recover & Reconcile Seluruh Profil Bandwidth (PPP Profile) & PPP Secret di RouterOS dari UNMS.
     *
     * Pipeline Pemulihan Otomatis:
     * 1. Auto-Recover Profil Bandwidth (PPP Profile) jika hilang atau konfigurasi berubah di RouterOS.
     * 2. Auto-Recover PPP Secret jika hilang/terhapus, salah profil, salah IP statis, atau salah password.
     * 3. Sinkronisasikan status isolir (disabled).
     * 4. Hapus entri duplikat di RouterOS.
     *
     * Jika $dryRun = true, tidak ada perubahan nyata yang dikirim ke router untuk poin 2 & 3 —
     * setiap drift yang terdeteksi hanya dicatat ke log & 'dry_run_changes' (lihat Sprint A5:
     * docs/plan/fix_race_condition_mikrotik/sprint-a5-audit-drift-detection.md). Sinkronisasi IP Pool,
     * profil bandwidth (poin 1), dan penghapusan duplikat (poin 4) di luar scope dry-run ini dan tetap berjalan normal.
     *
     * @return array{
     *     profiles: array{total: int, synced: int, errors: array<string>},
     *     secrets: array{
     *         total_checked: int,
     *         recovered: int,
     *         already_synced: int,
     *         disabled: int,
     *         duplicates_removed: int,
     *         terminated_removed: int,
     *         errors: array<string>
     *     },
     *     total_checked: int,
     *     recovered: int,
     *     already_synced: int,
     *     disabled: int,
     *     duplicates_removed: int,
     *     terminated_removed: int,
     *     delete_cap_exceeded: bool,
     *     delete_skipped_over_cap: array<int, string>,
     *     unmanaged_duplicates: array<int, string>,
     *     errors: array<string>,
     *     dry_run: bool,
     *     dry_run_changes: array<int, array<string, mixed>>,
     *     profile_lama_dihapus: array<int, string>
     * }
     *
     * @throws MikrotikException
     */
    public function autoRecoverPppSecrets(Router $router, ?Client $client = null, bool $dryRun = false): array
    {
        $this->resetDeleteBudget();
        $client = $client ?? $this->getClient($router);

        // 1. Auto-recover seluruh IP Pool milik router ini di RouterOS
        foreach ($router->ipPools as $pool) {
            try {
                $this->syncIpPool($router, $pool, $client);
            } catch (Throwable $e) {
                Log::warning('Sinkronisasi IP pool gagal saat auto-recover, dilewati.', [
                    'router_id' => $router->id,
                    'ip_pool_id' => $pool->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Auto-recover seluruh profil bandwidth (PPP Profile) di RouterOS
        $profileStats = [
            'total' => 0,
            'synced' => 0,
            'errors' => [],
        ];

        try {
            $profileStats = $this->syncPaketProfiles($router, $client);
        } catch (Throwable $e) {
            $profileStats['errors'][] = $e->getMessage();
        }

        // 3. Ambil seluruh PPP secrets yang ada di RouterOS saat ini
        try {
            $remoteSecretsRaw = $client->query(new Query('/ppp/secret/print'))->read();
        } catch (Throwable $e) {
            // Transient retry: coba sekali lagi dengan fresh reconnect jika gagal karena socket timeout / glitch
            try {
                $client = $this->getClient($router, 15);
                $remoteSecretsRaw = $client->query(new Query('/ppp/secret/print'))->read();
            } catch (Throwable $retryEx) {
                throw new MikrotikException("Gagal membaca data PPP Secret dari router {$router->nama_router}: {$retryEx->getMessage()}", (int) $retryEx->getCode(), $retryEx);
            }
        }

        // Respons galat (mis. policy API user tanpa `read`) bukan "daftar kosong": tanpa ini seluruh layanan
        // terbaca "secret hilang" dan dipulihkan massal.
        $this->assertNoTrap($remoteSecretsRaw, "pembacaan PPP Secret di router {$router->nama_router}");

        // Petakan username => data secret di RouterOS; duplikat dikumpulkan dulu dan diproses setelah
        // daftar layanan terdaftar diketahui (hanya duplikat milik billing yang boleh dihapus).
        $remoteSecrets = [];
        $duplicateEntries = [];

        foreach ($remoteSecretsRaw as $s) {
            $name = $s['name'] ?? null;
            if (! $name) {
                continue;
            }

            if (! isset($remoteSecrets[$name])) {
                $remoteSecrets[$name] = $s;
            } else {
                $duplicateEntries[] = $s;
            }
        }

        // 3. Ambil seluruh layanan pelanggan UNMS yang terhubung ke router ini
        $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'router.ipPools', 'ipPubliks'])
            ->where('router_id', $router->id)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend, StatusLayanan::Proses])
            ->get();
        $routerPakets = $router->routerPakets()->with('paketLayanan')->get()->keyBy('paket_layanan_id');

        $recovered = 0;
        $alreadySynced = 0;
        $disabledCount = 0;
        $duplicatesRemoved = 0;
        $terminatedRemoved = 0;
        $capExceeded = false;
        $skippedOverCap = [];
        $unmanagedDuplicates = [];
        $errors = [];
        $dryRunChanges = [];

        foreach ($layanans as $layanan) {
            $username = trim((string) $layanan->ppp_username);
            if (empty($username)) {
                continue;
            }

            $routerPaket = $routerPakets[$layanan->paket_layanan_id] ?? null;
            if (! $routerPaket) {
                $errors[] = "Paket layanan {$username} belum didaftarkan ke router {$router->nama_router}; secret tidak disinkronkan.";

                continue;
            }

            // Layanan Suspend diharapkan di profile ISOLIR (CONTEXT.md "Isolir").
            $expectedProfile = $layanan->status === StatusLayanan::Suspend ? Router::PROFILE_ISOLIR : $routerPaket->namaProfile();
            $expectedPassword = (string) $layanan->ppp_password_terenkripsi;
            $expectedRemoteAddress = (string) ($layanan->resolveRemoteAddress() ?? '');
            $expectedLocalAddress = (string) ($layanan->resolveLocalAddress() ?? '');
            $remote = $remoteSecrets[$username] ?? null;

            // Periksa apakah secret hilang, profile berbeda, password berbeda, remote-address, atau local-address tidak sesuai
            $needsRecovery = false;
            $driftReason = null;

            if ($remote === null) {
                // Secret hilang dari RouterOS
                $needsRecovery = true;
                $driftReason = 'secret_missing';
            } elseif (($remote['profile'] ?? '') !== $expectedProfile) {
                // Profile di RouterOS tidak sesuai
                $needsRecovery = true;
                $driftReason = 'profile_mismatch';
            } elseif (($remote['remote-address'] ?? '') !== $expectedRemoteAddress) {
                // Remote-address di RouterOS tidak sesuai dengan alokasi UNMS (IP Pool / IP Statis)
                $needsRecovery = true;
                $driftReason = 'remote_address_mismatch';
            } elseif (($remote['local-address'] ?? '') !== $expectedLocalAddress) {
                // Local-address di RouterOS tidak sesuai dengan gateway UNMS
                $needsRecovery = true;
                $driftReason = 'local_address_mismatch';
            } elseif (in_array($remote['disabled'] ?? 'false', ['true', 'yes'], true)) {
                // Secret ter-disable (cara isolir lama): isolir kini lewat profile, secret selalu aktif
                $needsRecovery = true;
                $driftReason = 'disabled_lama';
            } elseif (str_starts_with((string) ($remote['comment'] ?? ''), 'UNMS:')) {
                // Komentar `UNMS:` lama dikosongkan: billing tidak lagi memberi komentar (ADR-0063)
                $needsRecovery = true;
                $driftReason = 'comment_lama';
            } elseif (isset($remote['password']) && $remote['password'] !== $expectedPassword) {
                // Password di RouterOS tidak sesuai dengan UNMS
                $needsRecovery = true;
                $driftReason = 'password_mismatch';
            }

            if ($needsRecovery) {
                if ($dryRun) {
                    // DRY RUN: hanya catat & log, jangan benar-benar disable/enable/rewrite secret di router.
                    // Sprint A5 (docs/plan/fix_race_condition_mikrotik/sprint-a5-audit-drift-detection.md):
                    // output ini dipakai sebagai data nyata Langkah 0 (audit format remote-address/local-address).
                    $change = [
                        'username' => $username,
                        'action' => 'would_recover',
                        'reason' => $driftReason,
                        'from_router' => $this->redactSecretForLog($remote),
                        'expected' => [
                            'profile' => $expectedProfile,
                            'remote-address' => $expectedRemoteAddress,
                            'local-address' => $expectedLocalAddress,
                        ],
                    ];
                    $dryRunChanges[] = $change;

                    Log::info('DRY RUN: akan memperbaiki drift pada PPP secret', array_merge(
                        ['router_id' => $router->id],
                        $change
                    ));

                    $recovered++;

                    continue;
                }

                // Status dapat berubah sejak daftar layanan dibaca (mis. baru bayar): jangan menimpa dengan data basi.
                if ($this->statusLayananBerubah($layanan)) {
                    continue;
                }

                try {
                    $this->createOrUpdatePppoeSecret($router, $layanan, $client);

                    // Profile baru berlaku saat sesi tersambung ulang.
                    if ($driftReason === 'profile_mismatch') {
                        $this->removeActiveSession($router, $username, $client);
                    }

                    if ($layanan->status === StatusLayanan::Suspend) {
                        $disabledCount++;
                    }

                    $recovered++;
                } catch (Throwable $e) {
                    $errors[] = "Gagal recover {$username}: {$e->getMessage()}";
                }
            } else {
                $alreadySynced++;
            }
        }

        // Layanan Berhenti (input admin/NOC): secret yang masih tersisa di router dihapus, dengan guard/batas/audit
        // yang sama seperti penghapusan lain. Isolir (Suspend) tidak pernah sampai ke sini.
        $berhentiLayanans = LayananPelanggan::query()
            ->where('router_id', $router->id)
            ->where('status', StatusLayanan::Berhenti)
            ->whereNotNull('ppp_username')
            ->get(['id', 'ppp_username']);

        foreach ($berhentiLayanans as $layanan) {
            $username = trim((string) $layanan->ppp_username);
            $remote = $remoteSecrets[$username] ?? null;

            if ($username === '' || $remote === null || self::isProtectedSecret($remote)) {
                continue;
            }

            if ($dryRun) {
                $dryRunChanges[] = [
                    'username' => $username,
                    'action' => 'would_remove',
                    'reason' => 'layanan_berhenti',
                    'from_router' => $this->redactSecretForLog($remote),
                ];
                $terminatedRemoved++;

                continue;
            }

            if (! $this->consumeDeleteBudget()) {
                $capExceeded = true;
                $skippedOverCap[] = $username;

                continue;
            }

            try {
                $this->deletePppoeSecret(
                    $router,
                    $username,
                    PppDeletionContext::system('reconcile', "Rekonsiliasi: layanan #{$layanan->id} berstatus Berhenti tetapi secret masih ada di router"),
                    $client,
                );
                $terminatedRemoved++;
            } catch (Throwable $e) {
                $errors[] = "Gagal menghapus secret layanan Berhenti {$username}: {$e->getMessage()}";
            }
        }

        // Duplikat nama di router: hanya yang terdaftar di billing dan tidak dilindungi yang dihapus.
        if ($duplicateEntries !== []) {
            $registeredLookup = array_flip(
                LayananPelanggan::query()->where('router_id', $router->id)->pluck('ppp_username')->filter()->map(fn ($u) => trim((string) $u))->all()
            );

            foreach ($duplicateEntries as $dup) {
                $name = $dup['name'];

                if (! isset($registeredLookup[$name]) || ! isset($dup['.id']) || self::isProtectedSecret($dup)) {
                    $unmanagedDuplicates[] = $name;

                    continue;
                }

                if ($dryRun) {
                    $dryRunChanges[] = ['username' => $name, 'action' => 'would_remove_duplicate', 'reason' => 'duplicate_name', 'from_router' => $this->redactSecretForLog($dup)];
                    $duplicatesRemoved++;

                    continue;
                }

                if (! $this->consumeDeleteBudget()) {
                    $capExceeded = true;
                    $skippedOverCap[] = $name;

                    continue;
                }

                try {
                    $this->assertNoTrap($client->query((new Query('/ppp/secret/remove'))->equal('.id', $dup['.id']))->read(), "penghapusan duplikat {$name}");
                    $this->recordDeletion($router, $name, PppDeletionContext::system('reconcile', 'Rekonsiliasi: entri duplikat bernama sama untuk username terdaftar di billing'), 'deleted', $this->snapshotSecret($dup));
                    $duplicatesRemoved++;
                } catch (Throwable $e) {
                    $errors[] = "Gagal menghapus duplikat {$name}: {$e->getMessage()}";
                }
            }
        }

        if ($capExceeded) {
            $errors[] = 'Batas penghapusan '.config('mikrotik.max_deletes_per_run').' secret per eksekusi tercapai; '.count($skippedOverCap).' kandidat dilewati: '.implode(', ', $skippedOverCap);
        }

        // Setelah secret pindah ke profile paket, profile lama yang tak terpakai dibereskan (ADR-0063).
        $profileLama = $dryRun ? ['removed' => [], 'errors' => []] : $this->hapusProfileLama($router, $client);
        $errors = array_merge($errors, $profileLama['errors']);

        return [
            'profiles' => $profileStats,
            'profile_lama_dihapus' => $profileLama['removed'],
            'secrets' => [
                'total_checked' => $layanans->count(),
                'recovered' => $recovered,
                'already_synced' => $alreadySynced,
                'disabled' => $disabledCount,
                'duplicates_removed' => $duplicatesRemoved,
                'terminated_removed' => $terminatedRemoved,
                'errors' => $errors,
            ],
            'total_checked' => $layanans->count(),
            'recovered' => $recovered,
            'already_synced' => $alreadySynced,
            'disabled' => $disabledCount,
            'duplicates_removed' => $duplicatesRemoved,
            'terminated_removed' => $terminatedRemoved,
            'delete_cap_exceeded' => $capExceeded,
            'delete_skipped_over_cap' => $skippedOverCap,
            'unmanaged_duplicates' => $unmanagedDuplicates,
            'errors' => array_merge($profileStats['errors'], $errors),
            'dry_run' => $dryRun,
            'dry_run_changes' => $dryRunChanges,
        ];
    }

    /**
     * Hapus PPP Profile format lama (`{nama_bandwidth}` dan `{nama_bandwidth}@{nama_pool}`, ADR-0051/0060) yang
     * namanya cocok dengan data billing, bukan profile paket router ini, dan tidak dipakai secret mana pun.
     * Profile yang masih dipakai dibiarkan dan akan dicoba lagi pada rekonsiliasi berikutnya.
     *
     * @return array{removed: array<int, string>, errors: array<int, string>}
     */
    private function hapusProfileLama(Router $router, Client $client): array
    {
        $removed = [];
        $errors = [];

        try {
            $kandidat = $this->kandidatProfileLama($router);
            $namaTerpakai = $this->namaProfileTerpakai($router, $client);

            foreach ($client->query(new Query('/ppp/profile/print'))->read() as $profile) {
                $nama = $profile['name'] ?? '';

                if (! isset($profile['.id'], $kandidat[$nama]) || isset($namaTerpakai[$nama])) {
                    continue;
                }

                try {
                    $this->assertNoTrap($client->query((new Query('/ppp/profile/remove'))->equal('.id', $profile['.id']))->read(), "hapus profile lama {$nama}");
                    $removed[] = $nama;
                } catch (Throwable $e) {
                    $errors[] = "Gagal menghapus profile lama {$nama}: {$e->getMessage()}";
                }
            }
        } catch (Throwable $e) {
            $errors[] = "Gagal membaca profile lama di router {$router->nama_router}: {$e->getMessage()}";
        }

        return ['removed' => $removed, 'errors' => $errors];
    }

    /**
     * Nama profile format lama (`{nama_bandwidth}` dan `{nama_bandwidth}@{nama_pool}`, ADR-0051/0060)
     * yang mungkin masih ada di router ini.
     *
     * @return array<string, true>
     */
    private function kandidatProfileLama(Router $router): array
    {
        $namaBandwidth = ProfilBandwidth::query()->pluck('nama_bandwidth')->filter()->all();
        $namaPool = $router->ipPools()->pluck('nama_pool')->all();

        $kandidat = [];
        foreach ($namaBandwidth as $bandwidth) {
            $kandidat[$bandwidth] = true;
            foreach ($namaPool as $pool) {
                $kandidat["{$bandwidth}@{$pool}"] = true;
            }
        }

        return $kandidat;
    }

    /**
     * Nama profile yang masih dipakai: profile paket router ini, atau masih direferensikan secret di RouterOS.
     *
     * @return array<string, true>
     */
    private function namaProfileTerpakai(Router $router, Client $client): array
    {
        $profilePaket = array_flip($router->routerPakets()->with('paketLayanan')->get()->map(fn (RouterPaket $rp) => $rp->namaProfile())->all());
        $dipakai = array_flip(array_filter(array_column($client->query(new Query('/ppp/secret/print'))->read(), 'profile')));

        return $profilePaket + $dipakai;
    }

    /**
     * Apakah status layanan di DB sudah berbeda dari snapshot yang dipegang rekonsiliasi (atau layanan sudah dihapus).
     */
    private function statusLayananBerubah(LayananPelanggan $layanan): bool
    {
        return LayananPelanggan::query()->whereKey($layanan->id)->first(['status'])?->status !== $layanan->status;
    }

    /**
     * Redact field password dari data secret RouterOS sebelum ditulis ke log (dry-run audit).
     *
     * @param  array<string, mixed>|null  $secret
     * @return array<string, mixed>|null
     */
    private function redactSecretForLog(?array $secret): ?array
    {
        if ($secret === null) {
            return null;
        }

        if (array_key_exists('password', $secret)) {
            $secret['password'] = '[REDACTED]';
        }

        return $secret;
    }

    /**
     * Sinkronisasikan PPP Profile seluruh Router Paket milik router ini, plus profile ISOLIR bila router punya
     * IP Pool Isolir (ADR-0063). Galat per paket
     * dikumpulkan, tidak menghentikan paket lain.
     *
     * @return array{total: int, synced: int, errors: array<string>}
     */
    public function syncPaketProfiles(Router $router, ?Client $client = null): array
    {
        $routerPakets = $router->routerPakets()->with(['router', 'ipPool', 'paketLayanan.profilBandwidth'])->get();

        return $this->syncRouterPaketProfiles($router, $routerPakets, $router->ip_pool_isolir_id !== null, $client);
    }

    /**
     * Sinkronisasikan hanya PPP Profile paket yang memakai IP Pool ini, plus profile ISOLIR bila pool ini
     * adalah IP Pool Isolir router (ADR-0063). Dipakai saat cuma satu pool berubah (SyncIpPoolToRouterJob)
     * agar router dengan banyak paket tidak perlu me-resync seluruh profilnya untuk satu perubahan pool.
     *
     * @return array{total: int, synced: int, errors: array<string>}
     */
    public function syncPaketProfilesUsingPool(Router $router, IpPool $ipPool, ?Client $client = null): array
    {
        $routerPakets = $router->routerPakets()
            ->where('ip_pool_id', $ipPool->id)
            ->with(['router', 'ipPool', 'paketLayanan.profilBandwidth'])
            ->get();

        return $this->syncRouterPaketProfiles($router, $routerPakets, $router->ip_pool_isolir_id === $ipPool->id, $client);
    }

    /**
     * @param  Collection<int, RouterPaket>  $routerPakets
     * @return array{total: int, synced: int, errors: array<string>}
     */
    private function syncRouterPaketProfiles(Router $router, Collection $routerPakets, bool $sertakanIsolir, ?Client $client): array
    {
        $synced = 0;
        $errors = [];

        foreach ($routerPakets as $routerPaket) {
            try {
                $this->ensurePaketProfile($routerPaket, $client);
                $synced++;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Profile ISOLIR disiapkan lebih dulu agar isolir tidak menunggu dibuat (CONTEXT.md "Isolir").
        if ($sertakanIsolir) {
            try {
                $this->ensureIsolirProfile($router, $client);
                $synced++;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'total' => $routerPakets->count() + (int) $sertakanIsolir,
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Laporkan PPP Secret di router yang namanya tidak cocok dengan username layanan mana pun di router itu
     * (Orphaned Secret). Hanya audit: tanpa komentar penanda, orphan tidak bisa dibedakan dari secret manual
     * NOC, jadi sistem tidak pernah menghapusnya (ADR-0063).
     *
     * @return array{total_checked: int, orphans_count: int, orphans: array<string>, errors: array<string>}
     *
     * @throws MikrotikException
     */
    public function auditOrphanedPppSecrets(Router $router, ?Client $client = null): array
    {
        try {
            $remoteSecrets = $this->readRemoteSecretsWithRetry($router, $client ?? $this->getClient($router));
            $orphans = $this->orphanSecretNames($router, $remoteSecrets);

            return [
                'total_checked' => count($remoteSecrets),
                'orphans_count' => count($orphans),
                'orphans' => $orphans,
                'errors' => [],
            ];
        } catch (Throwable $e) {
            throw new MikrotikException("Gagal mengaudit orphaned secret di router {$router->nama_router}: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRemoteSecretsWithRetry(Router $router, Client $client): array
    {
        try {
            $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
        } catch (Throwable) {
            $client = $this->getClient($router, 15);
            $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
        }

        $this->assertNoTrap($remoteSecrets, 'pembacaan PPP Secret');

        return $remoteSecrets;
    }

    /**
     * @param  array<int, array<string, mixed>>  $remoteSecrets
     * @return array<int, string>
     */
    private function orphanSecretNames(Router $router, array $remoteSecrets): array
    {
        $terdaftar = array_flip(LayananPelanggan::query()
            ->where('router_id', $router->id)
            ->pluck('ppp_username')
            ->filter()
            ->map(fn ($u) => trim((string) $u))
            ->all());

        return collect($remoteSecrets)
            ->pluck('name')
            ->filter(fn ($name) => filled($name) && ! isset($terdaftar[$name]) && ! in_array(strtolower((string) $name), self::SYSTEM_PROTECTED_USERS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Eksekusi Pipeline Lengkap Provisi & Sinkronisasi Otomatis Router MikroTik.
     * Sesuai ADR 0018:
     * 1. Test Connection & System Resources
     * 2. Sinkronisasi IP Pools & Queues
     * 3. Sinkronisasi PPP Profiles (binary bps)
     * 4. Sinkronisasi PPP Secrets (Layanan Pelanggan & Static IPs)
     * 5. Audit Orphaned Secrets (tanpa penghapusan)
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function provisionRouterFull(Router $router, bool $force = false): array
    {
        try {
            $client = null;
            try {
                $client = $this->getClient($router);
            } catch (Throwable $e) {
                Log::warning('Koneksi router gagal, provisi dilanjutkan tanpa client.', [
                    'router_id' => $router->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 1. Health & Resource Check
            $resourceResult = $this->testConnection($router, 6, $client);

            // 2. Sinkronisasi IP Pool milik router ini
            $ipPools = $router->ipPools;
            $poolSynced = 0;
            $poolErrors = [];
            foreach ($ipPools as $pool) {
                try {
                    $this->syncIpPool($router, $pool, $client);
                    $poolSynced++;
                } catch (Throwable $e) {
                    $poolErrors[] = "Pool {$pool->nama_pool}: {$e->getMessage()}";
                }
            }

            $poolResult = [
                'total' => $ipPools->count(),
                'synced' => $poolSynced,
                'errors' => $poolErrors,
            ];

            // 3. Sinkronisasi Seluruh Profil Bandwidth (binary bps)
            $profileResult = $this->syncPaketProfiles($router, $client);

            // 4. Sinkronisasi PPP Secrets & Status Layanan Pelanggan
            if ($force) {
                $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'router.ipPools', 'ipPubliks'])
                    ->where('router_id', $router->id)
                    ->get();

                $secretSynced = 0;
                $secretErrors = [];

                foreach ($layanans as $layanan) {
                    try {
                        $this->createOrUpdatePppoeSecret($router, $layanan, $client);
                        $secretSynced++;
                    } catch (Throwable $e) {
                        $secretErrors[] = "Secret {$layanan->ppp_username}: {$e->getMessage()}";
                    }
                }

                $secretResult = [
                    'total_checked' => $layanans->count(),
                    'recovered' => $secretSynced,
                    'already_synced' => 0,
                    'disabled' => $layanans->where('status', StatusLayanan::Suspend)->count(),
                    'errors' => $secretErrors,
                ];
            } else {
                $secretResult = $this->autoRecoverPppSecrets($router, $client);
            }

            // 5. Audit Orphaned Secrets (hanya laporan, ADR-0063)
            $orphanResult = $this->auditOrphanedPppSecrets($router, $client);

            // Update last_sync_at pada router
            $router->update([
                'last_sync_at' => Carbon::now(),
            ]);

            $finalPayload = [
                'resources' => $resourceResult,
                'ip_pools' => $poolResult,
                'profiles' => $profileResult,
                'secrets' => $secretResult,
                'orphans' => $orphanResult,
                'force' => $force,
            ];

            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::ProvisionRouter,
                'status' => MikrotikJobStatus::Success,
                'attempt_count' => 1,
                'payload' => $finalPayload,
                'finished_at' => Carbon::now(),
            ]);

            return [
                'status' => 'success',
                'router_id' => $router->id,
                'nama_router' => $router->nama_router,
                'details' => $finalPayload,
            ];
        } catch (Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::ProvisionRouter,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            throw new MikrotikException(
                "Provisi penuh gagal pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Ambil status realtime PPP Secret & sesi aktif untuk suatu akun username di router.
     *
     * Hasil di-cache singkat (lihat config('mikrotik.status_cache_ttl')) supaya beberapa
     * admin yang membuka halaman pelanggan yang sama dalam waktu berdekatan tidak membuka
     * koneksi live baru ke RouterOS untuk masing-masing request. Gunakan refreshPppStatus()
     * untuk memaksa fetch ulang yang melewati cache.
     *
     * @return array{
     *     is_connected: bool,
     *     status_label: string,
     *     profile: ?string,
     *     service: ?string,
     *     ip_address: ?string,
     *     local_address: ?string,
     *     uptime: ?string,
     *     caller_id: ?string,
     *     last_logged_out: ?string,
     *     is_disabled: bool,
     *     router_online: bool,
     *     error_message: ?string,
     * }
     */
    public function getPppStatus(Router $router, string $username): array
    {
        return Cache::remember(
            $this->pppStatusCacheKey($router, $username),
            now()->addSeconds(config('mikrotik.status_cache_ttl', 20)),
            fn () => $this->fetchLivePppStatus($router, $username)
        );
    }

    /**
     * Paksa fetch ulang status realtime PPP, melewati cache dari getPppStatus().
     *
     * @return array{
     *     is_connected: bool,
     *     status_label: string,
     *     profile: ?string,
     *     service: ?string,
     *     ip_address: ?string,
     *     local_address: ?string,
     *     uptime: ?string,
     *     caller_id: ?string,
     *     last_logged_out: ?string,
     *     is_disabled: bool,
     *     router_online: bool,
     *     error_message: ?string,
     * }
     */
    public function refreshPppStatus(Router $router, string $username): array
    {
        Cache::forget($this->pppStatusCacheKey($router, $username));

        return $this->getPppStatus($router, $username);
    }

    /**
     * Pemakaian IP Pool live dari `/ip/pool/used` -- satu query per router, dikelompokkan per nama pool.
     * RouterOS yang mengalokasikan alamat (lihat CONTEXT.md "Alamat Sesi PPP"), jadi inilah satu-satunya
     * sumber kapasitas pool. Tidak pernah throw: null berarti router tidak terjangkau / tidak diketahui.
     *
     * @return array<string, int>|null nama pool => jumlah alamat terpakai
     */
    public function getPoolUsage(Router $router): ?array
    {
        return Cache::remember(
            "pool-usage:{$router->id}",
            now()->addSeconds(config('mikrotik.status_cache_ttl', 20)),
            function () use ($router): ?array {
                try {
                    $client = $this->getClient($router, config('mikrotik.status_timeout', 3));
                    $used = [];

                    foreach ($client->query(new Query('/ip/pool/used/print'))->read() as $row) {
                        if (! empty($row['pool'])) {
                            $used[$row['pool']] = ($used[$row['pool']] ?? 0) + 1;
                        }
                    }

                    return $used;
                } catch (Throwable $e) {
                    Log::warning('Gagal membaca pemakaian IP pool.', [
                        'router_id' => $router->id,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            }
        );
    }

    /**
     * Sidik sesi PPP aktif per username dari satu kali `/ppp/active/print`, untuk Pemantauan Sesi PPP.
     * Sidik berubah saat sesi diganti (reconnect) atau alamat/caller-id berubah; uptime sengaja tidak ikut.
     * Tidak pernah throw: null berarti router tidak terjangkau.
     *
     * @return array<string, string>|null ppp username => sidik sesi
     */
    public function getSidikSesiPppAktif(Router $router): ?array
    {
        try {
            $client = $this->getClient($router, config('mikrotik.status_timeout', 3));
            $sesi = [];

            foreach ($client->query(new Query('/ppp/active/print'))->read() as $row) {
                if (! empty($row['name'])) {
                    $sesi[$row['name']] = implode('|', [$row['.id'] ?? '', $row['address'] ?? '', $row['caller-id'] ?? '']);
                }
            }

            return $sesi;
        } catch (Throwable $e) {
            Log::warning('Gagal membaca sesi PPP aktif.', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function pppStatusCacheKey(Router $router, string $username): string
    {
        return "ppp-status:{$router->id}:{$username}";
    }

    /**
     * Ambil status realtime PPP Secret & sesi aktif dari RouterOS secara live (tanpa cache).
     *
     * @return array{
     *     is_connected: bool,
     *     status_label: string,
     *     profile: ?string,
     *     service: ?string,
     *     ip_address: ?string,
     *     local_address: ?string,
     *     uptime: ?string,
     *     caller_id: ?string,
     *     last_logged_out: ?string,
     *     is_disabled: bool,
     *     router_online: bool,
     *     error_message: ?string,
     * }
     */
    private function fetchLivePppStatus(Router $router, string $username): array
    {
        try {
            $client = $this->getClient($router, config('mikrotik.status_timeout', 3));

            // 1. Ambil data Secret
            $secretQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $secretData = $client->query($secretQuery)->read();
            $secret = ! empty($secretData) && isset($secretData[0]) ? $secretData[0] : null;

            // 2. Ambil data Active Session
            $activeQuery = (new Query('/ppp/active/print'))->where('name', $username);
            $activeData = $client->query($activeQuery)->read();
            $active = ! empty($activeData) && isset($activeData[0]) ? $activeData[0] : null;

            $isConnected = $active !== null;
            $isDisabled = $secret ? (($secret['disabled'] ?? 'false') === 'true') : false;

            $ipAddress = null;
            if ($active && ! empty($active['address'])) {
                $ipAddress = $active['address'];
            } elseif ($secret && ! empty($secret['remote-address'])) {
                $ipAddress = $secret['remote-address'];
            }

            $lastLoggedOut = $secret['last-logged-out'] ?? null;
            if ($lastLoggedOut === 'jan/01/1970 00:00:00') {
                $lastLoggedOut = 'Belum ada sesi';
            }

            return [
                'is_connected' => $isConnected,
                'status_label' => $isConnected ? 'Connected' : 'Disconnected',
                'profile' => $active['profile'] ?? $secret['profile'] ?? null,
                'service' => $active['service'] ?? $secret['service'] ?? 'pppoe',
                'ip_address' => $ipAddress,
                'local_address' => $secret['local-address'] ?? null,
                'uptime' => $active['uptime'] ?? null,
                'caller_id' => $active['caller-id'] ?? ($secret['caller-id'] ?? null),
                'last_logged_out' => $lastLoggedOut,
                'is_disabled' => $isDisabled,
                'router_online' => true,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'is_connected' => false,
                'status_label' => 'Unknown',
                'profile' => null,
                'service' => null,
                'ip_address' => null,
                'local_address' => null,
                'uptime' => null,
                'caller_id' => null,
                'last_logged_out' => null,
                'is_disabled' => false,
                'router_online' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
