<section class="panel studio-workspace" aria-labelledby="gallery-title" id="gallery">
    <div class="section-heading">
        <div>
            <h2 id="gallery-title">Studio gallery</h2>
            <p class="muted small">Choose a cover, add descriptive alt text and set display order.</p>
        </div>
    </div>
    <form action="{{ route('admin.studios.images.store', $studio) }}" method="post" enctype="multipart/form-data" class="form-stack">
        @csrf
        <div class="field">
            <label for="gallery-images">Choose images</label>
            <input id="gallery-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-gallery-files>
            <span class="small muted">Up to 10 JPEG, PNG or WebP images, 5 MB each, 200–2400 pixels per side. Uploaded images are stored as safe JPEGs.</span>
        </div>
        <div class="studio-upload-previews" data-gallery-previews aria-live="polite"></div>
        <div><button type="submit" class="button">Upload images</button></div>
    </form>
    <div class="studio-gallery">
        @forelse ($studio->images as $image)
            <article class="studio-gallery-card">
                <img src="{{ asset('uploads/studios/'.$image->path) }}" alt="{{ $image->alt_text }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy">
                @if ($image->is_cover)<span class="pill green">Cover image</span>@endif
                <form action="{{ route('admin.studios.images.update', [$studio, $image]) }}" method="post" class="form-stack">
                    @csrf
                    @method('patch')
                    <div class="field">
                        <label for="alt-{{ $image->id }}">Alt text</label>
                        <input id="alt-{{ $image->id }}" name="alt_text" maxlength="255" value="{{ old('_image') == $image->id ? old('alt_text', $image->alt_text) : $image->alt_text }}">
                    </div>
                    <div class="field">
                        <label for="order-{{ $image->id }}">Image order</label>
                        <input id="order-{{ $image->id }}" name="sort_order" type="number" min="0" max="1000000" value="{{ $image->sort_order }}" required>
                    </div>
                    <input type="hidden" name="_image" value="{{ $image->id }}">
                    @unless ($image->is_cover)
                        <label class="check"><input type="checkbox" name="is_cover" value="1">Use as cover image</label>
                    @endunless
                    <button type="submit" class="button secondary">Save image</button>
                </form>
                <form action="{{ route('admin.studios.images.destroy', [$studio, $image]) }}" method="post" data-studio-remove>
                    @csrf
                    @method('delete')
                    <button type="submit" class="button secondary">Remove from gallery</button>
                </form>
            </article>
        @empty
            <p class="muted">No gallery images yet.</p>
        @endforelse
    </div>
    <p class="small muted">Removing a gallery entry retains its file for recovery and existing references. To hide a studio, set its status to Inactive.</p>
</section>
