<?php

if (! defined('LARAVEL_START')) {
    exit(0);
}

use App\Core\Admin\PaymentGatewayCore;
use App\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

if (! class_exists('WK_ZIBAL_PAYMENT_GATEWAY') && ! function_exists('WK_ZIBAL_PAYMENT_GATEWAY_INIT')) {
    // Add translations
    Lang::addNamespace('ZibalPaymentGateway', realpath( __DIR__ .'/lang/'));

    // Require the class
    require_once('class.php');

    function WK_ZIBAL_PAYMENT_GATEWAY_INIT(): array
    {
        return ['zibal' => WK_ZIBAL_PAYMENT_GATEWAY::class];
    }

    // Gateways
    register_plugin_action(
        hook: 'payment_gateways',
        callback: 'WK_ZIBAL_PAYMENT_GATEWAY_INIT',
    );

    if (request()->routeIs('admin.payment-gateways.edit')) {
        // Custom Fields
        register_plugin_action(
            hook: 'pg[zibal]__edit_form_view',
            callback: function (PaymentGateway $paymentGateway) use ($plugin) {
                return plugin_view($plugin->name, 'src.views.edit-form-fields', compact('paymentGateway'))->render();
            },
        );

        // Validation Rules
        register_plugin_action(
            hook: 'pg[zibal]__validation_rules',
            callback: function (Request $request) {
                return [
                    'merchant' => ['required', 'string'],
                    'type' => ['required', 'string', 'in:eager,lazy'],
                ];
            },
        );

        // The update method
        register_plugin_action(
            hook: 'pg[zibal]__update',
            callback: function (UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway, PaymentGatewayCore $core) {
                return DB::transaction(function () use ($request, $paymentGateway, $core) {
                    $core->prefill($request, $paymentGateway)->fill([
                        'data' => [
                            'merchant' => $request->input('merchant'),
                            'type'     => $request->input('type'),
                        ],
                    ]);

                    return $paymentGateway->save();
                });
            },
        );
    }
}
