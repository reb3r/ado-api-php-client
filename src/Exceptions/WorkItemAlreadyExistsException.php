<?php

namespace Reb3r\ADOAPC\Exceptions;

use Exception;
use Reb3r\ADOAPC\Models\AzureDevOpsWorkitem;

class WorkItemAlreadyExistsException extends Exception
{
    /** @var AzureDevOpsWorkitem */
    public $workItem;

    public function setWorkitem(AzureDevOpsWorkitem $workItem): void
    {
        $this->workItem = $workItem;
    }
}
