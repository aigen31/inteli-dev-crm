<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FileAttachment;
use App\Models\User;

class FileAttachmentPolicy
{
    public function download(User $user, FileAttachment $file): bool
    {
        return $user->isAdmin() || $file->uploaded_by === $user->id;
    }

    public function delete(User $user, FileAttachment $file): bool
    {
        return $user->isAdmin();
    }
}
