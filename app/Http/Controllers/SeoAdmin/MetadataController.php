<?php

namespace App\Http\Controllers\SeoAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SeoAdmin\SaveMetadataRequest;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoMedia;
use App\Services\Seo\Admin\SeoPublisher;
use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\SeoMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class MetadataController extends Controller
{
    public function index(
        Request $request,
        SeoStorage $storage,
        SeoPublisher $publisher,
        SeoAdminPages $pages
    ) {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'indexing' => ['nullable', 'in:indexable,noindex'],
            'status' => ['nullable', 'in:draft,published,default'],
            'sitemap' => ['nullable', 'in:included,excluded'],
            'per_page' => ['nullable', 'in:10,25,50,100'],
        ]);

        $state = $storage->state();
        $snapshot = $publisher->snapshot($state);
        $inventory = $pages->resolver($snapshot)->inventory();
        $query = mb_strtolower($input['q'] ?? '');

        // One grouped query for workflow badges, not one query per page.
        $latest = DB::table('seo_revisions')
            ->whereIn(
                'id',
                DB::table('seo_revisions')
                    ->selectRaw('MAX(id)')
                    ->groupBy('page_id')
            )
            ->get(['page_id', 'kind'])
            ->keyBy('page_id');
        $published = array_fill_keys(
            array_map(
                fn ($override) => $override->pageId,
                array_filter($snapshot->overrides, fn ($override) => $override->published)
            ),
            true
        );
        $statuses = [];
        $summary = [
            'total' => count($inventory),
            'indexable' => 0,
            'sitemap' => 0,
            'draft' => 0,
            'published' => 0,
        ];

        foreach ($inventory as $page) {
            $statuses[$page->id] = [
                'draft' => ($latest[$page->id]->kind ?? null) === 'draft',
                'published' => isset($published[$page->id]),
            ];
            $summary['indexable'] += (int) $page->indexable();
            $summary['sitemap'] += (int) ($page->sitemap && $page->indexable());
            $summary['draft'] += (int) $statuses[$page->id]['draft'];
            $summary['published'] += (int) $statuses[$page->id]['published'];
        }

        $filtered = array_values(array_filter(
            $inventory,
            function ($page) use ($query, $input, $statuses) {
                $status = $statuses[$page->id];

                return (
                    $query === ''
                    || str_contains(
                        mb_strtolower($page->id.' '.$page->title.' '.$page->canonical),
                        $query
                    )
                )
                    && (match ($input['indexing'] ?? '') {
                        'indexable' => $page->indexable(),
                        'noindex' => ! $page->indexable(),
                        default => true,
                    })
                    && (match ($input['sitemap'] ?? '') {
                        'included' => $page->sitemap && $page->indexable(),
                        'excluded' => ! ($page->sitemap && $page->indexable()),
                        default => true,
                    })
                    && (match ($input['status'] ?? '') {
                        'draft' => $status['draft'],
                        'published' => $status['published'],
                        'default' => ! $status['draft'] && ! $status['published'],
                        default => true,
                    });
            }
        ));

        $perPage = (int) ($input['per_page'] ?? 10);
        $lastPage = max(1, (int) ceil(count($filtered) / $perPage));
        $number = min((int) ($input['page'] ?? 1), $lastPage);

        return view('admin.seo.index', [
            'pages' => array_slice($filtered, ($number - 1) * $perPage, $perPage),
            'count' => count($filtered),
            'number' => $number,
            'lastPage' => $lastPage,
            'perPage' => $perPage,
            'filters' => $input,
            'statuses' => $statuses,
            'summary' => $summary,
            'query' => $input['q'] ?? '',
            'state' => $state,
            'warnings' => $pages->warnings($snapshot),
            'images' => $snapshot->images,
        ]);
    }

    public function edit(
        string $page,
        SeoStorage $storage,
        SeoPublisher $publisher,
        SeoAdminPages $pages,
        SeoMedia $media
    ) {
        $state = $storage->state();
        $snapshot = $publisher->snapshot($state);
        $current = $pages->page($page, $snapshot);
        $history = DB::table('seo_revisions')
            ->where('page_id', $page)
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $latest = $history->first();
        $draft = $latest?->kind === 'draft' ? $latest : null;
        $fields = $draft ? json_decode(
            $draft->fields,
            true,
            flags: JSON_THROW_ON_ERROR
        ) : $pages->fields($current);
        $review = $draft ? $pages->candidate($snapshot, $page, $fields) : $snapshot;

        return view('admin.seo.edit', [
            'page' => $current,
            'fields' => $fields,
            'draft' => $draft,
            'history' => $history,
            'state' => $state,
            'images' => $media->choices(),
            'canonicals' => $pages->canonicals($page),
            'warnings' => $pages->warnings($review),
        ]);
    }

    public function save(SaveMetadataRequest $request, string $page, SeoPublisher $publisher)
    {
        $publisher->draft(
            $page,
            $request->fields(),
            $request->integer('expected_version'),
            $request->validated('policy_reason'),
            Auth::guard('web')->user()
        );

        return redirect()
            ->route('admin.seo.edit', $page)
            ->with(
                'status',
                'Draft saved. Public metadata has not changed.'
            );
    }

    public function preview(
        SaveMetadataRequest $request,
        string $page,
        SeoStorage $storage,
        SeoPublisher $publisher,
        SeoAdminPages $pages
    ) {
        $snapshot = $pages->candidate($publisher->snapshot($storage->state()), $page, $request->fields());
        $metadata = $pages->page($page, $snapshot);
        $preview = new SeoMetadata(
            id: $metadata->id,
            title: $metadata->title,
            description: $metadata->description,
            canonical: $metadata->canonical,
            robots: 'noindex, nofollow',
            ogType: $metadata->ogType,
            image: $metadata->image,
            ogTitle: $metadata->ogTitle,
            ogDescription: $metadata->ogDescription,
            twitterTitle: $metadata->twitterTitle,
            twitterDescription: $metadata->twitterDescription,
            structuredData: $metadata->structuredData
        );

        return view('admin.seo.preview', [
            'preview' => $preview,
            'page' => $metadata,
            'warnings' => $pages->warnings($snapshot),
        ]);
    }

    public function upload(Request $request, SeoMedia $media)
    {
        $input = $request->validate([
            'image_file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'label' => ['required', 'string', 'max:120', 'not_regex:/[<>\x00-\x1F]/'],
            'approve_image' => ['accepted'],
        ]);

        return $this->mediaOperation(function () use ($input, $media) {
            $media->upload($input['image_file'], $input['label'], Auth::guard('web')->user());

            return redirect()
                ->route('admin.seo.index')
                ->with(
                    'status',
                    'Image approved and safely re-encoded. Select it in a page draft.'
                );
        });
    }

    public function media(string $image)
    {
        $manifest = collect(app(SeoMedia::class)->manifests())
            ->first(fn ($item) => basename($item->path) === $image.'.jpg');
        $file = 'seo/'.$image.'.jpg';
        abort_unless($manifest && Storage::disk('fofo_images')->exists($file), 404);

        return response(Storage::disk('fofo_images')->get($file), 200, ['Content-Type' => 'image/jpeg']);
    }

    public function removeImage(Request $request, string $image, SeoMedia $media)
    {
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'confirm_removal' => ['accepted'],
        ]);

        return $this->mediaOperation(function () use ($image, $input, $media) {
            $media->remove($image, (int) $input['expected_version'], Auth::guard('web')->user());

            return redirect()
                ->route('admin.seo.index')
                ->with(
                    'status',
                    'Unused image removed. Published and historical images are protected.'
                );
        });
    }

    private function mediaOperation(callable $operation)
    {
        try {
            return $operation();
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('SEO image operation failed.', ['exception' => $exception::class]);

            return response()->view(
                'admin.seo.failure',
                [
                    'heading' => 'Image operation paused.',
                    'message' => 'The image operation could not complete. No automatic retry was made. Review storage health before trying again.',
                ],
                503
            );
        }
    }
}
