<a class="gallery-photo" href="{{ asset(ltrim($photo->public_path, '/')) }}" target="_blank" rel="noopener" data-photo-index="{{ $photoIndex }}" aria-label="View studio photo {{ $photoIndex + 1 }}">
    <img src="{{ asset(ltrim($photo->public_path, '/')) }}" alt="{{ $photo->alt_text ?: $studio->name.' studio photo '.($photoIndex + 1) }}" width="{{ $photo->width }}" height="{{ $photo->height }}" loading="lazy" decoding="async">
    <span class="photo-caption"><span>{{ $photo->alt_text }}</span><i class="icon bi bi-plus-lg" aria-hidden="true"></i></span>
</a>
