<?php

namespace App\Services\Mikrotik;

use App\Enums\JenisKoneksi;
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
use App\Support\PppDeletionContext;
use Illuminate\Support\Carbon;
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
     * request/job. ensurePppProfile()/syncIpPool() are correct but redundant
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

            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::TestConnection,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

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
     * Pastikan PPP Profile untuk profil bandwidth tertentu telah tersedia di RouterOS.
     *
     * Dengan $pool, profile bernama "{bandwidth}@{pool}" dan membawa local-address (gateway pool) +
     * remote-address (nama pool) sehingga RouterOS yang mengalokasikan IP (lihat CONTEXT.md
     * "Profile PPP per Pool"). Pool harus sudah ada di `/ip/pool` router sebelum profile ini dipakai.
     *
     * @throws MikrotikException
     */
    public function ensurePppProfile(Router $router, ProfilBandwidth $profil, ?Client $client = null, ?IpPool $pool = null): string
    {
        if (empty($profil->nama_bandwidth)) {
            throw new MikrotikException('Nama profil bandwidth di UNMS kosong. Sinkronisasi PPP Profile dibatalkan.');
        }

        $profileName = $profil->pppProfileName($pool);
        $cacheKey = "{$router->id}:{$profileName}";

        if (isset($this->ensuredProfileCache[$cacheKey])) {
            return $profileName;
        }

        $attributes = $this->pppProfileAttributes($profil, $pool);

        try {
            $client = $client ?? $this->getClient($router);
            $findQuery = (new Query('/ppp/profile/print'))->where('name', $profileName);
            $existing = $client->query($findQuery)->read();

            if (empty($existing) || ! isset($existing[0]['.id'])) {
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

                    $this->ensuredProfileCache[$cacheKey] = true;

                    return $profileName;
                } catch (Throwable $e) {
                    // Race sempit: profile mungkin sudah dibuat proses lain di antara query & create di atas.
                    // Re-query sekali untuk verifikasi state aktual, baru menyerah kalau memang bukan soal duplikat.
                    $existing = $client->query($findQuery)->read();

                    if (empty($existing) || ! isset($existing[0]['.id'])) {
                        throw $e;
                    }

                    Log::warning('Create PPP profile gagal tapi entry ternyata sudah ada (race sempit), melanjutkan ke update.', [
                        'profil_bandwidth_id' => $profil->id,
                        'profile_name' => $profileName,
                        'router_id' => $router->id,
                    ]);
                }
            }

            $setQuery = (new Query('/ppp/profile/set'))->equal('.id', $existing[0]['.id']);
            foreach ($attributes as $key => $value) {
                $setQuery->equal($key, $value);
            }
            $this->assertNoTrap($client->query($setQuery)->read(), "pembaruan PPP Profile {$profileName}");

            // Hapus duplikat profile jika ada lebih dari 1 di RouterOS
            if (count($existing) > 1) {
                for ($i = 1; $i < count($existing); $i++) {
                    if (isset($existing[$i]['.id'])) {
                        try {
                            $client->query((new Query('/ppp/profile/remove'))->equal('.id', $existing[$i]['.id']))->read();
                        } catch (Throwable $e) {
                            // Lanjutkan jika duplikat sekunder sudah terhapus
                        }
                    }
                }
            }

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
     * Atribut PPP Profile yang dikelola UNMS (selain name).
     *
     * @return array<string, string>
     */
    private function pppProfileAttributes(ProfilBandwidth $profil, ?IpPool $pool): array
    {
        $attributes = [
            'rate-limit' => $profil->routerOsRateLimit(),
            'comment' => "UNMS: {$profil->nama_bandwidth} ({$profil->labelKecepatan()})",
        ];

        if ($pool) {
            $attributes['local-address'] = $pool->getGatewayAddress();
            $attributes['remote-address'] = $pool->nama_pool;
        }

        return $attributes;
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

            // 3. Strict Guard: Pastikan IP Pool terdefinisi untuk PPPoE dinamis dan terdaftar pada router yang sama
            if ($layanan->jenis_koneksi === JenisKoneksi::Pppoe) {
                if (! $layanan->ipPool) {
                    throw new MikrotikException("Layanan {$username} dengan jenis koneksi PPPoE wajib memiliki alokasi IP Pool yang valid dari router terkait. Buka Edit layanan dan pilih IP Pool milik router {$router->nama_router}, lalu provisi ulang.");
                }

                if ($layanan->ipPool->router_id !== $router->id) {
                    throw new MikrotikException("Layanan {$username} memiliki IP Pool '{$layanan->ipPool->nama_pool}' yang terdaftar pada router lain, bukan {$router->nama_router}. Perbaiki alokasi IP Pool layanan sebelum provisi.");
                }
            }

            $client = $client ?? $this->getClient($router);

            // 4. Auto-Ensure IP Pool di RouterOS -- harus ada sebelum Profile PPP per Pool merujuknya.
            if ($layanan->ipPool && $layanan->ipPool->router_id === $router->id) {
                $this->syncIpPool($router, $layanan->ipPool, $client);
            }

            // 5. Profile: per pool untuk PPPoE dinamis (RouterOS yang mengalokasikan IP), polos untuk alamat literal.
            $profileName = $this->ensurePppProfile($router, $profil, $client, $layanan->profilePool());

            $pelangganNama = $layanan->pelanggan ? $layanan->pelanggan->nama_depan.' '.$layanan->pelanggan->nama_belakang : 'Pelanggan';
            $comment = "UNMS: {$layanan->site_id} - {$pelangganNama}";

            // 6. local/remote-address hanya untuk alamat literal (IP Publik / IP Statis); null = dinamis, dibiarkan kosong.
            $remoteAddress = $layanan->resolveRemoteAddress();
            $localAddress = $layanan->resolveLocalAddress();

            $isDisabled = ($layanan->status === StatusLayanan::Suspend) ? 'yes' : 'no';

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
                        ->equal('comment', $comment)
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
                    ->equal('comment', $comment)
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
                        // Lanjutkan jika remote-address sudah tidak ada
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
                        // Lanjutkan jika local-address sudah tidak ada
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
                                // Lanjutkan jika duplikat sekunder sudah terhapus
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
            $profileName = $this->ensurePppProfile($router, $profil, $client, $layanan->profilePool());

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
     * Aktifkan (Enable) PPPoE Secret di RouterOS.
     *
     * @throws MikrotikException
     */
    public function enablePppoeSecret(Router $router, LayananPelanggan $layanan, ?Client $client = null): bool
    {
        try {
            $client = $client ?? $this->getClient($router);
            $username = $this->requireUsername($layanan->ppp_username);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();
            $this->assertNoTrap($existing, 'pembacaan PPP Secret');

            if (empty($existing) || ! isset($existing[0]['.id'])) {
                // Jika secret belum ada, lakukan provisi penuh
                $this->createOrUpdatePppoeSecret($router, $layanan, $client);

                return true;
            }

            $secretId = $existing[0]['.id'];
            $enableQuery = (new Query('/ppp/secret/set'))
                ->equal('.id', $secretId)
                ->equal('disabled', 'no');

            $result = $client->query($enableQuery)->read();

            if (isset($result['after']['message'])) {
                throw new MikrotikException($result['after']['message']);
            }

            return true;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal mengaktifkan PPPoE {$layanan->ppp_username} pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Nonaktifkan (Isolir/Disable) PPPoE Secret di RouterOS & putus sesi aktif jika ada.
     *
     * @throws MikrotikException
     */
    public function disablePppoeSecret(Router $router, LayananPelanggan $layanan, bool $disconnectActive = true, ?Client $client = null): bool
    {
        $username = (string) $layanan->ppp_username;

        try {
            $username = $this->requireUsername($layanan->ppp_username);
            $client = $client ?? $this->getClient($router);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();
            $this->assertNoTrap($existing, 'pembacaan PPP Secret');

            if (! empty($existing) && isset($existing[0]['.id'])) {
                $secretId = $existing[0]['.id'];
                $disableQuery = (new Query('/ppp/secret/set'))
                    ->equal('.id', $secretId)
                    ->equal('disabled', 'yes');

                $this->assertNoTrap($client->query($disableQuery)->read(), "penonaktifan secret {$username}");
            }

            if ($disconnectActive) {
                $this->removeActiveSession($router, $username, $client);
            }

            return true;
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal memutuskan sesi aktif PPPoE {$username} pada router {$router->nama_router}: {$e->getMessage()}",
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
     * Hanya objek bertanda UNMS (komentar `UNMS Managed ...` / `UNMS:`) yang disentuh. Objek yang masih dipakai
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
            foreach ($client->query((new Query('/ppp/profile/print')))->read() as $profile) {
                if (isset($profile['.id'], $profile['name']) && str_ends_with($profile['name'], "@{$poolName}") && str_starts_with((string) ($profile['comment'] ?? ''), 'UNMS:')) {
                    $targets[] = ['/ppp/profile/remove', $profile['.id'], "profile {$profile['name']}"];
                }
            }
            foreach ($client->query((new Query('/queue/simple/print'))->where('name', "POOL-{$poolName}"))->read() as $queue) {
                if (isset($queue['.id']) && str_starts_with((string) ($queue['comment'] ?? ''), 'UNMS Managed')) {
                    $targets[] = ['/queue/simple/remove', $queue['.id'], "queue POOL-{$poolName}"];
                }
            }
            foreach ($client->query((new Query('/ip/pool/print'))->where('name', $poolName))->read() as $pool) {
                if (isset($pool['.id']) && str_starts_with((string) ($pool['comment'] ?? ''), 'UNMS Managed')) {
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
                $setPoolQuery = (new Query('/ip/pool/set'))
                    ->equal('.id', $poolId)
                    ->equal('ranges', $poolRanges);
                $this->assertNoTrap($client->query($setPoolQuery)->read(), "pembaruan pool {$poolName}");

                if (count($existingPool) > 1) {
                    for ($i = 1; $i < count($existingPool); $i++) {
                        if (isset($existingPool[$i]['.id'])) {
                            try {
                                $client->query((new Query('/ip/pool/remove'))->equal('.id', $existingPool[$i]['.id']))->read();
                            } catch (Throwable $e) {
                                // Lanjutkan jika duplikat sekunder sudah terhapus
                            }
                        }
                    }
                }
            } else {
                $addPoolQuery = (new Query('/ip/pool/add'))
                    ->equal('name', $poolName)
                    ->equal('ranges', $poolRanges)
                    ->equal('comment', 'UNMS Managed IP Pool');
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
                    ->equal('priority', $queuePriority);
                $this->assertNoTrap($client->query($setQueueQuery)->read(), "pembaruan queue {$queueName}");
            } else {
                $addQueueQuery = (new Query('/queue/simple/add'))
                    ->equal('name', $queueName)
                    ->equal('target', $queueTarget)
                    ->equal('priority', $queuePriority)
                    ->equal('comment', 'UNMS Managed Pool Queue');
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
     *     dry_run_changes: array<int, array<string, mixed>>
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
                // Lanjutkan jika sinkronisasi pool terhambat
            }
        }

        // 2. Auto-recover seluruh profil bandwidth (PPP Profile) di RouterOS
        $profileStats = [
            'total' => 0,
            'synced' => 0,
            'errors' => [],
        ];

        try {
            $profileStats = $this->syncAllBandwidthProfiles($router, $client);
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
        $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'ipPool', 'ipPubliks'])
            ->where('router_id', $router->id)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend, StatusLayanan::Proses])
            ->get();

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

            $profil = $layanan->paketLayanan?->profilBandwidth;
            if (! $profil || empty($profil->nama_bandwidth)) {
                continue; // Lewati jika tidak ada profil bandwidth yang valid di UNMS
            }

            $expectedProfile = $profil->pppProfileName($layanan->profilePool());
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

                    if ($layanan->status === StatusLayanan::Suspend) {
                        $this->disablePppoeSecret($router, $layanan, false, $client);
                        $disabledCount++;
                    }

                    $recovered++;
                } catch (Throwable $e) {
                    $errors[] = "Gagal recover {$username}: {$e->getMessage()}";
                }
            } else {
                // Secret sudah ada di router, pastikan status disabled sesuai dengan status layanan UNMS (misal suspend)
                $isCurrentlyDisabled = ($remote['disabled'] ?? 'false') === 'true' || ($remote['disabled'] ?? 'false') === 'yes';
                $shouldBeDisabled = ($layanan->status === StatusLayanan::Suspend);

                if ($isCurrentlyDisabled !== $shouldBeDisabled) {
                    if ($dryRun) {
                        // DRY RUN: hanya catat & log, jangan benar-benar toggle disabled di router.
                        $change = [
                            'username' => $username,
                            'action' => $shouldBeDisabled ? 'would_disable' : 'would_enable',
                            'reason' => 'disabled_state_mismatch',
                            'from_router' => ['disabled' => $remote['disabled'] ?? null],
                            'expected' => ['disabled' => $shouldBeDisabled],
                        ];
                        $dryRunChanges[] = $change;

                        Log::info('DRY RUN: akan mengubah status disabled PPP secret', array_merge(
                            ['router_id' => $router->id],
                            $change
                        ));

                        $recovered++;

                        continue;
                    }

                    if ($this->statusLayananBerubah($layanan)) {
                        continue;
                    }

                    try {
                        if ($shouldBeDisabled) {
                            $this->disablePppoeSecret($router, $layanan, true, $client);
                            $disabledCount++;
                        } else {
                            $this->enablePppoeSecret($router, $layanan, $client);
                        }
                        $recovered++;
                    } catch (Throwable $e) {
                        $errors[] = "Gagal sinkron status disabled {$username}: {$e->getMessage()}";
                    }
                } else {
                    $alreadySynced++;
                }
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

                if (! isset($registeredLookup[$name]) || ! isset($dup['.id']) || self::isProtectedSecret($dup) || self::isProtectedSecret($remoteSecrets[$name])) {
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

        return [
            'profiles' => $profileStats,
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
     * Sinkronisasikan seluruh profil bandwidth yang ada di UNMS ke RouterOS: profile polos
     * (alamat literal) dan satu Profile PPP per Pool untuk setiap IP Pool router ini.
     *
     * @return array{total: int, synced: int, errors: array<string>}
     *
     * @throws MikrotikException
     */
    public function syncAllBandwidthProfiles(Router $router, ?Client $client = null): array
    {
        $profils = ProfilBandwidth::all();
        $pools = $router->ipPools;
        $synced = 0;
        $errors = [];

        if ($profils->isEmpty()) {
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => [],
            ];
        }

        /** @var array<int, array{ProfilBandwidth, IpPool|null}> $targets */
        $targets = [];
        foreach ($profils as $profil) {
            if (empty($profil->nama_bandwidth)) {
                continue;
            }

            $targets[] = [$profil, null];
            foreach ($pools as $pool) {
                $targets[] = [$profil, $pool];
            }
        }

        try {
            $client = $client ?? $this->getClient($router);

            // 1. Bulk read seluruh PPP profile yang sudah ada di RouterOS
            $existingProfilesRaw = $client->query(new Query('/ppp/profile/print'))->read();

            $existingByName = [];
            foreach ($existingProfilesRaw as $item) {
                $name = $item['name'] ?? null;
                if ($name) {
                    $existingByName[$name][] = $item;
                }
            }

            // 2. Sinkronisasikan profil UNMS ke RouterOS
            foreach ($targets as [$profil, $pool]) {
                $profileName = $profil->pppProfileName($pool);
                $attributes = $this->pppProfileAttributes($profil, $pool);

                try {
                    if (isset($existingByName[$profileName])) {
                        $entries = $existingByName[$profileName];
                        $primary = $entries[0];

                        // Periksa apakah konfigurasi perlu diperbarui
                        $drifted = false;
                        foreach ($attributes as $key => $value) {
                            if (($primary[$key] ?? '') !== $value) {
                                $drifted = true;
                            }
                        }

                        if ($drifted) {
                            $setQuery = (new Query('/ppp/profile/set'))->equal('.id', $primary['.id']);
                            foreach ($attributes as $key => $value) {
                                $setQuery->equal($key, $value);
                            }
                            $this->assertNoTrap($client->query($setQuery)->read(), "pembaruan PPP Profile {$profileName}");
                        }

                        // Bersihkan duplikat profile jika ada lebih dari 1 di RouterOS
                        if (count($entries) > 1) {
                            for ($i = 1; $i < count($entries); $i++) {
                                if (isset($entries[$i]['.id'])) {
                                    try {
                                        $client->query((new Query('/ppp/profile/remove'))->equal('.id', $entries[$i]['.id']))->read();
                                    } catch (Throwable) {
                                        // Lanjutkan jika duplikat sekunder sudah terhapus
                                    }
                                }
                            }
                        }
                    } else {
                        // Tambahkan profil baru ke RouterOS
                        $addQuery = (new Query('/ppp/profile/add'))->equal('name', $profileName);
                        foreach ($attributes as $key => $value) {
                            $addQuery->equal($key, $value);
                        }
                        $res = $client->query($addQuery)->read();
                        if (isset($res['after']['message'])) {
                            throw new MikrotikException($res['after']['message']);
                        }
                    }

                    $synced++;
                } catch (Throwable $e) {
                    $errors[] = "Gagal sinkron profil {$profileName}: {$e->getMessage()}";
                }
            }
        } catch (Throwable $e) {
            // Fallback ke pemanggilan per-profil jika bulk query mengalami kendala
            foreach ($targets as [$profil, $pool]) {
                try {
                    $this->ensurePppProfile($router, $profil, $client, $pool);
                    $synced++;
                } catch (Throwable $pe) {
                    $errors[] = "Gagal sinkron profil {$profil->pppProfileName($pool)}: {$pe->getMessage()}";
                }
            }
        }

        return [
            'total' => count($targets),
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Audit atau bersihkan akun PPP Secret di RouterOS yang tidak terdaftar di UNMS (Orphaned Secrets).
     *
     * Penghapusan hanya untuk secret berkomentar `UNMS:` yang namanya tidak ada di billing dan tidak
     * berkomentar MANUAL:/NOC:/SYSTEM:/WHITELIST: -- pola nama TIDAK lagi dianggap penanda kepemilikan.
     * Penghapusan wajib membawa $context dan dibatasi config('mikrotik.max_deletes_per_run') per eksekusi;
     * kandidat di atas batas dilewati dan dilaporkan (`cap_exceeded`).
     *
     * @return array{
     *     total_checked: int,
     *     orphans_count: int,
     *     orphans: array<string>,
     *     deleted: int,
     *     mode: string,
     *     cap_exceeded: bool,
     *     skipped_over_cap: array<string>,
     *     errors: array<string>
     * }
     *
     * @throws MikrotikException
     */
    public function cleanOrphanedPppSecrets(Router $router, bool $executeDelete = false, ?Client $client = null, ?PppDeletionContext $context = null): array
    {
        if ($executeDelete && $context === null) {
            throw new MikrotikException('Penghapusan orphaned secret wajib menyertakan konteks (actor + alasan).');
        }

        $this->resetDeleteBudget();

        try {
            $client = $client ?? $this->getClient($router);
            try {
                $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
            } catch (Throwable) {
                $client = $this->getClient($router, 15);
                $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
            }

            $this->assertNoTrap($remoteSecrets, 'pembacaan PPP Secret');

            if (empty($remoteSecrets)) {
                return [
                    'total_checked' => 0,
                    'orphans_count' => 0,
                    'orphans' => [],
                    'deleted' => 0,
                    'mode' => $executeDelete ? 'cleaned' : 'audit_only',
                    'cap_exceeded' => false,
                    'skipped_over_cap' => [],
                    'errors' => [],
                ];
            }

            // Ambil semua username PPPoE di UNMS untuk router ini (semua status)
            $validUsernames = LayananPelanggan::query()
                ->where('router_id', $router->id)
                ->pluck('ppp_username')
                ->filter()
                ->map(fn ($u) => trim((string) $u))
                ->toArray();

            $validUsernamesLookup = array_flip($validUsernames);

            $orphans = [];
            $deletedCount = 0;
            $capExceeded = false;
            $skippedOverCap = [];
            $errors = [];

            foreach ($remoteSecrets as $secret) {
                $name = $secret['name'] ?? '';
                $comment = $secret['comment'] ?? '';
                $secretId = $secret['.id'] ?? null;

                // Lindungi akun sistem bawaan dan secret teknisi (MANUAL:/NOC:/SYSTEM:/WHITELIST:)
                if (empty($name) || self::isProtectedSecret($secret)) {
                    continue;
                }

                if (isset($validUsernamesLookup[$name])) {
                    continue;
                }

                $orphanLabel = $name.($comment ? " ({$comment})" : '');
                $orphans[$orphanLabel] = true;

                // Hanya secret berkomentar `UNMS:` yang dapat dihapus; sisanya hanya dilaporkan.
                if (! ($executeDelete && $secretId && str_starts_with((string) $comment, 'UNMS:'))) {
                    continue;
                }

                // Double check real-time database state to prevent race conditions with concurrent registrations on other replicas
                $existsInDb = LayananPelanggan::query()
                    ->where('router_id', $router->id)
                    ->where('ppp_username', $name)
                    ->exists();

                if ($existsInDb) {
                    continue;
                }

                if (! $this->consumeDeleteBudget()) {
                    $capExceeded = true;
                    $skippedOverCap[] = $name;

                    continue;
                }

                try {
                    $this->assertNoTrap($client->query((new Query('/ppp/secret/remove'))->equal('.id', $secretId))->read(), "penghapusan secret {$name}");

                    // Putus sesi aktif jika ada
                    $this->removeActiveSession($router, $name, $client);
                    $this->recordDeletion($router, $name, $context, 'deleted', $this->snapshotSecret($secret));
                    $deletedCount++;
                } catch (Throwable $e) {
                    $errors[] = "Gagal menghapus orphaned secret {$name}: {$e->getMessage()}";
                }
            }

            if ($capExceeded) {
                $errors[] = 'Batas penghapusan '.config('mikrotik.max_deletes_per_run').' secret per eksekusi tercapai; '.count($skippedOverCap).' kandidat dilewati: '.implode(', ', $skippedOverCap);
            }

            return [
                'total_checked' => count($remoteSecrets),
                'orphans_count' => count($orphans),
                'orphans' => array_keys($orphans),
                'deleted' => $deletedCount,
                'mode' => $executeDelete ? 'cleaned' : 'audit_only',
                'cap_exceeded' => $capExceeded,
                'skipped_over_cap' => $skippedOverCap,
                'errors' => $errors,
            ];
        } catch (Throwable $e) {
            throw new MikrotikException(
                "Gagal memeriksa orphaned PPP secrets pada router {$router->nama_router}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Eksekusi Pipeline Lengkap Provisi & Sinkronisasi Otomatis Router MikroTik.
     * Sesuai ADR 0018:
     * 1. Test Connection & System Resources
     * 2. Sinkronisasi IP Pools & Queues
     * 3. Sinkronisasi PPP Profiles (binary bps)
     * 4. Sinkronisasi PPP Secrets (Layanan Pelanggan & Static IPs)
     * 5. Audit / Pembersihan Orphaned Secrets
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function provisionRouterFull(Router $router, bool $force = false, bool $cleanOrphans = false, ?PppDeletionContext $context = null): array
    {
        try {
            $client = null;
            try {
                $client = $this->getClient($router);
            } catch (Throwable) {
                // Fallback for mocked environments or unreachable router
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
            $profileResult = $this->syncAllBandwidthProfiles($router, $client);

            // 4. Sinkronisasi PPP Secrets & Status Layanan Pelanggan
            if ($force) {
                $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'ipPool', 'ipPubliks'])
                    ->where('router_id', $router->id)
                    ->get();

                $secretSynced = 0;
                $secretErrors = [];

                foreach ($layanans as $layanan) {
                    try {
                        $this->createOrUpdatePppoeSecret($router, $layanan, $client);
                        if ($layanan->status === StatusLayanan::Suspend) {
                            $this->disablePppoeSecret($router, $layanan, false, $client);
                        }
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

            // 5. Audit / Pembersihan Orphaned Secrets
            $orphanResult = $this->cleanOrphanedPppSecrets(
                $router,
                $cleanOrphans,
                $client,
                $cleanOrphans ? ($context ?? PppDeletionContext::system('artisan', 'Pembersihan orphaned secret atas permintaan eksplisit (opsi --clean-orphans)')) : null,
            );

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
                'clean_orphans' => $cleanOrphans,
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
                } catch (Throwable) {
                    return null;
                }
            }
        );
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
