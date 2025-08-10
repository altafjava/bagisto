<?php

namespace Webkul\Core\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Services\StorageService;

class ChannelRepository extends Repository
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
        return 'Webkul\Core\Contracts\Channel';
    }

    /**
     * Create.
     *
     * @return \Webkul\Core\Contracts\Channel
     */
    public function create(array $data)
    {

        $model = $this->getModel();

        foreach (core()->getAllLocales() as $locale) {
            foreach ($model->translatedAttributes as $attribute) {
                if (isset($data[$attribute])) {
                    $data[$locale->code][$attribute] = $data[$attribute];
                }
            }
        }

        $channel = parent::create($data);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        $this->uploadImages($data, $channel);

        $this->uploadImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @return \Webkul\Core\Contracts\Channel
     */
    public function update(array $data, $id)
    {
        $channel = parent::update($data, $id);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        $this->uploadImages($data, $channel);

        $this->uploadImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Upload images.
     *
     * @param  array  $data
     * @param  \Webkul\Core\Contracts\Channel  $channel
     * @param  string  $type
     * @return void
     */
    public function uploadImages($data, $channel, $type = 'logo')
    {
        if (request()->hasFile($type)) {
            $folder = $this->storageService->isCloudinaryEnabled() 
                ? config('bagisto-cloudinary.folders.channels', 'bagisto/channels') . '/' . $channel->id
                : 'channel/' . $channel->id;
            $path = $this->storageService->uploadImage(current(request()->file($type)), $folder);

            $channel->{$type} = $path;
            $channel->save();
        } else {
            if (! isset($data[$type])) {
                if (! empty($data[$type])) {
                    $this->storageService->deleteFile($channel->{$type});
                }

                $channel->{$type} = null;

                $channel->save();
            }
        }
    }
}
