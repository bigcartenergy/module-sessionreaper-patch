<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

use Magento\Framework\Api\ExtensionAttributesInterface;

/**
 * Stands in for a generated extension attributes interface: the processor converts it through the class
 * whose name drops the "Interface" suffix, and so must the plugin.
 */
interface NoteExtensionInterface extends ExtensionAttributesInterface
{
    /**
     * @return string|null
     */
    public function getRemark(): ?string;

    /**
     * @param string $remark
     * @return void
     */
    public function setRemark(string $remark): void;
}
