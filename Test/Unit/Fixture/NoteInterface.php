<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Api\Data\TagInterface;

/**
 * The interface the preference test asks the plugin to convert to; the ObjectManager configuration maps it
 * to Note, whose constructor is then the one reflected.
 */
interface NoteInterface
{
    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void;

    /**
     * @return TagInterface|null
     */
    public function getTag(): ?TagInterface;

    /**
     * @param TagInterface $tag
     * @return void
     */
    public function setTag(TagInterface $tag): void;

    /**
     * @return Attachment|null
     */
    public function getAttachment(): ?Attachment;

    /**
     * @param Attachment $attachment
     * @return void
     */
    public function setAttachment(Attachment $attachment): void;
}
