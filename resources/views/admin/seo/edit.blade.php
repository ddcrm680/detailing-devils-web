@extends('admin.layouts.app')

@section('title', 'Edit page metadata')
@section('context', 'Page editor')

@section('content')
    <div class="page-heading editor-heading">
        <div>
            <span class="page-id">{{ $page->id }}</span>
            <h1>Edit page metadata</h1>
            <p class="muted url">{{ $page->canonical }}</p>
        </div>
        <div class="editor-actions">
            <span class="save-state" role="status" data-save-state hidden>No unsaved changes</span>
            <button
                class="button secondary"
                type="submit"
                form="metadata-form"
                formaction="{{ route('admin.seo.preview', $page->id) }}"
                formtarget="_blank"
            >Preview without publishing &nearr;</button>
            <button class="button" type="submit" form="metadata-form">Save draft</button>
        </div>
    </div>
    <noscript>
        <p class="notice">JavaScript is disabled. Save drafts and use Preview to review changes. Live previews and unsaved-change warnings are unavailable.</p>
    </noscript>
    @if (! $page->published)
        <p class="notice">The underlying content is not published. You can prepare drafts and previews here; publish the content before publishing its SEO metadata.</p>
    @endif
    <div class="editor-grid">
        <form
            id="metadata-form"
            data-draft-form
            action="{{ route('admin.seo.draft', $page->id) }}"
            method="post"
            class="editor-fields"
        >
            @csrf
            <input type="hidden" name="expected_version" value="{{ $state->version }}">
            <section class="panel form-stack" aria-labelledby="search-title">
                <span class="eyebrow">SEARCH</span>
                <h2 id="search-title">Search metadata</h2>
                <label for="title">SEO title <span class="muted">Required · maximum 200</span></label>
                <input id="title" name="title" maxlength="200" required value="{{ old('title', $fields['title']) }}">
                <span class="field-count" data-count-for="title"></span>
                <label for="description">Meta description <span class="muted">Required · maximum 1000</span></label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="1000"
                    required
                >{{ old('description', $fields['description']) }}</textarea>
                <span class="field-count" data-count-for="description"></span>
                <p class="small muted">Company and email branding are independent. Editing this title changes only this page’s metadata.</p>
            </section>
            <section class="panel form-stack" aria-labelledby="sharing-title">
                <span class="eyebrow">SHARING</span>
                <h2 id="sharing-title">Open Graph & Twitter/X</h2>
                <p class="small muted">Leave optional fields blank to inherit the page title and description.</p>
                <label for="og_title">Open Graph title</label>
                <input id="og_title" name="og_title" maxlength="200" value="{{ old('og_title', $fields['og_title']) }}">
                <label for="og_description">Open Graph description</label>
                <textarea
                    id="og_description"
                    name="og_description"
                    rows="3"
                    maxlength="1000"
                >{{ old('og_description', $fields['og_description']) }}</textarea>
                <label for="og_type">Open Graph type</label>
                <select id="og_type" name="og_type">
                    @foreach (['website', 'article'] as $type)
                        <option value="{{ $type }}" @selected(old('og_type', $fields['og_type']) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <label for="twitter_title">Twitter/X title</label>
                <input
                    id="twitter_title"
                    name="twitter_title"
                    maxlength="200"
                    value="{{ old('twitter_title', $fields['twitter_title']) }}"
                >
                <label for="twitter_description">Twitter/X description</label>
                <textarea
                    id="twitter_description"
                    name="twitter_description"
                    rows="3"
                    maxlength="1000"
                >{{ old('twitter_description', $fields['twitter_description']) }}</textarea>
                <label for="image">Approved sharing image</label>
                <select id="image" name="image">
                    <option value="">No image / text-only card</option>
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
            </section>
            <details class="panel advanced-settings" open>
                <summary>Advanced settings <span class="pill">Protected policy</span>
                </summary>
                <div class="form-stack">
                <span class="eyebrow">03 / PROTECTED POLICY</span>
                <h2 id="policy-title">Canonical & indexing</h2>
                <p class="small muted">Changing these controls requires separate publication approval and a meaningful reason. New URLs and unapproved sitemap pages cannot be added here.</p>
                <p class="small muted">Only a Super Admin can publish canonical, indexing or sitemap changes. Admin and SEO Admin accounts can prepare proposals and publish routine copy or sharing edits.</p>
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
        <aside class="editor-preview" aria-label="Metadata preview and publishing">
            <section class="panel search-preview" aria-labelledby="search-preview-title">
                <div class="section-heading">
                    <h2 id="search-preview-title">Search preview</h2>
                    <span class="pill">Illustrative</span>
                </div>
                <span class="search-domain">{{ parse_url($page->canonical, PHP_URL_HOST) }}</span>
                <span class="muted small url">{{ parse_url($page->canonical, PHP_URL_PATH) }}</span>
                <h3 data-search-title>{{ old('title', $fields['title']) }}</h3>
                <p data-search-description>{{ old('description', $fields['description']) }}</p>
            </section>
            <section class="panel" aria-labelledby="social-preview-title">
                <h2 id="social-preview-title">Social preview</h2>
                <div class="social-card">
                    <img data-social-image alt="Selected sharing artwork" hidden>
                    <div class="image-placeholder" data-image-placeholder>No sharing image selected</div>
                    <div class="social-card-copy">
                        <span class="small muted">{{ parse_url($page->canonical, PHP_URL_HOST) }}</span>
                        <h3 data-social-title>{{ old('og_title', $fields['og_title']) ?: old('title', $fields['title']) }}</h3>
                        <p data-social-description>{{ old('og_description', $fields['og_description']) ?: old('description', $fields['description']) }}</p>
                    </div>
                </div>
                <p class="muted small">Search and social platforms may display a different snippet. Use Preview to review the actual generated metadata.</p>
            </section>
            @if ($draft)
                <section class="panel publish-panel" id="publish-panel" aria-labelledby="publish-title">
                    <span class="eyebrow">DRAFT {{ $draft->id }}</span>
                    <h2 id="publish-title">Publish saved draft</h2>
                    @if ((int) $draft->base_version !== (int) $state->version)
                        <p class="notice error">This draft is based on an older publication. Review and save a fresh draft first.</p>
                    @endif
                    <p class="notice" data-unsaved-notice hidden>You have unsaved edits. Save a new draft to include them in this publication.</p>
                    <form action="{{ route('admin.seo.publish', $draft->id) }}" method="post" class="form-stack">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $state->version }}">
                        <label class="check"><input type="checkbox" name="acknowledge_warnings" value="1" required> I have reviewed the preview and metadata findings below.</label>
                        @can('publish-seo-policy')
                            <label class="check"><input type="checkbox" name="approve_policy" value="1"> I explicitly approve any canonical, robots or sitemap changes in this draft.</label>
                        @endcan
                        <button class="button" type="submit">Publish draft</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
    <details class="panel">
        <summary>Metadata findings</summary>
        <ul class="findings">
            @foreach ($warnings as $warning)
                <li>
                    <span class="pill">{{ $warning['level'] }}</span><strong>{{ $warning['page'] }}</strong><span>{{ $warning['message'] }}</span>
                </li>
            @endforeach
        </ul>
    </details>
    <section class="panel" aria-labelledby="history-title">
        <h2 id="history-title">Revision history</h2>
        <p class="small muted">The latest 20 immutable revisions are shown. Restoring creates a new audited publication.</p>
        @forelse ($history as $revision)
            <details class="revision">
                <summary>#{{ $revision->id }} · {{ ucfirst($revision->kind) }} · {{ $revision->created_at }}</summary>
                <dl>
                    @foreach (json_decode($revision->fields, true) as $key => $value)
                        <dt>{{ str_replace('_', ' ', $key) }}</dt>
                        <dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? 'Inherited') }}</dd>
                    @endforeach
                </dl>
                @if (in_array($revision->kind, ['baseline', 'published', 'rollback'], true))
                    <form action="{{ route('admin.seo.rollback', $revision->id) }}" method="post" class="form-stack">
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
    </section>
@endsection
