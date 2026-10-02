<?php

use App\Support\WebpImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * T-127 (20-09-2026) — Profile Photo / Passbook / Cancelled Cheque images are stored as WebP;
 * a PDF bank proof can't be, so it is stored exactly as uploaded.
 */
beforeEach(function () {
    Storage::fake('public');
});

test('a JPEG upload is stored as a real WebP file', function () {
    $path = WebpImageStore::store(UploadedFile::fake()->image('photo.jpg', 200, 120), 'profile-photos');

    expect($path)->toBeString()->toEndWith('.webp')->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);

    $bytes = Storage::disk('public')->get($path);
    expect(substr($bytes, 0, 4))->toBe('RIFF')->and(substr($bytes, 8, 4))->toBe('WEBP');

    $size = getimagesizefromstring($bytes);
    expect($size[0])->toBe(200)->and($size[1])->toBe(120);
});

test('a PNG upload is stored as WebP too', function () {
    $path = WebpImageStore::store(UploadedFile::fake()->image('cheque.png', 90, 60), 'bank-proofs');

    expect($path)->toEndWith('.webp')->toStartWith('bank-proofs/');
    expect(substr(Storage::disk('public')->get($path), 8, 4))->toBe('WEBP');
});

test('a PDF bank proof is left exactly as uploaded', function () {
    $path = WebpImageStore::store(UploadedFile::fake()->create('passbook.pdf', 20, 'application/pdf'), 'bank-proofs');

    expect($path)->toEndWith('.pdf')->toStartWith('bank-proofs/');
    Storage::disk('public')->assertExists($path);
});
