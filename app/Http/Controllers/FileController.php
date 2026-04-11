<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function upload(Request $request, FileUploadService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $attachment = $service->upload($request->file('file'), $request->user());

        return response()->json([
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'size' => $attachment->size,
        ], 201);
    }

    public function download(FileAttachment $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return response()->streamDownload(function () use ($file) {
            echo \Illuminate\Support\Facades\Storage::get($file->stored_path);
        }, $file->original_name, [
            'Content-Type' => $file->mime_type,
        ]);
    }
}
