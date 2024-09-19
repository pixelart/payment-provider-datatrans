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

namespace Pixelart\PaymentProviderDatatransBundle\Command;

use GuzzleHttp\Client;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CommitOrderProcessorLocatorInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderManagerLocatorInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Status;
use Pixelart\PaymentProviderDatatransBundle\PaymentManager\Payment\Datatrans;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'datatrans:check-pending-payment-orders',
    description: 'Check payment pending orders for updates'
)]
class CheckPendingPaymentOrdersCommand extends Command
{
    public function __construct(
        private Factory $factory,
        private CommitOrderProcessorLocatorInterface $commitOrderProcessors,
        private OrderManagerLocatorInterface $orderManagers,
        $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('provider', InputArgument::OPTIONAL, 'The Pimcore payment_manager provider (e.g. datatrans)', 'datatrans')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providerString = $input->getArgument('provider');
        $provider = $this->factory->getPaymentManager()->getProvider($providerString);

        if (!$provider instanceof Datatrans) {
            return Command::FAILURE;
        }

        $dateTime = new \DateTime();
        $dateTime->sub(new \DateInterval('PT1H'));
        $timestamp = $dateTime->getTimestamp();

        $orderManager = $this->orderManagers->getOrderManager();

        // Abort orders with payment pending
        $list = $orderManager->buildOrderList();
        $list->setCondition(
            'orderState = ? AND o_modificationDate < ?',
            [AbstractOrder::ORDER_STATE_PAYMENT_PENDING, $timestamp]
        );

        foreach ($list as $order) {
            $paymentInfo = $order->getLastPaymentInfo();
            $internalPaymentId = $paymentInfo?->getInternalPaymentId();
            $transactionId = $paymentInfo?->getPaymentReference();

            if (!$transactionId || !$internalPaymentId) {
                continue;
            }

            $resourcePath = sprintf('/v1/transactions/%s', $transactionId);
            $client = new Client(
                [
                    'base_uri' => $provider->getUrls()['api'],
                    'headers' => [
                        'Content-Type' => 'application/json; charset=UTF-8',
                    ],
                    'auth' => [$provider->getMerchantId(), $provider->getPassword()],
                ]
            );
            $response = $client->request('get', $resourcePath);
            $jsonResponse = json_decode($response->getBody()->getContents(), true);

            if ('transmitted' === $jsonResponse['status']) {
                $commitOrderProcessor = $this->commitOrderProcessors->getCommitOrderProcessor();

                $status = new Status(
                    $internalPaymentId,
                    $transactionId,
                    'Payment updated from CheckPendingPaymentOrdersCommand',
                    Status::STATUS_CLEARED,
                    $jsonResponse
                );

                $commitOrderProcessor->commitOrderPayment($status, $provider, $order);
            }
        }

        return Command::SUCCESS;
    }
}
