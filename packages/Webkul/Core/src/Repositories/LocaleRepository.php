<?php

namespace Webkul\Core\Repositories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Contracts\Locale;
use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Services\StorageService;

class LocaleRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Core\Services\StorageService  $storageService
     * @return void
     */
    public function __construct(
        protected StorageService $storageService
    ) {
        parent::__construct(app());
    }

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Locale::class;
    }

    /**
     * Create.
     *
     * @return mixed
     */
    public function create(array $attributes)
    {
        Event::dispatch('core.locale.create.before');

        $locale = parent::create($attributes);

        $this->uploadImage($attributes, $locale);

        Event::dispatch('core.locale.create.after', $locale);

        return $locale;
    }

    /**
     * Update.
     *
     * @return mixed
     */
    public function update(array $attributes, $id)
    {
        Event::dispatch('core.locale.update.before', $id);

        $locale = parent::update($attributes, $id);

        $this->uploadImage($attributes, $locale);

        Event::dispatch('core.locale.update.after', $locale);

        return $locale;
    }

    /**
     * Delete.
     *
     * @param  int  $id
     * @return void
     */
    public function delete($id)
    {
        Event::dispatch('core.locale.delete.before', $id);

        $locale = parent::find($id);

        $locale->delete($id);

        $this->storageService->deleteFile((string) $locale->logo_path);

        Event::dispatch('core.locale.delete.after', $id);
    }

    /**
     * Upload image.
     *
     * @param  array  $attributes
     * @param  \Webkul\Core\Models\Locale  $locale
     * @return void
     */
    public function uploadImage($localeImages, $locale)
    {
        if (! isset($localeImages['logo_path'])) {
            if (! empty($localeImages['logo_path'])) {
                $this->storageService->deleteFile((string) $locale->logo_path);
            }

            $locale->logo_path = null;

            $locale->save();

            return;
        }

        foreach ($localeImages['logo_path'] as $image) {
            if ($image instanceof UploadedFile) {
                $folder = $this->storageService->isCloudinaryEnabled() 
                    ? config('bagisto-cloudinary.folders.locales', 'bagisto/locales')
                    : 'locale';
                $filename = $locale->code . '.' . $image->getClientOriginalExtension();
                
                $path = $this->storageService->uploadImage($image, $folder, ['public_id' => $locale->code]);
                
                $locale->logo_path = $path;
                $locale->save();
            }
        }
    }
}
