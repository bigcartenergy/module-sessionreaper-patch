<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Stands in for a generated extension attributes class: it takes the plain data array Magento's simple
 * objects take, and nothing else.
 */
class NoteExtension extends AbstractSimpleObject implements NoteExtensionInterface
{
    /**
     * @inheritdoc
     */
    public function getRemark(): ?string
    {
        $remark = $this->_get('remark');
        return $remark === null ? null : (string)$remark;
    }

    /**
     * @inheritdoc
     */
    public function setRemark(string $remark): void
    {
        $this->setData('remark', $remark);
    }
}
