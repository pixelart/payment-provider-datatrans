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

namespace Pixelart\PaymentProviderDatatransBundle\PaymentManager\V7\Payment\StartPaymentRequest;

use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentRequest\AbstractRequest;

class DatatransRequest extends AbstractRequest
{
    protected $paymentMethod;
    protected $returnUrl;
    protected $reqtype;
    protected $refno;
    protected $language;
    protected $errorUrl;
    protected $cancelUrl;
    protected $useAlias;
    protected $merchantName;
    protected $name;

    public function setPaymentMethod($paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    public function setReturnUrl($returnUrl): self
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * @return mixed
     */
    public function getReqtype()
    {
        return $this->reqtype;
    }

    /**
     * @param mixed $reqtype
     */
    public function setReqtype($reqtype): self
    {
        $this->reqtype = $reqtype;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRefno()
    {
        return $this->refno;
    }

    /**
     * @param mixed $refno
     */
    public function setRefno($refno): self
    {
        $this->refno = $refno;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param mixed $language
     */
    public function setLanguage($language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getErrorUrl()
    {
        return $this->errorUrl;
    }

    /**
     * @param mixed $errorUrl
     */
    public function setErrorUrl($errorUrl): self
    {
        $this->errorUrl = $errorUrl;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    /**
     * @param mixed $cancelUrl
     */
    public function setCancelUrl($cancelUrl): self
    {
        $this->cancelUrl = $cancelUrl;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUseAlias()
    {
        return $this->useAlias;
    }

    /**
     * @param mixed $useAlias
     */
    public function setUseAlias($useAlias): self
    {
        $this->useAlias = $useAlias;

        return $this;
    }

    public function setMerchantName($merchantName): self
    {
        $this->merchantName = $merchantName;

        return $this;
    }

    public function getMerchantName(): string
    {
        return $this->merchantName;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
