<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileAttachment>
 */
class FileAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'original_name' => fake()->word() . '.pdf',
            'stored_path' => 'attachments/' . fake()->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 20 * 1024 * 1024),
        ];
    }
}
