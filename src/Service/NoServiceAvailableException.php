<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Throwable;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
class NoServiceAvailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No service available to handle this request');
    }
}
