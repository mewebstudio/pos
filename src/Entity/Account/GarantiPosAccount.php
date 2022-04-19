<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Entity\Account;

/**
 * GarantiPosAccount
 */
class GarantiPosAccount extends AbstractPosAccount
{
    /**
     * @var string
     */
    private $terminalId;
    /**
     * @var string
     */
    private $refundUsername;
    /**
     * @var string
     */
    private $refundPassword;

    public function __construct(
        string $bank,
        string $model,
        string $merchantId,
        string $username,
        string $password,
        string $lang,
        string $terminalId,
        ?string $storeKey = null,
        ?string $refundUsername = null,
        ?string $refundPassword = null
    ) {
        parent::__construct($bank, $model, $merchantId, $username, $password, $lang, $storeKey);
        $this->model = $model;
        $this->terminalId = $terminalId;
        $this->refundUsername = $refundUsername;
        $this->refundPassword = $refundPassword;
    }

    /**
     * @return string
     */
    public function getRefundPassword(): string
    {
        return $this->refundPassword;
    }

    /**
     * @return string
     */
    public function getRefundUsername(): string
    {
        return $this->refundUsername;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }
}
