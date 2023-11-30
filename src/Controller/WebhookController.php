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

namespace Pixelart\PaymentProviderDatatransBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    #[Route('/_datatrans/webhook', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        // validate signature from the HTTP header Datatrans-Signature
        // update order state to paid and completed
    }

    private function verifySignature(string $timestamp, string $payload, string $signature): bool
    {
        /** @todo generate new signed key and set in ENV variable within the %parameters%. */
        $key = '786bd8210563a4b53e376716017e2ab0b16cef16330e0cd3ef4a5120118e1cc514dabe0b5e749ca8922a4d424c54b7359724d0d152bf4b7c2db80e31ed3ded70';

        // Create sign with timestamp and payload
        echo hash_hmac('sha256', $timestamp.$payload, hex2bin($key));
    }
}
