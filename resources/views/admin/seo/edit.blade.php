@extends('admin.layouts.app')

@include('admin.seo.partials.scripts')

@section('title', 'Edit page SEO')
@section('context', 'Edit page')

@section('content')
    @php
        $pageWarnings = array_values(array_filter($warnings, fn ($warning) => in_array(
            $page->id,
            array_map('trim', explode(',', $warning['page'])),
            true,
        )));
    @endphp
    <div class="page-heading editor-heading">
        <div>
            <div class="heading-line">
                <h1>Edit Page SEO</h1>
                <span class="pill {{ $draft ? 'amber' : 'green' }}">{{ $draft ? 'Draft saved' : 'Ready to edit' }}</span>
            </div>
            <p class="muted">Update how this page appears in search and when shared.</p>
        </div>
        <div class="editor-actions">
            <span class="save-state" role="status" data-save-state hidden>No unsaved changes</span>
            <button class="button secondary" type="submit" form="metadata-form"><i class="bi bi-save" aria-hidden="true"></i> Save Draft</button>
            <button
                class="button secondary"
                type="submit"
                form="metadata-form"
                formaction="{{ route('admin.seo.preview', $page->id) }}"
                formtarget="_blank"
            ><i class="bi bi-eye" aria-hidden="true"></i> Preview</button>
            @if ($draft)
                <a class="button" href="#publish-controls" data-open-panel="publish-panel"><i class="bi bi-check-lg" aria-hidden="true"></i> Publish</a>
            @else
                <button class="button" type="button" disabled title="Save a draft before publishing"><i class="bi bi-check-lg" aria-hidden="true"></i> Publish</button>
            @endif
        </div>
    </div>
    <noscript>
        <p class="notice">Live previews are unavailable without JavaScript. Save a draft or use Preview to review your changes.</p>
    </noscript>
    @if (! $page->published)
        <p class="notice">This page is not published. You can prepare drafts here, then publish its content before publishing SEO.</p>
    @endif
    <div class="editor-grid">
        <div class="editor-main">
            <form
                id="metadata-form"
                data-draft-form
                action="{{ route('admin.seo.draft', $page->id) }}"
                method="post"
                class="editor-fields"
            >
                @csrf
                <input type="hidden" name="expected_version" value="{{ $state->version }}">
                <section class="panel form-stack page-details" aria-labelledby="search-title">
                    <h2 class="panel-title" id="search-title">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        Page Details
                    </h2>
                    <div class="page-context">
                        <strong>{{ str($page->id)->replace(['.show', '.index', 'legal.', 'studios.'], '')->replace(['.', ':'], ' ')->headline()->replace(['Fofo', 'Xclusive'], ['FOFO', 'X-Clusive']) }}</strong>
                        <a href="{{ $page->canonical }}" target="_blank" rel="noopener">{{ $page->canonical }} <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="field">
                        <label for="title">SEO Title <span class="required" aria-hidden="true">*</span></label>
                        <input id="title" name="title" maxlength="200" required value="{{ old('title', $fields['title']) }}">
                        <span class="field-count" data-count-for="title"></span>
                    </div>
                    <div class="field">
                        <label for="description">Meta Description <span class="required" aria-hidden="true">*</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="1000" required>{{ old('description', $fields['description']) }}</textarea>
                        <span class="field-count" data-count-for="description"></span>
                    </div>
                </section>
                <details class="panel accordion" id="social-settings">
                    <summary>
                        <span class="panel-title"><i class="bi bi-share" aria-hidden="true"></i>Social Sharing</span>
                        <span class="summary-note">Facebook &amp; X</span>
                    </summary>
                    <div class="form-stack disclosure-body">
                        <p class="muted small">Optional. Leave blank to use your SEO title and description.</p>
                        <div class="two-column social-fields">
                            <div class="form-stack">
                                <h3 class="social-field-heading">Facebook &amp; other platforms <span class="muted">Open Graph</span></h3>
                                <label for="og_title">Sharing title</label>
                                <input id="og_title" name="og_title" maxlength="200" value="{{ old('og_title', $fields['og_title']) }}">
                                <label for="og_description">Sharing description</label>
                                <textarea
                                    id="og_description"
                                    name="og_description"
                                    rows="3"
                                    maxlength="1000"
                                >{{ old('og_description', $fields['og_description']) }}</textarea>
                                <label for="og_type">Content type</label>
                                <select id="og_type" name="og_type">
                                    @foreach (['website', 'article'] as $type)
                                        <option value="{{ $type }}" @selected(old('og_type', $fields['og_type']) === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-stack">
                                <h3 class="social-field-heading">X / Twitter</h3>
                                <label for="twitter_title">X title</label>
                                <input
                                    id="twitter_title"
                                    name="twitter_title"
                                    maxlength="200"
                                    value="{{ old('twitter_title', $fields['twitter_title']) }}"
                                >
                                <label for="twitter_description">X description</label>
                                <textarea
                                    id="twitter_description"
                                    name="twitter_description"
                                    rows="3"
                                    maxlength="1000"
                                >{{ old('twitter_description', $fields['twitter_description']) }}</textarea>
                            </div>
                        </div>
                        <div class="form-stack sharing-image-field">
                            <label for="image">Social Sharing Image</label>
                            <select id="image" name="image">
                                <option value="">No image (optional)</option>
                                @foreach ($images as $url => $label)
                                    @php
                                        $imagePath = parse_url($url, PHP_URL_PATH);
                                        $imageSource = str_starts_with($imagePath, '/uploads/images/seo/')
                                            ? route('admin.seo.images.show', pathinfo($imagePath, PATHINFO_FILENAME))
                                            : asset(ltrim($imagePath, '/'));
                                    @endphp
                                    <option
                                        data-preview-source="{{ $imageSource }}"
                                        value="{{ $url }}"
                                        @selected(old('image', $fields['image']) === $url)
                                    >{{ $label }}</option>
                                @endforeach
                            </select>
                            <a class="text-link" href="{{ route('admin.seo.index') }}#image-library" target="_blank" rel="noopener">Manage Images <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                            <span class="small muted">Save your draft first. The library opens in a new tab. After approval, return here and refresh to select the image.</span>
                        </div>
                    </div>
                </details>
                <details class="panel accordion advanced-settings" id="advanced-settings">
                    <summary>
                        <span class="panel-title"><i class="bi bi-gear" aria-hidden="true"></i>Advanced SEO Settings</span>
                        <span class="summary-note">Canonical, indexing, sitemap</span>
                    </summary>
                    <div class="form-stack disclosure-body">
                        <p class="muted small">Only a Super Admin can publish policy changes. Review carefully and provide a reason.</p>
                        <div class="two-column compact">
                            <div class="form-stack">
                                <label for="canonical">Approved canonical URL</label>
                                <select id="canonical" name="canonical">
                                    @foreach ($canonicals as $canonical)
                                        <option value="{{ $canonical->canonical }}" @selected(old('canonical', $fields['canonical']) === $canonical->canonical)>{{ $canonical->id }} — {{ $canonical->canonical }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-stack">
                                <label for="robots">Robots directive</label>
                                <select id="robots" name="robots">
                                    @foreach (['index, follow', 'noindex, follow', 'noindex, nofollow', 'index, nofollow'] as $robots)
                                        <option value="{{ $robots }}" @selected(old('robots', $fields['robots']) === $robots)>{{ $robots }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="sitemap" value="0">
                        <label class="check"><input type="checkbox" name="sitemap" value="1" @checked(old('sitemap', $fields['sitemap']))> Include in the sitemap, within the approved page policy</label>
                        <label for="policy_reason">Policy decision reason <span class="muted">Required only for policy changes</span></label>
                        <textarea
                            id="policy_reason"
                            name="policy_reason"
                            rows="2"
                            maxlength="500"
                        >{{ old('policy_reason', $draft->policy_reason ?? '') }}</textarea>
                    </div>
                </details>
            </form>
            <details class="panel accordion" id="version-history">
                <summary>
                    <span class="panel-title"><i class="bi bi-clock-history" aria-hidden="true"></i>Version History</span>
                    <span class="summary-note">{{ count($history) }} recent revisions</span>
                </summary>
                <p class="muted small">Review saved values against the editor. Restore creates a new publication and keeps previous versions.</p>
                @forelse ($history as $revision)
                    <details class="revision">
                        <summary>
                            <span class="revision-version">#{{ $revision->id }} <span class="pill {{ $revision->kind === 'draft' ? 'amber' : 'green' }}">{{ ucfirst($revision->kind) }}</span></span>
                            <time>{{ $revision->created_at }}</time>
                            <span class="revision-action">View changes</span>
                        </summary>
                        <dl>
                            @foreach (json_decode($revision->fields, true) as $key => $value)
                                <dt>{{ str_replace('_', ' ', $key) }}</dt>
                                <dd>
                                    <span>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? 'Inherited') }}</span>
                                    @if (array_key_exists($key, $fields) && $fields[$key] !== $value)
                                        <small class="muted">In editor: {{ is_bool($fields[$key]) ? ($fields[$key] ? 'Yes' : 'No') : ($fields[$key] ?? 'Inherited') }}</small>
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                        @if (in_array($revision->kind, ['baseline', 'published', 'rollback'], true))
                            <form action="{{ route('admin.seo.rollback', $revision->id) }}" method="post" class="form-stack" data-confirm="restore">
                                @csrf
                                <input type="hidden" name="expected_version" value="{{ $state->version }}">
                                <label class="check"><input type="checkbox" name="acknowledge_warnings" value="1" required> Restore this revision after reviewing its metadata.</label>
                                @can('publish-seo-policy')
                                    <label class="check"><input type="checkbox" name="approve_policy" value="1"> Approve any indexing/canonical changes caused by restoration.</label>
                                    <label for="restore_reason_{{ $revision->id }}">Policy restoration reason, if needed</label>
                                    <input id="restore_reason_{{ $revision->id }}" name="policy_reason" maxlength="500">
                                @endcan
                                <button class="button secondary" type="submit">Restore revision #{{ $revision->id }}</button>
                            </form>
                        @endif
                    </details>
                @empty
                    <p class="muted">No edits yet. The first draft will also preserve the current baseline.</p>
                @endforelse
            </details>
            @if (count($pageWarnings))
                <details class="panel accordion" id="metadata-findings">
                    <summary><span class="panel-title">Page Review <span class="pill">{{ count($pageWarnings) }}</span></span></summary>
                    <p class="muted small">Only findings for this page. Suggestions do not change your approved copy.</p>
                    @include('admin.seo.partials.warnings', ['findings' => $pageWarnings, 'showPage' => false])
                </details>
            @endif
        </div>
        <aside class="editor-preview" aria-label="Metadata preview and publishing">
            <section class="panel search-preview live-preview" aria-labelledby="search-preview-title">
                <h2 class="panel-title" id="search-preview-title"><i class="bi bi-eye" aria-hidden="true"></i>Live Preview</h2>
                <div class="preview-tabs" data-preview-tabs hidden aria-label="Preview type">
                    <button type="button" id="search-preview-tab" data-preview-tab="search" aria-controls="search-preview-panel">Search</button>
                    <button type="button" id="social-preview-tab" data-preview-tab="social" aria-controls="social-preview-panel">Social</button>
                </div>
                <div id="search-preview-panel" data-preview-panel="search">
                    <div class="search-result">
                        <span class="search-domain">{{ parse_url($page->canonical, PHP_URL_HOST) }}</span>
                        <span class="search-path">{{ parse_url($page->canonical, PHP_URL_PATH) }}</span>
                        <h3 data-search-title>{{ old('title', $fields['title']) }}</h3>
                        <p data-search-description>{{ old('description', $fields['description']) }}</p>
                    </div>
                </div>
                <div id="social-preview-panel" data-preview-panel="social">
                    <div class="preview-switch" role="group" aria-label="Social preview platform">
                        <button type="button" data-social-platform="og" aria-pressed="true">Facebook</button>
                        <button type="button" data-social-platform="twitter" aria-pressed="false">X / Twitter</button>
                    </div>
                    <div class="social-card">
                        <img data-social-image alt="Selected sharing artwork" hidden>
                        <div class="image-placeholder" data-image-placeholder>No sharing image selected</div>
                        <div class="social-card-copy">
                            <span class="small">{{ parse_url($page->canonical, PHP_URL_HOST) }}</span>
                            <h3 data-social-title>{{ old('og_title', $fields['og_title']) ?: old('title', $fields['title']) }}</h3>
                            <p data-social-description>{{ old('og_description', $fields['og_description']) ?: old('description', $fields['description']) }}</p>
                        </div>
                    </div>
                </div>
                <p class="preview-note muted">Illustrative only. Platforms may show a different snippet.</p>
            </section>
            @if ($draft)
                <details class="panel accordion publish-panel" id="publish-panel">
                    <summary><span class="panel-title">Review &amp; Publish</span><span class="summary-note">Saved draft #{{ $draft->id }}</span></summary>
                    <form id="publish-controls" action="{{ route('admin.seo.publish', $draft->id) }}" method="post" class="form-stack" data-confirm="publish">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $state->version }}">
                        @if ((int) $draft->base_version !== (int) $state->version)
                            <p class="notice error">This draft is based on an older publication. Review and save a fresh draft first.</p>
                        @endif
                        <p class="notice" data-unsaved-notice hidden>You have unsaved edits. Save a new draft to include them in this publication.</p>
                        <label class="check"><input type="checkbox" name="acknowledge_warnings" value="1" required> I have reviewed the preview and metadata findings.</label>
                        @can('publish-seo-policy')
                            <label class="check"><input type="checkbox" name="approve_policy" value="1"> I approve any canonical, robots or sitemap changes in this draft.</label>
                        @endcan
                        <button class="button" type="submit">Publish draft</button>
                    </form>
                </details>
            @else
                <p class="small muted draft-hint">Save a draft to enable publishing. Your changes stay private until published.</p>
            @endif
        </aside>
    </div>
@endsection
