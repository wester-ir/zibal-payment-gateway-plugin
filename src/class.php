<?php

if (! defined('LARAVEL_START')) {
    exit(0);
}

use App\Models\Order;
use App\Services\Core\Payment\Exceptions\PaymentCreationException;
use App\Services\Core\Payment\Exceptions\PaymentCurlException;
use App\Services\Core\Payment\Gateway\Gateway;
use App\Services\Core\Payment\Gateway\GatewayInterface;
use Illuminate\Http\Request;

class WK_ZIBAL_PAYMENT_GATEWAY extends Gateway implements GatewayInterface
{
    public string $name = 'zibal';

    private const URL = 'https://gateway.zibal.ir';
    private const ENDPOINTS = [
        'eager' => [
            'request' => 'v1/request',
            'verify' => 'v1/verify',
        ],
        'lazy' => [
            'request' => 'request/lazy',
            'verify' => 'verify',
        ],
    ];

    /**
     * Send a request.
     *
     * @throws PaymentCurlException
     */
    private function sendRequest(string $endpoint, mixed $data): array
    {
        $url = self::URL .'/'. self::ENDPOINTS[$this->model->data['type']][$endpoint];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $err = curl_error($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        // Check if there was a cURL error
        if ($errno !== 0) {
            throw new PaymentCurlException($err);
        }

        // Attempt to decode the response JSON
        $response = json_decode($response, true);

        return [$response, $responseCode];
    }

    /**
     * Create a payment request.
     *
     * @param  int  $orderId
     * @param  int  $amount
     * @param  string  $description
     * @param  string  $callbackUrl
     * @return void
     * @throws PaymentCurlException
     * @throws PaymentCreationException
     */
    public function create(int $orderId, int $amount, string $description, string $callbackUrl): void
    {
        // Convert to Rials
        if (productCurrency()->value === 'IRT') {
            $amount = $amount * 10;
        }

        [$response, $responseStatus] = $this->sendRequest('request', [
            'merchant'    => $this->model->data['merchant'],
            'amount'      => $amount,
            'callbackUrl' => $callbackUrl,
            'description' => $description,
            'orderId'     => $orderId,
        ]);

        // Process the response
        $result = $response['result'] ?? 0;

        if ($result === 100) {
            $this->uniqueId = $response['trackId'];
            $this->redirectUrl = self::URL .'/start/'. $this->uniqueId;

            return;
        }

        // Handle payment creation errors
        $message = match ($result) {
            102 => 'شناسه درگاه یافت نشد.',
            103 => 'درگاه غیر فعال می باشد.',
            104 => 'شناسه درگاه نامعتبر است.',
            105 => 'مبلغ باید بیشتر از '. number_format(1000) .'ریال باشد.',
            106 => 'آدرس بازگشتی نامعتبر است.',
            113 => 'مبلغ تراکنش از سقف میزان تراکنش بیشتر است.',
            default => 'نامشخص',
        };

        throw new PaymentCreationException($message);
    }

    /**
     * Verify parameters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $orderId
     * @return bool
     */
    public function verifyParameters(Request $request, mixed $orderId): bool
    {
        return $request->has(['success', 'trackId', 'status', 'orderId']) && $order->id == $this->getOrderId($request);
    }

    /**
     * Verify the payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @throws PaymentCurlException
     */
    public function verify(Request $request): array
    {
        // Send a payment verification request to Zibal API
        [$responseData] = $this->sendRequest('verify', [
            'merchant' => $this->model->data['merchant'],
            'trackId'  => $request->trackId,
        ]);

        return match ($responseData['result']) {
            100 => [
                'status' => true,
                'result' => $responseData['result'],
                'unique_id' => $request->trackId,
                'ref_number' => $responseData['refNumber'],
                'amount' => $responseData['amount'],
            ],
            201 => [
                'status' => false,
                'result' => $responseData['result'],
                'unique_id' => $request->trackId,
                'reason' => 'already_verified',
            ],
            202 => [
                'status' => false,
                'result' => $responseData['result'],
                'unique_id' => $request->trackId,
                'reason' => 'unpaid_or_unsuccessful',
            ],
            default => [
                'status' => false,
                'result' => $responseData['result'],
                'unique_id' => $request->trackId,
                'reason' => 'unknown',
            ],
        };
    }

    /**
     * Get the transaction id.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function getTransactionUid(Request $request): ?string
    {
        return $request->trackId;
    }

    /**
     * Get the order id.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function getOrderId(Request $request): ?string
    {
        return $request->orderId;
    }
}
