<?php

namespace Webkul\Customer\Repositories;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Services\StorageService;
use Webkul\Sales\Models\Order;

class CustomerRepository extends Repository
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
        return 'Webkul\Customer\Contracts\Customer';
    }

    /**
     * Check if customer has order pending or processing.
     *
     * @param  \Webkul\Customer\Models\Customer
     * @return bool
     */
    public function haveActiveOrders($customer)
    {
        return $customer->orders->pluck('status')->contains(function ($val) {
            return $val === 'pending' || $val === 'processing';
        });
    }

    /**
     * Returns current customer group
     *
     * @return \Webkul\Customer\Models\CustomerGroup
     */
    public function getCurrentGroup()
    {
        $customer = auth()->guard()->user();

        return $customer->group ?? core()->getGuestCustomerGroup();
    }

    /**
     * Upload customer's images.
     *
     * @param  array  $data
     * @param  \Webkul\Customer\Models\Customer  $customer
     * @param  string  $type
     * @return void
     */
    public function uploadImages($data, $customer, $type = 'image')
    {
        if (isset($data[$type])) {
            $request = request();

            foreach ($data[$type] as $imageId => $image) {
                $file = $type.'.'.$imageId;
                $folder = $this->storageService->isCloudinaryEnabled() 
                    ? config('bagisto-cloudinary.folders.customers', 'bagisto/customers') . '/' . $customer->id
                    : 'customer/' . $customer->id;

                if ($request->hasFile($file)) {
                    if ($customer->{$type}) {
                        $this->storageService->deleteFile($customer->{$type});
                    }

                    $path = $this->storageService->uploadImage($request->file($file), $folder);
                    $customer->{$type} = $path;
                    $customer->save();
                }
            }
        } else {
            if ($customer->{$type}) {
                $this->storageService->deleteFile($customer->{$type});
            }

            $customer->{$type} = null;
            $customer->save();
        }
    }

    /**
     * Sync new registered customer data.
     *
     * @param  \Webkul\Customer\Contracts\Customer  $customer
     * @return mixed
     */
    public function syncNewRegisteredCustomerInformation($customer)
    {
        /**
         * Setting registered customer to orders.
         */
        Order::where('customer_email', $customer->email)->update([
            'is_guest'      => 0,
            'customer_id'   => $customer->id,
            'customer_type' => \Webkul\Customer\Models\Customer::class,
        ]);

        /**
         * Grabbing orders by `customer_id`.
         */
        $orders = Order::where('customer_id', $customer->id)->get();

        /**
         * Setting registered customer to associated order's relations.
         */
        $orders->each(function ($order) use ($customer) {
            $order->addresses()->update([
                'customer_id' => $customer->id,
            ]);

            $order->shipments()->update([
                'customer_id'   => $customer->id,
                'customer_type' => \Webkul\Customer\Models\Customer::class,
            ]);

            $order->downloadable_link_purchased()->update([
                'customer_id' => $customer->id,
            ]);
        });
    }
}
