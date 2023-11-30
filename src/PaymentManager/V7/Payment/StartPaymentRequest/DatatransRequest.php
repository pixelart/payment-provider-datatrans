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

class DatatransRequest extends AbstractRequest
{
    protected $reqtype;
    protected $refno;
    protected $language;
    protected $successUrl;
    protected $errorUrl;
    protected $cancelUrl;
    protected $uppStartTarget;
    protected $useAlias;

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
    public function setReqtype($reqtype): void
    {
        $this->reqtype = $reqtype;
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
    public function setRefno($refno): void
    {
        $this->refno = $refno;
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
    public function setLanguage($language): void
    {
        $this->language = $language;
    }

    /**
     * @return mixed
     */
    public function getSuccessUrl()
    {
        return $this->successUrl;
    }

    /**
     * @param mixed $successUrl
     */
    public function setSuccessUrl($successUrl): void
    {
        $this->successUrl = $successUrl;
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
    public function setErrorUrl($errorUrl): void
    {
        $this->errorUrl = $errorUrl;
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
    public function setCancelUrl($cancelUrl): void
    {
        $this->cancelUrl = $cancelUrl;
    }

    /**
     * @return mixed
     */
    public function getUppStartTarget()
    {
        return $this->uppStartTarget;
    }

    /**
     * @param mixed $uppStartTarget
     */
    public function setUppStartTarget($uppStartTarget): void
    {
        $this->uppStartTarget = $uppStartTarget;
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
    public function setUseAlias($useAlias): void
    {
        $this->useAlias = $useAlias;
    }
}
