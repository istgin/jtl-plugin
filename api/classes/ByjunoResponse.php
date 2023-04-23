<?php
/**
 * Created by PhpStorm.
 * User: i.sutugins
 * Date: 14.4.9
 * Time: 16:57
 */

class ByjunoResponse
{

    private $rawResponse;

    /**
     * @param mixed $rawResponse
     */
    public function setRawResponse($rawResponse)
    {
        $this->rawResponse = $rawResponse;
    }

    /**
     * @return mixed
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * @param mixed $ClientId
     */
    public function setClientId($ClientId)
    {
        $this->ClientId = $ClientId;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->ClientId;
    }

    /**
     * @param mixed $CustomerLastStatusChange
     */
    public function setCustomerLastStatusChange($CustomerLastStatusChange)
    {
        $this->CustomerLastStatusChange = $CustomerLastStatusChange;
    }

    /**
     * @return mixed
     */
    public function getCustomerLastStatusChange()
    {
        return $this->CustomerLastStatusChange;
    }

    /**
     * @param mixed $CustomerProcessingInfoClassification
     */
    public function setCustomerProcessingInfoClassification($CustomerProcessingInfoClassification)
    {
        $this->CustomerProcessingInfoClassification = $CustomerProcessingInfoClassification;
    }

    /**
     * @return mixed
     */
    public function getCustomerProcessingInfoClassification()
    {
        return $this->CustomerProcessingInfoClassification;
    }

    /**
     * @param mixed $CustomerProcessingInfoCode
     */
    public function setCustomerProcessingInfoCode($CustomerProcessingInfoCode)
    {
        $this->CustomerProcessingInfoCode = $CustomerProcessingInfoCode;
    }

    /**
     * @return mixed
     */
    public function getCustomerProcessingInfoCode()
    {
        return $this->CustomerProcessingInfoCode;
    }

    /**
     * @param mixed $CustomerProcessingInfoDescription
     */
    public function setCustomerProcessingInfoDescription($CustomerProcessingInfoDescription)
    {
        $this->CustomerProcessingInfoDescription = $CustomerProcessingInfoDescription;
    }

    /**
     * @return mixed
     */
    public function getCustomerProcessingInfoDescription()
    {
        return $this->CustomerProcessingInfoDescription;
    }

    /**
     * @param mixed $CustomerRequestStatus
     */
    public function setCustomerRequestStatus($CustomerRequestStatus)
    {
        $this->CustomerRequestStatus = $CustomerRequestStatus;
    }

    /**
     * @return mixed
     */
    public function getCustomerRequestStatus()
    {
        return $this->CustomerRequestStatus;
    }

    /**
     * @param mixed $ProcessingInfoClassification
     */
    public function setProcessingInfoClassification($ProcessingInfoClassification)
    {
        $this->ProcessingInfoClassification = $ProcessingInfoClassification;
    }

    /**
     * @return mixed
     */
    public function getProcessingInfoClassification()
    {
        return $this->ProcessingInfoClassification;
    }

    /**
     * @param mixed $ProcessingInfoCode
     */
    public function setProcessingInfoCode($ProcessingInfoCode)
    {
        $this->ProcessingInfoCode = $ProcessingInfoCode;
    }

    /**
     * @return mixed
     */
    public function getProcessingInfoCode()
    {
        return $this->ProcessingInfoCode;
    }

    /**
     * @param mixed $ProcessingInfoDescription
     */
    public function setProcessingInfoDescription($ProcessingInfoDescription)
    {
        $this->ProcessingInfoDescription = $ProcessingInfoDescription;
    }

    /**
     * @return mixed
     */
    public function getProcessingInfoDescription()
    {
        return $this->ProcessingInfoDescription;
    }

    /**
     * @param mixed $ResponseId
     */
    public function setResponseId($ResponseId)
    {
        $this->ResponseId = $ResponseId;
    }

    /**
     * @return mixed
     */
    public function getResponseId()
    {
        return $this->ResponseId;
    }

    /**
     * @param mixed $Version
     */
    public function setVersion($Version)
    {
        $this->Version = $Version;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->Version;
    }
    private $ResponseId;
    private $Version;
    private $ClientId;

    private $ProcessingInfoCode;
    private $ProcessingInfoClassification;
    private $ProcessingInfoDescription;

    private $CustomerRequestStatus;

    /**
     * @return mixed
     */
    public function getTransactionNumber()
    {
        return $this->TransactionNumber;
    }

    /**
     * @param mixed $TransactionNumber
     */
    public function setTransactionNumber($TransactionNumber)
    {
        $this->TransactionNumber = $TransactionNumber;
    }

    private $CustomerLastStatusChange;
    private $CustomerProcessingInfoCode;
    private $CustomerProcessingInfoClassification;
    private $CustomerProcessingInfoDescription;

    private $TransactionNumber;

    public function processResponse()
    {
        $xml = simplexml_load_string($this->rawResponse);

        $this->ResponseId = (int)$xml["ResponseId"];
        $this->Version = (double)$xml["Version"];
        $this->ClientId = (int)$xml["ClientId"];

        $this->ProcessingInfoCode = trim((string)$xml->ProcessingInfo->Code);
        $this->ProcessingInfoClassification = trim((string)$xml->ProcessingInfo->Classification);
        if ($this->ProcessingInfoClassification == 'ERR') {
            $this->CustomerRequestStatus = 0;
            return;
        }
        $this->ProcessingInfoDescription = trim((string)$xml->ProcessingInfo->Description);

        $this->CustomerRequestStatus = (int)$xml->Customer->RequestStatus;
        $this->TransactionNumber = trim((string)$xml->Customer->TransactionNumber);
        $this->CustomerLastStatusChange = trim((string)$xml->Customer->RequestStatus);
        $this->CustomerProcessingInfoCode = trim((string)$xml->Customer->ProcessingInfo->Code);
        $this->CustomerProcessingInfoClassification = trim((string)$xml->Customer->ProcessingInfo->Classification);
        $this->CustomerProcessingInfoDescription = trim((string)$xml->Customer->ProcessingInfo->Description);

    }

}