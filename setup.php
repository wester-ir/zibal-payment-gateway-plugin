<?php

if (! defined('LARAVEL_START')) {
    exit(0);
}

use App\Enums\CurrencyEnum;
use App\Models\PaymentGateway;
use App\Services\Core\Plugin\Bases\BasePluginSetup;

class PluginSetup extends BasePluginSetup
{
    private const ID = 'zibal';

    public function install(): void
    {
        if ($this->query()->doesntExist()) {
            PaymentGateway::create([
                'id'         => self::ID,
                'title'      => 'زیبال',
                'currency'   => CurrencyEnum::IranianRial,
                'logo_path'  => $this->plugin->name,
                'logo_name'  => 'logo.png',
                'logo_disk'  => 'plugin',
                'data' => [
                    'merchant' => '',
                    'type' => 'eager',
                ],
                'is_active'  => false,
                'deleted_at' => now(),
            ]);
        }
    }

    public function activate(): void
    {
        $this->query()->update([
            'is_active'  => false,
            'deleted_at' => null,
        ]);
    }

    public function deactivate(): void
    {
        $this->query()->update([
            'is_active'  => false,
            'deleted_at' => now(),
        ]);
    }

    public function uninstall(): void
    {
        $this->query()->forceDelete();
    }

    private function query()
    {
        return PaymentGateway::where('id', self::ID)->withTrashed();
    }
};
