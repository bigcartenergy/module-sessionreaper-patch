<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

/**
 * A harmless class outside any Api\Data namespace. As a constructor parameter type it is not on the
 * allow-list, so a key naming such a parameter must be removed.
 */
class Attachment
{
    /**
     * @var string
     */
    private $label;

    /**
     * @param string $label
     */
    public function __construct(string $label = '')
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return void
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
