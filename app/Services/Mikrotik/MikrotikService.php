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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikrotikService
{
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
     * @throws MikrotikException
     */
    public function ensurePppProfile(Router $router, ProfilBandwidth $profil, ?Client $client = null): string
    {
        if (empty($profil->nama_bandwidth)) {
            throw new MikrotikException('Nama profil bandwidth di UNMS kosong. Sinkronisasi PPP Profile dibatalkan.');
        }

        $profileName = $profil->nama_bandwidth;
        $rateLimit = $profil->routerOsRateLimit();
        $comment = "UNMS: {$profil->nama_bandwidth} ({$profil->labelKecepatan()})";

        try {
            $client = $client ?? $this->getClient($router);
            $findQuery = (new Query('/ppp/profile/print'))->where('name', $profileName);
            $existing = $client->query($findQuery)->read();

            if (! empty($existing) && isset($existing[0]['.id'])) {
                $setQuery = (new Query('/ppp/profile/set'))
                    ->equal('.id', $existing[0]['.id'])
                    ->equal('rate-limit', $rateLimit)
                    ->equal('comment', $comment);
                $client->query($setQuery)->read();

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
            } else {
                $addQuery = (new Query('/ppp/profile/add'))
                    ->equal('name', $profileName)
                    ->equal('rate-limit', $rateLimit)
                    ->equal('comment', $comment);
                $res = $client->query($addQuery)->read();
                if (isset($res['after']['message'])) {
                    throw new MikrotikException($res['after']['message']);
                }
            }

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

            // 2. Strict Guard: Pastikan Paket Layanan & Profil Bandwidth terdefinisi valid di UNMS
            $paket = $layanan->paketLayanan;
            $profil = $paket?->profilBandwidth;

            if (! $paket || ! $profil || empty($profil->nama_bandwidth)) {
                throw new MikrotikException("Layanan {$username} tidak memiliki paket layanan atau profil bandwidth yang valid di UNMS. Provisi dibatalkan.");
            }

            $client = $client ?? $this->getClient($router);
            $profileName = $this->ensurePppProfile($router, $profil, $client);

            $pelangganNama = $layanan->pelanggan ? $layanan->pelanggan->nama_depan.' '.$layanan->pelanggan->nama_belakang : 'Pelanggan';
            $comment = "UNMS: {$layanan->site_id} - {$pelangganNama}";

            // 3. Tentukan local-address (Gateway) dan remote-address (IP Pool / IP Statis)
            $remoteAddress = $layanan->resolveRemoteAddress();
            $localAddress = $layanan->resolveLocalAddress();

            $isDisabled = ($layanan->status === StatusLayanan::Suspend) ? 'yes' : 'no';

            // 4. Cek apakah secret sudah ada di RouterOS
            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();

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
                        if (isset($existing[$i]['.id'])) {
                            try {
                                $removeDupQuery = (new Query('/ppp/secret/remove'))->equal('.id', $existing[$i]['.id']);
                                $client->query($removeDupQuery)->read();
                            } catch (Throwable $e) {
                                // Lanjutkan jika duplikat sekunder sudah terhapus
                            }
                        }
                    }
                }
            } else {
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
                $action = 'created';
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
            $profileName = $this->ensurePppProfile($router, $profil, $client);

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
            $username = $layanan->ppp_username;

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();

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
        $username = $layanan->ppp_username;

        try {
            $client = $client ?? $this->getClient($router);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();

            if (! empty($existing) && isset($existing[0]['.id'])) {
                $secretId = $existing[0]['.id'];
                $disableQuery = (new Query('/ppp/secret/set'))
                    ->equal('.id', $secretId)
                    ->equal('disabled', 'yes');

                $client->query($disableQuery)->read();
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
                        $client->query($removeQuery)->read();
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
     * Operasi ini idempoten: mengembalikan false jika secret tidak ditemukan,
     * true jika berhasil dihapus. Digunakan untuk cleanup username lama
     * sebelum provisioning username baru saat migrasi format ppp_username,
     * atau saat layanan dihapus.
     *
     * @throws MikrotikException
     */
    public function deletePppoeSecret(Router $router, LayananPelanggan|string $target, ?Client $client = null): bool
    {
        try {
            $username = $target instanceof LayananPelanggan ? $target->ppp_username : $target;
            $client = $client ?? $this->getClient($router);

            $findQuery = (new Query('/ppp/secret/print'))->where('name', $username);
            $existing = $client->query($findQuery)->read();

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
                if (isset($item['.id'])) {
                    $removeQuery = (new Query('/ppp/secret/remove'))->equal('.id', $item['.id']);
                    $client->query($removeQuery)->read();
                }
            }

            // Putus sesi aktif jika ada
            $this->removeActiveSession($router, $username, $client);

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
     * Sinkronisasikan konfigurasi IP Pool & Simple Queue ke RouterOS.
     *
     * @return array<string, mixed>
     *
     * @throws MikrotikException
     */
    public function syncIpPool(Router $router, IpPool $ipPool, ?Client $client = null): array
    {
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
                $client->query($setPoolQuery)->read();

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
                $client->query($addPoolQuery)->read();
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
                $client->query($setQueueQuery)->read();
            } else {
                $addQueueQuery = (new Query('/queue/simple/add'))
                    ->equal('name', $queueName)
                    ->equal('target', $queueTarget)
                    ->equal('priority', $queuePriority)
                    ->equal('comment', 'UNMS Managed Pool Queue');
                $client->query($addQueueQuery)->read();
            }

            $ipPool->update([
                'applied_to_router_at' => Carbon::now(),
                'sync_status' => 'success',
                'last_sync_error' => null,
            ]);

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
     * @return array{
     *     profiles: array{total: int, synced: int, errors: array<string>},
     *     secrets: array{
     *         total_checked: int,
     *         recovered: int,
     *         already_synced: int,
     *         disabled: int,
     *         duplicates_removed: int,
     *         errors: array<string>
     *     },
     *     total_checked: int,
     *     recovered: int,
     *     already_synced: int,
     *     disabled: int,
     *     duplicates_removed: int,
     *     errors: array<string>
     * }
     *
     * @throws MikrotikException
     */
    public function autoRecoverPppSecrets(Router $router, ?Client $client = null): array
    {
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

        // Petakan username => data secret di RouterOS dan hapus duplikat jika ditemukan
        $remoteSecrets = [];
        $duplicatesRemoved = 0;

        foreach ($remoteSecretsRaw as $s) {
            $name = $s['name'] ?? null;
            if (! $name) {
                continue;
            }

            if (! isset($remoteSecrets[$name])) {
                $remoteSecrets[$name] = $s;
            } else {
                // Secret duplikat di RouterOS: hapus entri duplikat tambahan
                if (isset($s['.id'])) {
                    try {
                        $client->query((new Query('/ppp/secret/remove'))->equal('.id', $s['.id']))->read();
                        $duplicatesRemoved++;
                    } catch (Throwable $e) {
                        // Lanjutkan jika duplikat sudah terhapus
                    }
                }
            }
        }

        // 3. Ambil seluruh layanan pelanggan UNMS yang terhubung ke router ini
        $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'ipPool'])
            ->where('router_id', $router->id)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend, StatusLayanan::Proses])
            ->get();

        $recovered = 0;
        $alreadySynced = 0;
        $disabledCount = 0;
        $errors = [];

        foreach ($layanans as $layanan) {
            $username = trim((string) $layanan->ppp_username);
            if (empty($username)) {
                continue;
            }

            $profil = $layanan->paketLayanan?->profilBandwidth;
            if (! $profil || empty($profil->nama_bandwidth)) {
                continue; // Lewati jika tidak ada profil bandwidth yang valid di UNMS
            }

            $expectedProfile = $profil->nama_bandwidth;
            $expectedPassword = (string) $layanan->ppp_password_terenkripsi;
            $expectedRemoteAddress = (string) ($layanan->resolveRemoteAddress() ?? '');
            $expectedLocalAddress = (string) ($layanan->resolveLocalAddress() ?? '');
            $remote = $remoteSecrets[$username] ?? null;

            // Periksa apakah secret hilang, profile berbeda, password berbeda, remote-address, atau local-address tidak sesuai
            $needsRecovery = false;

            if ($remote === null) {
                // Secret hilang dari RouterOS
                $needsRecovery = true;
            } elseif (($remote['profile'] ?? '') !== $expectedProfile) {
                // Profile di RouterOS tidak sesuai
                $needsRecovery = true;
            } elseif (($remote['remote-address'] ?? '') !== $expectedRemoteAddress) {
                // Remote-address di RouterOS tidak sesuai dengan alokasi UNMS (IP Pool / IP Statis)
                $needsRecovery = true;
            } elseif (($remote['local-address'] ?? '') !== $expectedLocalAddress) {
                // Local-address di RouterOS tidak sesuai dengan gateway UNMS
                $needsRecovery = true;
            } elseif (isset($remote['password']) && $remote['password'] !== $expectedPassword) {
                // Password di RouterOS tidak sesuai dengan UNMS
                $needsRecovery = true;
            }

            if ($needsRecovery) {
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

        return [
            'profiles' => $profileStats,
            'secrets' => [
                'total_checked' => $layanans->count(),
                'recovered' => $recovered,
                'already_synced' => $alreadySynced,
                'disabled' => $disabledCount,
                'duplicates_removed' => $duplicatesRemoved,
                'errors' => $errors,
            ],
            'total_checked' => $layanans->count(),
            'recovered' => $recovered,
            'already_synced' => $alreadySynced,
            'disabled' => $disabledCount,
            'duplicates_removed' => $duplicatesRemoved,
            'errors' => array_merge($profileStats['errors'], $errors),
        ];
    }

    /**
     * Sinkronisasikan seluruh profil bandwidth yang ada di UNMS ke RouterOS.
     *
     * @return array{total: int, synced: int, errors: array<string>}
     *
     * @throws MikrotikException
     */
    public function syncAllBandwidthProfiles(Router $router, ?Client $client = null): array
    {
        $profils = ProfilBandwidth::all();
        $synced = 0;
        $errors = [];

        if ($profils->isEmpty()) {
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => [],
            ];
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
            foreach ($profils as $profil) {
                if (empty($profil->nama_bandwidth)) {
                    continue;
                }

                $profileName = $profil->nama_bandwidth;
                $rateLimit = $profil->routerOsRateLimit();
                $comment = "UNMS: {$profil->nama_bandwidth} ({$profil->labelKecepatan()})";

                try {
                    if (isset($existingByName[$profileName])) {
                        $entries = $existingByName[$profileName];
                        $primary = $entries[0];

                        // Periksa apakah konfigurasi perlu diperbarui
                        $currentRate = $primary['rate-limit'] ?? '';
                        $currentComment = $primary['comment'] ?? '';

                        if ($currentRate !== $rateLimit || $currentComment !== $comment) {
                            $setQuery = (new Query('/ppp/profile/set'))
                                ->equal('.id', $primary['.id'])
                                ->equal('rate-limit', $rateLimit)
                                ->equal('comment', $comment);
                            $client->query($setQuery)->read();
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
                        $addQuery = (new Query('/ppp/profile/add'))
                            ->equal('name', $profileName)
                            ->equal('rate-limit', $rateLimit)
                            ->equal('comment', $comment);
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
            foreach ($profils as $profil) {
                try {
                    $this->ensurePppProfile($router, $profil, $client);
                    $synced++;
                } catch (Throwable $pe) {
                    $errors[] = "Gagal sinkron profil {$profil->nama_bandwidth}: {$pe->getMessage()}";
                }
            }
        }

        return [
            'total' => $profils->count(),
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Audit atau bersihkan akun PPP Secret di RouterOS yang tidak terdaftar di UNMS (Orphaned Secrets).
     * Sesuai ADR 0018 & Kebijakan Whitelist: Akun teknisi (MANUAL:, NOC:, SYSTEM:, admin) dilindungi.
     *
     * @return array{
     *     total_checked: int,
     *     orphans_count: int,
     *     orphans: array<string>,
     *     deleted: int,
     *     mode: string,
     *     errors: array<string>
     * }
     *
     * @throws MikrotikException
     */
    public function cleanOrphanedPppSecrets(Router $router, bool $executeDelete = false, ?Client $client = null): array
    {
        try {
            $client = $client ?? $this->getClient($router);
            try {
                $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
            } catch (Throwable) {
                $client = $this->getClient($router, 15);
                $remoteSecrets = $client->query(new Query('/ppp/secret/print'))->read();
            }

            if (empty($remoteSecrets)) {
                return [
                    'total_checked' => 0,
                    'orphans_count' => 0,
                    'orphans' => [],
                    'deleted' => 0,
                    'mode' => $executeDelete ? 'cleaned' : 'audit_only',
                    'errors' => [],
                ];
            }

            // Ambil semua username PPPoE aktif/valid di UNMS untuk router ini
            $validUsernames = LayananPelanggan::query()
                ->where('router_id', $router->id)
                ->pluck('ppp_username')
                ->filter()
                ->map(fn ($u) => trim((string) $u))
                ->toArray();

            $validUsernamesLookup = array_flip($validUsernames);

            $orphans = [];
            $deletedCount = 0;
            $errors = [];

            $systemProtectedUsers = ['admin', 'api', 'support', 'guest', 'default-encryption'];

            foreach ($remoteSecrets as $secret) {
                $name = $secret['name'] ?? '';
                $comment = $secret['comment'] ?? '';
                $secretId = $secret['.id'] ?? null;

                if (empty($name) || in_array(strtolower($name), $systemProtectedUsers, true)) {
                    continue; // Lindungi akun sistem bawaan MikroTik
                }

                // Lindungi akun dengan tag komentar manajemen teknisi NOC/MANUAL/SYSTEM
                if (preg_match('/^(MANUAL:|NOC:|SYSTEM:|WHITELIST:)/i', $comment)) {
                    continue;
                }

                // Jika nama secret tidak ada dalam database UNMS router ini
                if (! isset($validUsernamesLookup[$name])) {
                    $isUnmsTagged = str_starts_with($comment, 'UNMS:') || preg_match('/^[a-zA-Z0-9]+_[0-9]{5}$/', $name);

                    // Catat sebagai orphaned secret unik
                    $orphanLabel = $name.($comment ? " ({$comment})" : '');
                    $orphans[$orphanLabel] = true;

                    if ($executeDelete && $secretId && $isUnmsTagged) {
                        try {
                            $removeQuery = (new Query('/ppp/secret/remove'))->equal('.id', $secretId);
                            $client->query($removeQuery)->read();

                            // Putus sesi aktif jika ada
                            $this->removeActiveSession($router, $name, $client);
                            $deletedCount++;
                        } catch (Throwable $e) {
                            $errors[] = "Gagal menghapus orphaned secret {$name}: {$e->getMessage()}";
                        }
                    }
                }
            }

            return [
                'total_checked' => count($remoteSecrets),
                'orphans_count' => count($orphans),
                'orphans' => array_keys($orphans),
                'deleted' => $deletedCount,
                'mode' => $executeDelete ? 'cleaned' : 'audit_only',
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
    public function provisionRouterFull(Router $router, bool $force = false, bool $cleanOrphans = false): array
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
                $layanans = LayananPelanggan::with(['paketLayanan.profilBandwidth', 'pelanggan', 'ipPool'])
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
            $orphanResult = $this->cleanOrphanedPppSecrets($router, $cleanOrphans, $client);

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
        try {
            $client = $this->getClient($router, 3);

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
