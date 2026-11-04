@extends('admin.layouts.app')

@section('title', 'SEO overview')
@section('context', 'SEO management')

@section('content')
    <div class="page-heading">
        <div>
            <h1>SEO overview</h1>
            <p class="muted">Manage search visibility and sharing details for every public page.</p>
        </div>
        <span class="version">Publication <strong>{{ $state->version }}</strong></span>
    </div>
    <section class="stats" aria-label="SEO summary">
        <div class="stat">
            <span>Registered pages</span>
            <strong>{{ $summary['total'] }}</strong>
            <small>Discovered public URLs</small>
        </div>
        <div class="stat">
            <span>Indexable pages</span>
            <strong>{{ $summary['indexable'] }}</strong>
            <small>{{ $summary['sitemap'] }} included in sitemap</small>
        </div>
        <div class="stat">
            <span>Pending drafts</span>
            <strong>{{ $summary['draft'] }}</strong>
            <small>Not yet published</small>
        </div>
        <div class="stat">
            <span>Published SEO</span>
            <strong>{{ $summary['published'] }}</strong>
            <small>Active editorial overrides</small>
        </div>
    </section>
    <section class="panel inventory" aria-labelledby="inventory-title">
        <div class="section-heading">
            <div>
                <h2 id="inventory-title">Page inventory</h2>
                <p class="muted small">{{ $count }} matching pages</p>
            </div>
            <a class="text-link" href="#sharing-assets">Sharing images &darr;</a>
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
            <div class="field">
                <label for="status">Workflow</label>
                <select id="status" name="status">
                    @foreach ([
                        '' => 'All statuses',
                        'draft' => 'Has draft',
                        'published' => 'Published SEO',
                        'default' => 'Default metadata',
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
            <div class="filter-actions">
                <button class="button" type="submit">Search pages</button>
                <a class="text-link" href="{{ route('admin.seo.index') }}">Reset</a>
            </div>
            <div class="field rows-field">
                <label for="per_page">Rows</label>
                <select id="per_page" name="per_page">
                    @foreach ([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Registered SEO pages">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Page / current SEO title</th>
                        <th scope="col">Workflow</th>
                        <th scope="col">Indexing</th>
                        <th scope="col">Sitemap</th>
                        <th scope="col">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <th scope="row">
                                <span class="page-id">{{ $page->id }}</span>
                                <a
                                    class="page-title"
                                    href="{{ route('admin.seo.edit', $page->id) }}"
                                >{{ $page->title }}</a>
                                <span class="muted url">{{ $page->canonical }}</span>
                            </th>
                            <td>
                                <div class="badges">
                                    @if ($statuses[$page->id]['draft'])
                                        <span class="pill amber">Draft</span>
                                    @endif
                                    @if ($statuses[$page->id]['published'])
                                        <span class="pill green">Published</span>
                                    @endif
                                    @if (! $statuses[$page->id]['draft'] && ! $statuses[$page->id]['published'])
                                        <span class="pill">Default</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="pill {{ $page->indexable() ? 'green' : '' }}">{{ $page->robots }}</span>
                            </td>
                            <td>
                                <span class="sitemap-status">{{ $page->sitemap && $page->indexable() ? 'Included' : 'Excluded' }}</span>
                            </td>
                            <td>
                                <a
                                    class="button secondary small-button"
                                    href="{{ route('admin.seo.edit', $page->id) }}"
                                >Edit <span class="sr-only">{{ $page->id }}</span></a>
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
    <details class="panel" id="metadata-findings">
        <summary>Metadata findings <span class="pill">{{ count($warnings) }}</span>
        </summary>
        <p class="muted small">Approved copy is preserved. Review warnings before publishing.</p>
        <ul class="findings">
            @forelse ($warnings as $warning)
                <li>
                    <span class="pill">{{ $warning['level'] }}</span><strong>{{ $warning['page'] }}</strong><span>{{ $warning['message'] }}</span>
                </li>
            @empty
                <li>No metadata findings.</li>
            @endforelse
        </ul>
    </details>
    <details class="panel" id="sharing-assets">
        <summary>Sharing images <span class="pill">{{ count($images) }} approved</span>
        </summary>
        <div class="asset-workspace">
            <section class="form-stack" aria-labelledby="images-title">
                <h2 id="images-title">Add a sharing image</h2>
                <p class="muted small">JPEG or PNG, up to 5 MB; 200&ndash;2400 pixels per side. Recommended: 1200 &times; 630. Images are re-encoded before storage.</p>
                <form
                    action="{{ route('admin.seo.images.upload') }}"
                    method="post"
                    enctype="multipart/form-data"
                    class="form-stack"
                >
                    @csrf
                    <div class="field">
                        <label for="label">Library label</label><input id="label" name="label" required maxlength="120">
                    </div>
                    <div class="field">
                        <label for="image_file">Sharing image</label><input id="image_file" name="image_file" type="file" accept="image/jpeg,image/png" required>
                    </div>
                    <label class="check"><input type="checkbox" name="approve_image" value="1" required> I approve this image for public social sharing.</label>
                    <button class="button secondary" type="submit">Approve image</button>
                </form>
            </section>
            <section aria-labelledby="library-title">
                <h2 id="library-title">Approved image library</h2>
                <p class="muted small">Replace a page image in its editor. Images used by drafts or revision history are retained for rollback.</p>
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
                                action="{{ route('admin.seo.images.remove', $imageId) }}"
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
                        <p class="muted small">No approved sharing images yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </details>
@endsection
