<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

/**
 * A class whose constructor parameter is documented as "mixed": not a simple type and not an Api\Data type,
 * so a key naming it must be removed.
 */
class Loose
{
    /**
     * @var mixed
     */
    private $thing;

    /**
     * @param mixed $thing
     */
    public function __construct($thing = null)
    {
        $this->thing = $thing;
    }

    /**
     * @return mixed
     */
    public function getThing()
    {
        return $this->thing;
    }
}
