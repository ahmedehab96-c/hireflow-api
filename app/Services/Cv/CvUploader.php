<?php

namespace App\Services\Cv;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Stores an uploaded CV privately and extracts its text; nothing is persisted if extraction fails. */
class CvUploader
{
    public function __construct(private PdfTextExtractor $extractor) {}

    /** @throws CvTextExtractionException */
    public function upload(User $user, UploadedFile $file): Cv
    {
        $disk = Storage::disk(config('cv.disk'));
        $path = $file->store("cvs/{$user->id}", config('cv.disk'));

        try {
            $text = $this->extractor->extract($disk->path($path));

            if (mb_strlen($text) > config('cv.max_text_chars')) {
                throw new CvTextExtractionException('This CV contains too much text to analyze. Please upload a shorter version (a few pages at most).');
            }

            $cv = new Cv([
                'original_filename' => $this->safeName($file->getClientOriginalName()),
                'file_path' => $path,
                'extracted_text' => $text,
            ]);
            $cv->user()->associate($user);
            $cv->save();

            return $cv;
        } catch (Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
    }

    private function safeName(string $name): string
    {
        $name = trim(preg_replace('/[^\pL\pN\s._()-]/u', '', basename($name)) ?? '');

        return mb_substr($name !== '' ? $name : 'cv.pdf', 0, 200);
    }
}
