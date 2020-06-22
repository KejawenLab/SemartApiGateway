<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Aggregate;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
interface AggregateRequestInterface
{
    public function handle(Request $request): Response;
}
