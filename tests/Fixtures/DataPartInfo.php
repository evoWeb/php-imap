<?php

declare(strict_types=1);

namespace PhpImap\Tests\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    protected bool|string|null $data;

    public function fetch(): string
    {
        return is_string($this->data) ? $this->decodeAfterFetch($this->data) : '';
    }

    public function setData(?string $data = null): void
    {
        $this->data = $data;
    }
}
