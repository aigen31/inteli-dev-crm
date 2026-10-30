<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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
        'application/zip',
        'application/x-rar-compressed',
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
