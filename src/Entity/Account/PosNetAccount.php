<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Entity\Account;

/**
 * PosNetAccount
 */
class PosNetAccount extends AbstractPosAccount
{
    /** @var string */
    private $terminalId;

    /** @var string */
    private $posNetId;

    public function __construct(
        string $bank,
        string $model,
        string $clientId,
        string $username,
        string $password,
        string $lang,
        string $terminalId,
        string $posNetId,
        ?string $storeKey = null
    ) {
        parent::__construct($bank, $model, $clientId, $username, $password, $lang, $storeKey);
        $this->model = $model;
        $this->terminalId = $terminalId;
        $this->posNetId = $posNetId;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @return string
     */
    public function getPosNetId(): string
    {
        return $this->posNetId;
    }
}
