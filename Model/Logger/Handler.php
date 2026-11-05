<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Model\Logger;

use Magento\Framework\Logger\Handler\Base;

/**
 * Writes the module's log lines to var/log/bigcartenergy_cve-2025-54236.log, through Magento's standard
 * file handler.
 */
class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/bigcartenergy_cve-2025-54236.log';
}
