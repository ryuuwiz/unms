<?php

namespace App\Livewire\PaketLayanan;

use App\Jobs\Mikrotik\HapusPaketProfileJob;
use App\Jobs\Mikrotik\SyncRouterPaketJob;
use App\Models\IpPool;
use App\Models\PaketLayanan;
use App\Models\Router;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detail paket PPPoE dan Router Paket-nya (ADR-0063): router yang boleh menjual paket ini beserta
 * IP Pool yang dipakai profile PPP paket di router itu.
 */
#[Layout('layouts.app')]
#[Title('Detail Paket')]
class Show extends Component
{
    public PaketLayanan $paketLayanan;

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $router_id = null;

    public ?int $ip_pool_id = null;

    public string $deskripsi = '';

    public function mount(PaketLayanan $paketLayanan): void
    {
        $this->authorize('view', $paketLayanan);
        $this->paketLayanan = $paketLayanan;
    }

    public function updatedRouterId(): void
    {
        $this->ip_pool_id = null;
    }

    public function openCreateModal(): void
    {
        $this->authorize('kelolaRouter', $this->paketLayanan);
        $this->reset(['editingId', 'router_id', 'ip_pool_id', 'deskripsi']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('kelolaRouter', $this->paketLayanan);
        $routerPaket = $this->paketLayanan->routerPakets()->findOrFail($id);

        $this->editingId = $routerPaket->id;
        $this->router_id = $routerPaket->router_id;
        $this->ip_pool_id = $routerPaket->ip_pool_id;
        $this->deskripsi = (string) $routerPaket->deskripsi;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->authorize('kelolaRouter', $this->paketLayanan);

        $this->validate([
            'router_id' => [
                'required', 'integer', 'exists:router,id',
                Rule::unique('router_paket', 'router_id')->where('paket_layanan_id', $this->paketLayanan->id)->ignore($this->editingId),
            ],
            'ip_pool_id' => [
                'required', 'integer',
                Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id),
                Rule::unique('router', 'ip_pool_isolir_id'),
            ],
            'deskripsi' => ['nullable', 'string', 'max:255'],
        ], [
            'router_id.required' => 'Router wajib dipilih.',
            'router_id.unique' => 'Router ini sudah terdaftar pada paket ini.',
            'ip_pool_id.required' => 'IP Pool wajib dipilih.',
            'ip_pool_id.exists' => 'IP Pool harus milik router yang dipilih.',
            'ip_pool_id.unique' => 'Pool ini adalah IP Pool Isolir router; pilih pool lain untuk paket.',
        ]);

        $data = ['router_id' => $this->router_id, 'ip_pool_id' => $this->ip_pool_id, 'deskripsi' => trim($this->deskripsi) ?: null];

        if ($this->editingId) {
            $routerPaket = $this->paketLayanan->routerPakets()->findOrFail($this->editingId);

            if ($routerPaket->router_id !== $this->router_id && $routerPaket->masihDipakai()) {
                $this->addError('router_id', 'Router tidak bisa diganti: masih ada layanan memakai paket ini di router tersebut.');

                return;
            }

            $routerPaket->update($data);
        } else {
            $routerPaket = $this->paketLayanan->routerPakets()->create($data);
        }

        SyncRouterPaketJob::dispatch($routerPaket);

        $this->showModal = false;
        Flux::toast(variant: 'success', text: 'Router paket disimpan. Profile PPP sedang diterapkan ke router.');
    }

    public function hapus(int $id): void
    {
        $this->authorize('kelolaRouter', $this->paketLayanan);
        $routerPaket = $this->paketLayanan->routerPakets()->with('router')->findOrFail($id);

        if ($routerPaket->masihDipakai()) {
            Flux::toast(variant: 'danger', text: "Router {$routerPaket->router->nama_router} masih dipakai layanan paket ini dan tidak dapat dihapus.");

            return;
        }

        $namaProfile = $routerPaket->namaProfile();
        $routerId = $routerPaket->router_id;
        $routerPaket->delete();

        HapusPaketProfileJob::dispatch($routerId, $namaProfile);

        Flux::toast(variant: 'success', text: 'Router dihapus dari paket.');
    }

    public function render(): View
    {
        $this->paketLayanan->load(['profilBandwidth', 'routerPakets' => fn ($query) => $query->with(['router', 'ipPool'])]);

        return view('livewire.paket-layanan.show', [
            'routers' => Router::orderBy('nama_router')->get(['id', 'nama_router', 'ip_address']),
            'ipPools' => $this->router_id
                ? IpPool::where('router_id', $this->router_id)->whereDoesntHave('routerIsolir')->orderBy('nama_pool')->get()
                : collect(),
            'bisaKelola' => auth()->user()->can('kelolaRouter', $this->paketLayanan),
        ]);
    }
}
