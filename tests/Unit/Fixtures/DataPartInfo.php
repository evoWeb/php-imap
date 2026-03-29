<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    protected ?string $data;

    public function fetch(): string
    {
        return $this->decodeAfterFetch($this->data);
    }

    public function setData(?string $data = null): void
    {
        $this->data = $data;
    }
}
