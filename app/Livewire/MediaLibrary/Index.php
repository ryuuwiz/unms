<?php

namespace App\Livewire\MediaLibrary;

use App\DTO\Storage\S3HealthCheckResult;
use App\Models\BerkasUmum;
use App\Services\Storage\S3HealthCheckService;
use App\Support\MediaLibraryVisibility;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Layout('layouts.app')]
#[Title('Media Library')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $collectionFilter = '';

    public ?S3HealthCheckResult $healthResult = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public function mount(S3HealthCheckService $service): void
    {
        $this->authorizeLihat();

        $this->healthResult = $service->check();
    }

    public function runCheck(S3HealthCheckService $service): void
    {
        $this->authorizeLihat();

        $this->healthResult = $service->check();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCollectionFilter(): void
    {
        $this->resetPage();
    }

    public function uploadFiles(): void
    {
        abort_unless(auth()->user()->can('media_library.unggah'), 403);

        $this->validate([
            'uploads.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,xlsx,xls,csv,docx|max:20480',
        ]);

        foreach ($this->uploads as $file) {
            BerkasUmum::create(['uploaded_by' => auth()->id()])
                ->addMedia($file->getRealPath())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('berkas');
        }

        $this->uploads = [];
        $this->resetPage();

        Flux::toast(variant: 'success', text: 'Berkas berhasil diunggah.');
    }

    public function deleteMedia(int $id): void
    {
        abort_unless(auth()->user()->can('media_library.hapus'), 403);

        $media = $this->baseQuery()->whereKey($id)->first();

        if (! $media) {
            Flux::toast(variant: 'danger', text: 'Berkas tidak ditemukan atau tidak bisa dihapus dari sini.');

            return;
        }

        $namaBerkas = $media->file_name;
        $media->delete();

        Flux::toast(variant: 'success', text: "Berkas {$namaBerkas} berhasil dihapus.");
    }

    /**
     * @return Builder<Media>
     */
    private function baseQuery(): Builder
    {
        return MediaLibraryVisibility::query();
    }

    private function authorizeLihat(): void
    {
        abort_unless(auth()->user()->can('media_library.lihat'), 403);
    }

    public function render(): View
    {
        $collections = $this->baseQuery()
            ->distinct()
            ->orderBy('collection_name')
            ->pluck('collection_name');

        $media = $this->baseQuery()
            ->when($this->search !== '', fn (Builder $q) => $q->where('file_name', 'like', '%'.$this->search.'%'))
            ->when($this->collectionFilter !== '', fn (Builder $q) => $q->where('collection_name', $this->collectionFilter))
            ->latest('id')
            ->paginate(20);

        $totalCount = Media::count();
        $totalSize = (int) Media::sum('size');

        $perCollection = Media::query()
            ->selectRaw('model_type, collection_name, count(*) as jumlah, sum(size) as total_ukuran')
            ->groupBy('model_type', 'collection_name')
            ->orderByDesc('total_ukuran')
            ->get();

        return view('livewire.media-library.index', [
            'media' => $media,
            'collections' => $collections,
            'totalCount' => $totalCount,
            'totalSize' => $totalSize,
            'perCollection' => $perCollection,
        ]);
    }
}
