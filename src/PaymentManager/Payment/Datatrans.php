<?php

declare(strict_types=1);

/*
 * This file is part of the Pixelart Payment Provider Datatrans Bundle.
 *
 * Copyright (c) pixelart GmbH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Pixelart\PaymentProviderDatatransBundle\PaymentManager\Payment;

use GuzzleHttp\Client;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderAgentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment\AbstractPayment;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Status;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\StatusInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\PaymentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentRequest\AbstractRequest;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentResponse\SnippetResponse;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentResponse\StartPaymentResponseInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentResponse\UrlResponse;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\PriceInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Type\Decimal;
use Pixelart\PaymentProviderDatatransBundle\Exception\InvalidSignatureException;
use Pixelart\PaymentProviderDatatransBundle\Exception\NotImplementedException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Datatrans extends AbstractPayment implements PaymentInterface
{
    private string $merchantId;
    private string $password;
    private string $sign;
    private array $urls = [];
    private bool $autoSettle;
    private array $authorizedData = [];

    public function __construct(
        array $options,
        private RequestStack $requestStack,
        private ContainerInterface $container,
        private Client $client
    ) {
        $this->processOptions(
            $this->configureOptions(new OptionsResolver())->resolve($options)
        );
    }

    public function getName(): string
    {
        return 'Datatrans';
    }

    public function signUrl($url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query ?: '', $query);

        unset($query['timestamp'], $query['signature']);

        $query['timestamp'] = time();
        $unsignedUrl = strtok($url, '?').'?'.http_build_query($query);

        $query['signature'] = hash_hmac('sha256', $unsignedUrl, $this->sign);

        return strtok($url, '?').'?'.http_build_query($query);
    }

    public function checkSignature($url): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $query);

        if (!$query['timestamp'] || !$query['signature']) {
            return false;
        }

        $timestamp = $query['timestamp'];
        $signature = $query['signature'];
        unset($query['timestamp'], $query['signature']);
        $query['timestamp'] = $timestamp;

        $unsignedUrl = strtok($url, '?').'?'.http_build_query($query);

        return $signature === hash_hmac('sha256', $unsignedUrl, $this->sign);
    }

    /** @todo Refactor this function! Quickly done to get it working (needs better open-close principal and more abstraction). */
    public function startPayment(OrderAgentInterface $orderAgent, PriceInterface $price, AbstractRequest $config): StartPaymentResponseInterface
    {
        $request = $this->requestStack->getMainRequest();
        $order = $orderAgent->getOrder();
        $refNo = $orderAgent->getCurrentPendingPaymentInfo()?->getInternalPaymentId();
        $returnUrl = $this->signUrl($config->getReturnUrl().'?orderIdent='.$refNo);

        if ('credit_card' === $config->getPaymentMethod() && $request?->isMethod('POST')) {
            $redirect = $request?->get('redirect');
            $transactionId = $request?->get('uppTransactionId');
            $returnUrl = $this->signUrl($config->getReturnUrl().'?orderIdent='.$refNo.'&uppTransactionId='.$transactionId);

            return new UrlResponse($order, $redirect ?: $returnUrl);
        }

        if ('credit_card' === $config->getPaymentMethod()) {
            $init = $this->client->request('POST', $this->urls['api'].'/v1/transactions/secureFields', [
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'auth' => [$this->merchantId, $this->password],
                'json' => [
                    'amount' => $price->getAmount()->mul(100)->asNumeric(),
                    'currency' => $price->getCurrency()->getShortName(),
                    'returnUrl' => $returnUrl,
                ],
            ]);
            $jsonResponse = json_decode($init->getBody()->getContents(), true);
            $transactionId = $jsonResponse['transactionId'];

            $html = $this->container->get('twig')?->render('@PixelartPaymentProviderDatatrans/credit_card.html.twig', [
                'config' => $config,
                'transaction_id' => $transactionId,
            ]);

            $response = new SnippetResponse($order, $html ?? '');
        }

        if ('pay_button' === $config->getPaymentMethod()) {
            $displayItems = [];
            foreach ($order->getItems() as $item) {
                $displayItems[] = ['label' => ($item->getAmount() > 1 ? $item->getAmount().'x' : '').$item->getProductName(), 'amount' => ['value' => $item->getTotalPrice(), 'currency' => $price->getCurrency()->getShortName()]];
            }

            /**
             * @todo add shipping from price modifications to $displayItems[]
             * @todo add discount from price modifications to $displayItems[]
             */
            $html = $this->container->get('twig')?->render('@PixelartPaymentProviderDatatrans/pay_button.html.twig', [
                'config' => $config,
                'script' => $this->urls['payment_button_js'],
                'merchant_id' => $this->merchantId,
                'return_url' => $this->signUrl($config->getReturnUrl()),
                'refno' => $refNo,
                'total' => Decimal::create($order->getTotalPrice())->asString(),
                'currency' => $price->getCurrency()->getShortName(),
                'country' => $order->getCustomerCountry() ?: 'AT', // @todo remove the default AT
                'allowed_cards' => ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA'], // @todo in yaml config or builder
                'display_items' => $displayItems,
            ]);

            $response = new SnippetResponse($order, $html ?? '');
        }

        if ('paypal_button' === $config->getPaymentMethod()) {
            $html = $this->container->get('twig')?->render('@PixelartPaymentProviderDatatrans/paypal_button.html.twig', [
                'config' => $config,
                'script' => $this->urls['paypal_button_js'],
                'merchant_id' => $this->merchantId,
                'return_url' => $returnUrl,
                'refno' => $refNo,
                //                'total' => Decimal::create($order->getTotalPrice())->asNumeric(),
                'total' => '20.55',
                'currency' => $price->getCurrency()->getShortName(),
                'country' => $order->getCustomerCountry() ?: 'AT', // @todo remove the default AT
            ]);

            $response = new SnippetResponse($order, $html ?? '');
        }

        if ('twint' === $config->getPaymentMethod()) {
            $init = $this->client->request('POST', $this->urls['api'].'/v1/transactions', [
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'auth' => [$this->merchantId, $this->password],
                'json' => [
                    'amount' => Decimal::create($order->getTotalPrice())->mul(100)->asNumeric(),
                    'currency' => $price->getCurrency()->getShortName(),
                    'refno' => $refNo,
                    'language' => $config->getLanguage() ?? 'en',
                    'paymentMethods' => ['TWI'],
                    'redirect' => [
                        'successUrl' => $returnUrl,
                        'cancelUrl' => $config->getCancelUrl(),
                        'errorUrl' => $config->getErrorUrl(),
                    ],
                ],
            ]);

            $jsonResponse = json_decode($init->getBody()->getContents(), true);
            $transactionId = $jsonResponse['transactionId'];
            $response = new UrlResponse($order, $this->urls['redirect'].$transactionId);
        }

        if ('klarna' === $config->getPaymentMethod()) {
            $articles = [];

            // Add modification to articles
            foreach ($order->getPriceModifications()?->getItems() as $modification) {
                if (!$modification->getAmount()) {
                    continue;
                }

                // physical, discount, shipping_fee, sales_tax, digital, gift_card, store_credit, surcharge
                $type = '';
                if ('shipping' === $modification->getName()) {
                    $type = 'shipping_fee';
                } elseif ($modification->getAmount() < 0) {
                    $type = 'discount';
                } elseif ($modification->getAmount() > 0) {
                    $type = 'surcharge';
                }

                /** @todo does not consider multiple tax percentage especially when added one-after-other (e.g. Quebec, CA) */
                $taxPercentage = ($modification->getAmount() - $modification->getNetAmount()) * 100 / $modification->getNetAmount();
                $articles[] = [
                    'id' => $modification->getIndex(),
                    'name' => $modification->getName(),
                    'taxPercent' => $taxPercentage,
                    'price' => Decimal::create($modification->getAmount())->mul(100)->asNumeric(),
                    'quantity' => 1,
                    'type' => $type,
                ];
            }

            // Add order items to articles
            foreach ($order->getItems() as $item) {
                $itemQuantity = $item->getAmount();
                $itemPrice = Decimal::create($item->getTotalPrice())->mul(100)->div($itemQuantity)->asNumeric();
                $articles[] = [
                    'id' => $item->getProductNumber(),
                    'name' => $item->getProductName(),
                    // @todo does not consider multiple tax percentage especially when added one-after-other (e.g. Quebec, CA)
                    'taxPercent' => isset($item->getTaxInfo()[0][1]) ? rtrim($item->getTaxInfo()[0][1], '%') : 0,
                    'price' => $itemPrice,
                    'quantity' => $itemQuantity,
                ];
            }

            $init = $this->client->request('POST', $this->urls['api'].'/v1/transactions', [
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'auth' => [$this->merchantId, $this->password],
                'json' => [
                    'amount' => Decimal::create($order->getTotalPrice())->mul(100)->asNumeric(),
                    'currency' => $price->getCurrency()->getShortName(),
                    'refno' => $refNo,
                    'customer' => [
                        'country' => $order->getCustomerCountry() ?: 'AT', // @todo remove the default AT
                    ],
                    'language' => $config->getLanguage() ?? 'en',
                    'paymentMethods' => ['KLN'],
                    'order' => [
                        'articles' => $articles,
                    ],
                    'redirect' => [
                        'successUrl' => $returnUrl,
                        'cancelUrl' => $config->getCancelUrl(),
                        'errorUrl' => $config->getErrorUrl(),
                    ],
                ],
            ]);

            $jsonResponse = json_decode($init->getBody()->getContents(), true);
            $transactionId = $jsonResponse['transactionId'];
            $response = new UrlResponse($order, $this->urls['redirect'].$transactionId);
        }

        return $response;
    }

    public function handleResponse($response): StatusInterface
    {
        $transactionId = $response['datatransTrxId'] ?? $response['uppTransactionId'];
        $refNo = $response['orderIdent'];

        // @todo validate signed URLs (issue with Klarna adding extra parameters to the URL upon return.... need to validate only the URL without query params).
        //        $url = $this->requestStack->getMainRequest()?->getUri();
        //        if (!$this->checkSignature($url)) {
        //            throw new InvalidSignatureException('Invalid signature.');
        //        }

        try {
            $transaction = $this->client->request('GET', $this->urls['api'].'/v1/transactions/'.$transactionId, [
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'auth' => [$this->merchantId, $this->password],
            ]);

            $transaction = json_decode($transaction->getBody()->getContents(), true);

            // When a transaction was initialized. A transaction is initialized after a successful init request. This status is only set for customer-initiated flows before consumers start their payment via our payment forms.
            if ('initialized' === $transaction['status']) {
                $paymentState = AbstractOrder::ORDER_STATE_PAYMENT_INIT;
            }

            // When a transaction was authenticated. This status is only set if you defer the authorization from the authentication.
            if ('authenticated' === $transaction['status']) {
                $request = $this->client->request('POST', $this->urls['api'].'/v1/transactions/'.$transactionId.'/authorize', [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=UTF-8',
                    ],
                    'auth' => [$this->merchantId, $this->password],
                    'json' => [
                        'refno' => $refNo,
                        'autoSettle' => $this->autoSettle,
                    ],
                ]);
                $data = json_decode($request->getBody()->getContents(), true);

                /** @todo check $data if successful */
                $paymentState = AbstractOrder::ORDER_STATE_COMMITTED;
            }

            // When a transaction was authorized. This status is only set if you defer the settlement from the authorization.
            if ('authorized' === $transaction['status']) {
                $request = $this->client->request('POST', $this->urls['api'].'/v1/transactions/'.$transactionId.'/settle', [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=UTF-8',
                    ],
                    'auth' => [$this->merchantId, $this->password],
                    'json' => [
                        'amount' => $transaction['detail']['authorize']['amount'],
                        'currency' => $transaction['currency'],
                        'refno' => $refNo,
                        'autoSettle' => $this->autoSettle,
                    ],
                ]);
                $data = json_decode($request->getBody()->getContents(), true);

                /** @todo check $data if successful */
                $paymentState = StatusInterface::STATUS_CLEARED;
            }

            // When a transaction was settled partially or fully.
            if ('settled' === $transaction['status']) {
                $paymentState = AbstractOrder::ORDER_STATE_COMMITTED;
            }

            // When a transaction was transmitted to the acquirer for processing. This is automatically set by our system.
            if ('transmitted' === $transaction['status']) {
                $paymentState = AbstractOrder::ORDER_STATE_COMMITTED;
            }

            // When a transaction was canceled by the user or automatically by the system after a time-out occurred on our payment forms.
            if ('canceled' === $transaction['status']) {
                $paymentState = AbstractOrder::ORDER_STATE_ABORTED;
            }

            // When a transaction failed
            if ('failed' === $transaction['status']) {
                $paymentState = AbstractOrder::ORDER_STATE_ABORTED;
            }
        } catch (\Exception $e) {
            // @todo log error
            throw $e;
        }

        return new Status(
            $refNo,
            $transactionId,
            $data['error']['message'] ?? '',
            $paymentState,
            [
                'datatrans_amount' => (string) $transaction['detail']['authorize']['amount'],
                'datatrans_acqAuthorizationCode' => $data['acquirerAuthorizationCode'] ?? '',
            ]
        );
    }

    public function getAuthorizedData(): array
    {
        return $this->authorizedData;
    }

    public function setAuthorizedData(array $authorizedData): void
    {
        $this->authorizedData = $authorizedData;
    }

    // Clear an authorization
    public function clearCharge(): bool
    {
        // https://api-reference.datatrans.ch/#tag/v1transactions/operation/settle
        throw new NotImplementedException('settleCharge is not implemented yet.');
    }

    // Cancel an authorization
    public function cancelCharge(): bool
    {
        // https://api-reference.datatrans.ch/#tag/v1transactions/operation/cancel
        throw new NotImplementedException('cancelCharge is not implemented yet.');
    }

    // Refund a charge
    public function refundCharge(): bool
    {
        // https://api-reference.datatrans.ch/#tag/v1transactions/operation/credit
        throw new NotImplementedException('refundCharge is not implemented yet.');
    }

    public function executeDebit(PriceInterface $price = null, $reference = null): StatusInterface
    {
        throw new NotImplementedException('executeDebit is not implemented yet.');
    }

    public function executeCredit(PriceInterface $price, $reference, $transactionId): StatusInterface
    {
        throw new NotImplementedException('executeCredit is not implemented yet.');
    }

    protected function processOptions(array $options): void
    {
        parent::processOptions($options);

        $this->configurationKey = $options['configuration_key'] ?? ''; // @todo test when value is not set
        $this->merchantId = $options['merchant_id'];
        $this->password = $options['password'];
        $this->sign = $options['sign'];
        $this->autoSettle = $options['auto_settle'];

        // set endpoint depending on mode
        if ('live' === $options['mode']) {
            $this->urls = array_merge($this->urls, [
                'api' => 'https://api.datatrans.com',
                'secure_fields_js' => 'https://pay.datatrans.com/upp/payment/js/secure-fields-2.0.0.min.js',
                'payment_button_js' => 'https://pay.datatrans.com/upp/payment/js/payment-button-2.0.0.js',
                'paypal_button_js' => 'https://pay.datatrans.com/upp/payment/js/paypal-button-1.0.0.js',
                'redirect' => 'https://pay.datatrans.com/v1/start/',
                'lightbox_js' => 'https://pay.datatrans.com/upp/payment/js/datatrans-2.0.0.js', // not implemented
            ]);
        } else {
            $this->urls = array_merge($this->urls, [
                'api' => 'https://api.sandbox.datatrans.com',
                'secure_fields_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/secure-fields-2.0.0.min.js',
                'payment_button_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/payment-button-2.0.0.js',
                'paypal_button_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/paypal-button-1.0.0.js',
                'redirect' => 'https://pay.sandbox.datatrans.com/v1/start/',
                'lightbox_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/datatrans-2.0.0.js', // not implemented
            ]);
        }
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        parent::configureOptions($resolver);

        $resolver->setRequired([
            'merchant_id',
            'password',
            'sign',
        ]);

        $resolver
            ->setDefault('mode', 'sandbox')
            ->setAllowedValues('mode', ['sandbox', 'live'])
        ;

        $resolver
            ->setDefined('auto_settle')
            ->setAllowedTypes('auto_settle', ['bool'])
        ;

        $resolver
            ->setDefined('configuration_key')
            ->setAllowedTypes('configuration_key', ['string'])
        ;

        $notEmptyValidator = static function ($value) {
            return !empty($value);
        };

        foreach ($resolver->getRequiredOptions() as $requiredProperty) {
            $resolver->setAllowedValues($requiredProperty, $notEmptyValidator);
        }

        return $resolver;
    }
}
