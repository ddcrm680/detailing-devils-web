<section class="panel studio-workspace studio-gallery-workspace" aria-labelledby="gallery-title" id="gallery">
    <div class="section-heading">
        <div>
            <h2 id="gallery-title">Add Photos</h2>
        </div>
    </div>
    @php($uploadHasErrors = $errors->has('images') || $errors->has('images.*'))
    <form action="{{ route('admin.studios.images.store', $studio) }}" method="post" enctype="multipart/form-data" class="studio-upload-form" data-gallery-upload data-has-cover="{{ $studio->images->contains('is_cover', true) ? 'true' : 'false' }}">
        @csrf
        <div class="field studio-dropzone" data-gallery-dropzone>
            <label for="gallery-images">Select Photos</label>
            <span class="small muted" data-drop-help hidden>Drop photos here</span>
            <input
                id="gallery-images"
                name="images[]"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
                data-gallery-files
                aria-describedby="upload-help{{ $uploadHasErrors ? ' upload-errors' : '' }}"
                @if ($uploadHasErrors) aria-invalid="true" @endif
            >
            <span class="small muted" id="upload-help">Up to 10 JPEG, PNG or WebP images. Maximum 5 MB each; 200–2400 pixels per side.</span>
            <div id="upload-errors" role="alert" tabindex="-1">
                @error('images')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
                @foreach ($errors->get('images.*') as $messages)
                    @foreach ($messages as $message)
                        <span class="invalid-feedback">{{ $message }}</span>
                    @endforeach
                @endforeach
            </div>
        </div>
        <div class="studio-upload-previews" data-gallery-previews></div>
        <div class="studio-batch-actions">
            <span class="muted small" data-selected-count role="status">0 photos selected</span>
            <button type="submit" class="button">Upload Photos</button>
        </div>
    </form>
    <div class="section-heading studio-saved-heading">
        <h2>Saved Photos</h2>
        <span class="muted small">{{ $studio->images->count() }} {{ $studio->images->count() === 1 ? 'photo' : 'photos' }}</span>
    </div>
    @error('gallery')
        <p class="invalid-feedback" role="alert">{{ $message }}</p>
    @enderror
    <div class="studio-gallery">
        @forelse ($studio->images as $image)
            @php($imageHasErrors = (string) old('_image') === (string) $image->id)
            <article class="studio-gallery-card">
                <img src="{{ asset('uploads/studios/'.$image->path) }}" alt="{{ $image->alt_text }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy">
                <form action="{{ route('admin.studios.images.update', [$studio, $image]) }}" method="post" class="form-stack" id="photo-edit-{{ $image->id }}" data-photo-form data-photo-original="{{ json_encode(['alt_text' => $image->alt_text ?? '', 'sort_order' => $image->sort_order, 'is_cover' => (bool) $image->is_cover]) }}">
                    @csrf
                    @method('patch')
                    <div class="field">
                        <label class="sr-only" for="alt-{{ $image->id }}">Photo Description</label>
                        <input
                            id="alt-{{ $image->id }}"
                            name="alt_text"
                            placeholder="Describe this photo"
                            maxlength="255"
                            value="{{ $imageHasErrors ? old('alt_text', $image->alt_text) : $image->alt_text }}"
                            @if ($imageHasErrors && $errors->has('alt_text')) aria-invalid="true" aria-describedby="alt-error-{{ $image->id }}" @endif
                        >
                        @if ($imageHasErrors)
                            @error('alt_text')
                                <span class="invalid-feedback" id="alt-error-{{ $image->id }}">{{ $message }}</span>
                            @enderror
                        @endif
                    </div>
                    <details class="studio-photo-order" @if ($imageHasErrors && $errors->has('sort_order')) open @endif>
                        <summary>Order {{ $image->sort_order }}</summary>
                    <div class="field">
                        <label for="order-{{ $image->id }}">Photo order</label>
                        <input
                            id="order-{{ $image->id }}"
                            name="sort_order"
                            type="number"
                            min="0"
                            max="1000000"
                            value="{{ $imageHasErrors ? old('sort_order', $image->sort_order) : $image->sort_order }}"
                            required
                            @if ($imageHasErrors && $errors->has('sort_order')) aria-invalid="true" aria-describedby="image-order-error-{{ $image->id }}" @endif
                        >
                        @if ($imageHasErrors)
                            @error('sort_order')
                                <span class="invalid-feedback" id="image-order-error-{{ $image->id }}">{{ $message }}</span>
                            @enderror
                        @endif
                    </div>
                    </details>
                    <input type="hidden" name="_image" value="{{ $image->id }}">
                    @if ($image->is_cover)
                        <span class="pill green studio-cover-label">Cover photo</span>
                    @else
                        <label class="check">
                            <input
                                type="checkbox"
                                name="is_cover"
                                value="1"
                                @checked($imageHasErrors && old('is_cover'))
                                @if ($imageHasErrors && $errors->has('is_cover')) aria-invalid="true" aria-describedby="cover-error-{{ $image->id }}" @endif
                            >
                            Set as Cover
                        </label>
                    @endif
                    @if ($imageHasErrors)
                        @error('is_cover')
                            <span class="invalid-feedback" id="cover-error-{{ $image->id }}">{{ $message }}</span>
                        @enderror
                    @endif
                </form>
                <div class="studio-saved-actions">
                    <button type="submit" form="photo-edit-{{ $image->id }}" data-photo-save="photo-edit-{{ $image->id }}" class="button secondary studio-photo-save" aria-label="Save Changes" title="Save Changes"><i class="bi bi-check2" aria-hidden="true"></i></button>
                    @foreach (['up' => 'Move Up', 'down' => 'Move Down'] as $direction => $label)
                        <form action="{{ route('admin.studios.images.update', [$studio, $image]) }}" method="post">
                            @csrf
                            @method('patch')
                            <input type="hidden" name="direction" value="{{ $direction }}">
                            <button class="button secondary" type="submit" aria-label="{{ $label }}" title="{{ $label }}" @disabled($direction === 'up' ? $loop->parent->first : $loop->parent->last)><i class="bi bi-arrow-{{ $direction }}" aria-hidden="true"></i></button>
                        </form>
                    @endforeach
                    <form action="{{ route('admin.studios.images.destroy', [$studio, $image]) }}" method="post" data-studio-remove>
                        @csrf
                        @method('delete')
                        <button type="submit" class="button secondary" aria-label="Remove" title="Remove Photo"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                    </form>
                </div>
            </article>
        @empty
            <p class="studio-gallery-empty muted"><i class="bi bi-images" aria-hidden="true"></i> No photos added yet.</p>
        @endforelse
    </div>
</section>
