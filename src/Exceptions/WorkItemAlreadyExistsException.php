<?php

namespace Reb3r\ADOAPC\Exceptions;

use Exception;
use Reb3r\ADOAPC\Models\Workitem;

class WorkItemAlreadyExistsException extends Exception
{
    /** @var Workitem */
    public $workItem;

    public function setWorkitem(Workitem $workItem): void
    {
        $this->workItem = $workItem;
    }
}
