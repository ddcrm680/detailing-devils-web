@extends('admin.layouts.app')

@include('admin.seo.partials.scripts')

@section('title', 'SEO overview')
@section('context', 'SEO management')

@section('content')
    @php
        $activeFilterCount = count(array_filter(
            array_intersect_key($filters, array_flip(['status', 'indexing', 'sitemap'])),
            fn ($value) => $value !== null && $value !== '',
        ));
    @endphp
    <div class="page-heading">
        <div>
            <h1>SEO Management</h1>
            <p class="muted">Update how your pages appear in search and when shared.</p>
        </div>
        <span class="version">Publication <strong>{{ $state->version }}</strong></span>
    </div>
    <div class="overview-tabs" data-overview-tabs hidden aria-label="SEO workspace">
        <button type="button" id="pages-overview-tab" data-overview-tab="pages" aria-controls="pages-panel">Pages</button>
        <button type="button" id="images-overview-tab" data-overview-tab="images" aria-controls="image-library">Image Library</button>
        <button type="button" id="review-overview-tab" data-overview-tab="review" aria-controls="seo-review">SEO Review</button>
    </div>
    <section id="pages-panel" data-overview-panel="pages" aria-label="Pages">
        <section class="stats" aria-label="SEO overview">
            @foreach ([
                ['total', 'Pages', 'blue', 'globe2'],
                ['published', 'Published', 'green', 'check-circle'],
                ['draft', 'Pending drafts', 'amber', 'pencil-square'],
                ['indexable', 'Indexable', 'neutral', 'eye'],
            ] as [$key, $label, $color, $icon])
                <div class="stat {{ $color }}">
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    <strong>{{ $summary[$key] }}</strong>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </section>
        <section class="panel inventory" aria-labelledby="inventory-title">
            <div class="section-heading">
                <div>
                    <h2 id="inventory-title">Pages</h2>
                    <p class="muted small">{{ $count }} matching pages</p>
                </div>
                <a class="text-link" href="#image-library">Manage Images &rarr;</a>
            </div>
            <form action="{{ route('admin.seo.index') }}" method="get" class="inventory-filters">
                <div class="field search-field">
                    <label for="q">Search pages</label>
                    <input
                        id="q"
                        name="q"
                        type="search"
                        value="{{ $query }}"
                        maxlength="100"
                        placeholder="Page, title or URL"
                    >
                </div>
                <details class="inventory-filter-controls" data-filter-panel>
                    <summary>
                        <i class="bi bi-sliders" aria-hidden="true"></i> Filters
                        @if ($activeFilterCount)
                            <span class="pill amber">{{ $activeFilterCount }}<span class="filter-count-label"> active</span></span>
                        @endif
                    </summary>
                    <div class="filter-fields">
                        <div class="field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                @foreach ([
                                    '' => 'All statuses',
                                    'draft' => 'Has draft',
                                    'published' => 'Published SEO',
                                    'default' => 'Not yet published',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="indexing">Indexing</label>
                            <select id="indexing" name="indexing">
                                <option value="">All pages</option>
                                <option value="indexable" @selected(($filters['indexing'] ?? '') === 'indexable')>Indexable</option>
                                <option value="noindex" @selected(($filters['indexing'] ?? '') === 'noindex')>Noindex</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="sitemap_filter">Sitemap</label>
                            <select id="sitemap_filter" name="sitemap">
                                <option value="">All pages</option>
                                <option value="included" @selected(($filters['sitemap'] ?? '') === 'included')>Included</option>
                                <option value="excluded" @selected(($filters['sitemap'] ?? '') === 'excluded')>Excluded</option>
                            </select>
                        </div>
                        <div class="field rows-field">
                            <label for="per_page">Rows</label>
                            <select id="per_page" name="per_page">
                                @foreach ([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </details>
                <div class="filter-actions">
                    <button class="button" type="submit" aria-label="Search pages"><span class="search-label-long">Search pages</span><span class="search-label-short" aria-hidden="true">Search</span></button>
                    <a class="text-link" href="{{ route('admin.seo.index') }}">Reset</a>
                </div>
            </form>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Registered SEO pages">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th scope="col">Page</th>
                            <th scope="col">URL</th>
                            <th scope="col">Status</th>
                            <th scope="col">Sitemap</th>
                            <th scope="col">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            @php
                                $pageName = str($page->id)->replace(['.show', '.index', 'legal.', 'studios.'], '')
                                    ->replace(['.', ':'], ' ')->headline()->replace(['Fofo', 'Xclusive'], ['FOFO', 'X-Clusive']);
                            @endphp
                            <tr>
                                <th scope="row">
                                    <a
                                        class="page-title"
                                        href="{{ route('admin.seo.edit', $page->id) }}"
                                        title="{{ $page->title }}"
                                    >{{ $pageName }}</a>
                                </th>
                                <td class="page-url-cell" data-label="URL">
                                    <span class="page-url" title="{{ $page->canonical }}">{{ str($page->canonical)->after(config('seo.origin')) ?: '/' }}</span>
                                </td>
                                <td data-label="Status">
                                    <div class="badges">
                                        @if ($statuses[$page->id]['draft'])
                                            <span class="pill amber" @if ($statuses[$page->id]['published']) title="A draft is pending. The published version remains live." @endif>Draft</span>
                                        @elseif ($statuses[$page->id]['published'])
                                            <span class="pill green">Published</span>
                                        @else
                                            <span class="pill">Default</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Sitemap">
                                    <span class="sitemap-status">{{ $page->sitemap && $page->indexable() ? 'Included' : 'Excluded' }}</span>
                                </td>
                                <td>
                                    <a
                                        class="button secondary small-button edit-button"
                                        href="{{ route('admin.seo.edit', $page->id) }}"
                                        aria-label="Edit SEO for {{ $pageName }}"
                                    ><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">No registered pages match these filters. <a href="{{ route('admin.seo.index') }}">Clear filters</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-bar">
                <span class="small muted">Showing {{ $count ? ($number - 1) * $perPage + 1 : 0 }}&ndash;{{ min($number * $perPage, $count) }} of {{ $count }}</span>
                <nav class="pagination" aria-label="Inventory pagination">
                    @foreach (array_unique([
                        1,
                        ...range(max(1, $number - 2), min($lastPage, $number + 2)),
                        $lastPage,
                    ]) as $target)
                        <a
                            class="page-number {{ $target === $number ? 'current' : '' }}"
                            href="{{ route('admin.seo.index', array_merge($filters, ['page' => $target])) }}"
                            @if ($target === $number)
                                aria-current="page"
                            @endif
                            aria-label="Page {{ $target }}"
                        >{{ $target }}</a>
                    @endforeach
                </nav>
            </div>
        </section>
    </section>
    <section class="panel" id="image-library" data-overview-panel="images" aria-labelledby="images-title" @if ($errors->has('image')) data-library-errors @endif>
        <div class="section-heading">
            <div>
                <h2 id="images-title" tabindex="-1">Image Library</h2>
                <p class="muted small">{{ count($images) }} approved {{ count($images) === 1 ? 'image' : 'images' }}</p>
            </div>
            <button class="button" type="button" data-upload-open hidden><i class="bi bi-upload" aria-hidden="true"></i> Upload Image</button>
        </div>
        <ol class="image-workflow" aria-label="Image workflow">
            <li>Upload</li>
            <li>Approve</li>
            <li>Select for page</li>
        </ol>
        <p class="image-library-return small">Editing a page? Return to its original tab after approval. Save any unsaved changes before refreshing, then select your image under Social Sharing.</p>
        <details class="upload-fallback" data-upload-fallback @if ($errors->hasAny(['label', 'image_file', 'approve_image'])) open @endif>
            <summary>Upload Image</summary>
            <div data-upload-content>
                <h2 id="upload-title">Upload Image</h2>
                <p class="muted small" id="upload-help">JPEG or PNG &middot; Up to 5 MB &middot; 200&ndash;2400 px per side. Ideal: 1200 &times; 630.</p>
                @if ($errors->hasAny(['label', 'image_file', 'approve_image']))
                    <div class="notice error" role="alert" tabindex="-1" data-upload-validation>
                        <strong>Review your image details and select the file again.</strong>
                        <ul>
                            @foreach (['label', 'image_file', 'approve_image'] as $field)
                                @foreach ($errors->get($field) as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form
                    action="{{ route('admin.seo.images.upload') }}#image-library"
                    method="post"
                    enctype="multipart/form-data"
                    class="form-stack"
                    data-upload-form
                >
                    @csrf
                    <div class="field">
                        <label for="label">Image label</label>
                        <input id="label" name="label" required maxlength="120" value="{{ old('label') }}" @if ($errors->has('label')) aria-invalid="true" aria-describedby="label-error" @endif>
                        @error('label')
                            <span class="invalid-feedback" id="label-error" data-error-for="label">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="image_file">Sharing image</label>
                        <input id="image_file" name="image_file" type="file" accept="image/jpeg,image/png" required @if ($errors->has('image_file')) aria-invalid="true" aria-describedby="image-file-error" @endif>
                        @error('image_file')
                            <span class="invalid-feedback" id="image-file-error" data-error-for="image_file">{{ $message }}</span>
                        @enderror
                    </div>
                    <label class="check"><input type="checkbox" name="approve_image" value="1" required @checked(old('approve_image')) @if ($errors->has('approve_image')) aria-invalid="true" aria-describedby="approval-error" @endif> I approve this image for public social sharing.</label>
                    @error('approve_image')
                        <span class="invalid-feedback" id="approval-error" data-error-for="approve_image">{{ $message }}</span>
                    @enderror
                    <div class="dialog-actions">
                        <button class="button secondary" type="button" data-upload-cancel hidden>Cancel</button>
                        <button class="button" type="submit">Upload &amp; approve</button>
                    </div>
                </form>
            </div>
        </details>
        <dialog
            class="upload-dialog"
            data-upload-dialog
            @if ($errors->hasAny(['label', 'image_file', 'approve_image'])) data-upload-errors @endif
            aria-labelledby="upload-title"
            aria-describedby="upload-help"
        ></dialog>
        <section class="approved-images" aria-label="Approved images">
            <p class="muted small">Images used in drafts or past revisions stay available for restoration.</p>
            <div class="media-grid">
                @forelse ($images as $image)
                    @php($imageId = pathinfo($image->path, PATHINFO_FILENAME))
                    <article class="media-card">
                        <img
                            src="{{ route('admin.seo.images.show', $imageId) }}"
                            alt="{{ $image->label }}"
                            width="{{ $image->width }}"
                            height="{{ $image->height }}"
                            class="media-thumbnail"
                            loading="lazy"
                        >
                        <strong>{{ $image->label }}</strong><span class="muted small">{{ $image->width }} &times; {{ $image->height }} &middot; JPEG</span>
                        <form
                            action="{{ route('admin.seo.images.remove', $imageId) }}#image-library"
                            method="post"
                            class="form-stack"
                        >
                            @csrf
                            <input type="hidden" name="expected_version" value="{{ $state->version }}">
                            <label class="check"><input type="checkbox" name="confirm_removal" value="1" required> Remove this unused image.</label>
                            <button class="button secondary small-button" type="submit">Remove unused image</button>
                        </form>
                    </article>
                @empty
                    <p class="empty-state">No approved images yet. Upload and approve an image to make it available in page editors.</p>
                @endforelse
            </div>
        </section>
    </section>
    <section class="panel" id="seo-review" data-overview-panel="review" aria-labelledby="review-title">
        <h2 id="review-title">SEO Review</h2>
        <p class="muted small">Site-wide findings. Suggestions are advisory; issues marked Needs attention require review.</p>
        @if (count($warnings))
            @include('admin.seo.partials.warnings', ['findings' => $warnings, 'showPage' => true])
        @else
            <p class="empty-state">No SEO findings to review.</p>
        @endif
    </section>
@endsection
