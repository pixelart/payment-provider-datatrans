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

use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderAgentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment\AbstractPayment;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\StatusInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\PaymentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentRequest\AbstractRequest;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentResponse\StartPaymentResponseInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\PriceInterface;
use Pimcore\PaymentProviderDatatransBundle\Exception\NotImplementedException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Datatrans extends AbstractPayment implements PaymentInterface
{
    private string $merchantId;
    private string $password;
    private string $sign;
    private array $urls = [];
    private array $authorizedData = [];

    public function __construct(array $options)
    {
        $this->processOptions(
            $this->configureOptions(new OptionsResolver())->resolve($options)
        );
    }

    public function getName(): string
    {
        return 'Datatrans';
    }

    public function startPayment(OrderAgentInterface $orderAgent, PriceInterface $price, AbstractRequest $config): StartPaymentResponseInterface
    {
        // Determine the payment method (Credit Card, Google/Apple pay button, Paypal button, redirect e.g. Klarna/Twint)

        $test = 1;

        exit;
        // #1: Credit Card (credit_card)
        // Get or initialize a transactionId
        //  https://api-reference.datatrans.ch/#tag/v1transactions/operation/secureFieldsInit
        // Return the payment Form/Snippet response

        // #2: Google/Apple pay button (pay_button)
        // Return the payment Snippet response

        // #3: Paypal button (paypal_button)
        // Return the payment Snippet response

        // #4: Redirect e.g. Klarna/Twint (klarna or twint)
        // Get or initialize a transactionId
        //  https://api-reference.datatrans.ch/#tag/v1transactions/operation/init
        // Return the payment Redirect response

        // @todo map payment_method to Response Type: [secure_field, redirect, lightbox, payment_button, paypal_button]
    }

    public function handleResponse($response): void
    {
        // If 3D secure, then handle the response [https://docs.datatrans.ch/docs/3d-secure#3d-secure-response]
        // Finalize the transaction
        // https://api-reference.datatrans.ch/#tag/v1transactions/operation/authorize-split
        // Allow overriding the default yaml config for autoSettle

        // How do we verify the payment (prevent tampering or fake success response)?
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

        // set endpoint depending on mode
        if ('live' === $options['mode']) {
            $this->urls = array_merge($this->urls, [
                'api' => 'https://api.datatrans.com',
                'secure_fields_js' => 'https://pay.datatrans.com/upp/payment/js/secure-fields-2.0.0.min.js',
                'payment_button_js' => 'https://pay.datatrans.com/upp/payment/js/payment-button-2.0.0.js',
                'paypal_button_js' => 'https://pay.datatrans.com/upp/payment/js/paypal-button-1.0.0.js',
                'lightbox_js' => 'https://pay.datatrans.com/upp/payment/js/datatrans-2.0.0.js', // not implemented
            ]);
        } else {
            $this->urls = array_merge($this->urls, [
                'api' => 'https://api.sandbox.datatrans.com',
                'secure_fields_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/secure-fields-2.0.0.min.js',
                'payment_button_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/payment-button-2.0.0.js',
                'paypal_button_js' => 'https://pay.sandbox.datatrans.com/upp/payment/js/paypal-button-1.0.0.js',
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

        $notEmptyValidator = static function ($value) {
            return !empty($value);
        };

        foreach ($resolver->getRequiredOptions() as $requiredProperty) {
            $resolver->setAllowedValues($requiredProperty, $notEmptyValidator);
        }

        return $resolver;
    }
}
