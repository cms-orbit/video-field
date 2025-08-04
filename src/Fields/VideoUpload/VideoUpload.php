<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Fields\VideoUpload;

use Orchid\Screen\Field;

class VideoUpload extends Field
{
    /**
     * Blade template.
     */
    protected $view = 'video-field::fields.video-upload.edit';

    /**
     * Default attributes value.
     */
    protected $attributes = [
        'accept' => 'video/*',
        'max_file_size' => 2048, // MB
        'chunk_size' => 1, // MB
        'max_files' => 1,
        'auto_process' => true,
        'show_progress' => true,
        'allowed_extensions' => ['mp4', 'avi', 'mov', 'mkv', 'webm'],
        'profiles' => ['HD@30fps', 'SD@30fps'],
        'auto_thumbnail' => true,
        'auto_sprite' => true,
    ];

    /**
     * Set maximum file size in MB.
     */
    public function maxFileSize(int $size): self
    {
        $this->set('max_file_size', $size);
        return $this;
    }

    /**
     * Set chunk size for upload in MB.
     */
    public function chunkSize(int $size): self
    {
        $this->set('chunk_size', $size);
        return $this;
    }

    /**
     * Set maximum number of files.
     */
    public function maxFiles(int $count): self
    {
        $this->set('max_files', $count);
        return $this;
    }

    /**
     * Enable/disable auto processing after upload.
     */
    public function autoProcess(bool $enabled = true): self
    {
        $this->set('auto_process', $enabled);
        return $this;
    }

    /**
     * Set allowed file extensions.
     */
    public function allowedExtensions(array $extensions): self
    {
        $this->set('allowed_extensions', $extensions);
        return $this;
    }

    /**
     * Set video encoding profiles to generate.
     */
    public function profiles(array $profiles): self
    {
        $this->set('profiles', $profiles);
        return $this;
    }

    /**
     * Enable/disable automatic thumbnail generation.
     */
    public function autoThumbnail(bool $enabled = true): self
    {
        $this->set('auto_thumbnail', $enabled);
        return $this;
    }

    /**
     * Enable/disable automatic sprite generation.
     */
    public function autoSprite(bool $enabled = true): self
    {
        $this->set('auto_sprite', $enabled);
        return $this;
    }

    /**
     * Set help text for the field.
     */
    public function help(string $text): self
    {
        $this->set('help', $text);
        return $this;
    }

    /**
     * Enable multiple file selection.
     */
    public function multiple(bool $enabled = true): self
    {
        if ($enabled) {
            $this->set('max_files', 10);
        }
        $this->set('multiple', $enabled);
        return $this;
    }

    /**
     * Set placeholder text.
     */
    public function placeholder(string $text): self
    {
        $this->set('placeholder', $text);
        return $this;
    }

    /**
     * Show upload progress.
     */
    public function showProgress(bool $enabled = true): self
    {
        $this->set('show_progress', $enabled);
        return $this;
    }
}