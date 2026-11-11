<?php
declare(strict_types=1);

final class HttpFehler extends RuntimeException
{
    public function __construct(public readonly int $status, string $meldung = '')
    {
        parent::__construct($meldung);
    }
}
