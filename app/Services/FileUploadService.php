<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain',
        'text/csv',
        'text/markdown',
        'text/html',
        'application/json',
        'application/xml',
        'text/xml',
        'application/octet-stream',
    ];

    private const MAX_SIZE = 20 * 1024 * 1024; // 20 MB

    public function upload(UploadedFile $file, User $user): FileAttachment
    {
        $this->validate($file);

        $path = $file->store('attachments', 'local');

        return FileAttachment::create([
            'uploaded_by' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function storeContents(string $contents, string $name, string $mime, User $user): FileAttachment
    {
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Недопустимый тип файла: '.$mime);
        }

        if (strlen($contents) > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Файл слишком большой. Максимум 20 МБ.');
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $path = 'attachments/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        return FileAttachment::create([
            'uploaded_by' => $user->id,
            'original_name' => $name,
            'stored_path' => $path,
            'mime_type' => $mime,
            'size' => strlen($contents),
        ]);
    }

    private function validate(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Недопустимый тип файла: '.$file->getMimeType());
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Файл слишком большой. Максимум 20 МБ.');
        }
    }
}
