<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture;

use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Api\Data\TagInterface;

/**
 * A data class with one constructor parameter of each kind: a simple type (kept), an Api\Data type (kept),
 * a class outside Api\Data (removed) and the plain array every Magento data class takes (removed, as the
 * official fix skips it too). Every parameter has a matching setter, which the removed keys never reach.
 */
class Note implements NoteInterface
{
    /**
     * @var string
     */
    private $title;

    /**
     * @var Attachment|null
     */
    private $attachment;

    /**
     * @var TagInterface|null
     */
    private $tag;

    /**
     * @var array
     */
    private $data;

    /**
     * @param string $title
     * @param Attachment|null $attachment
     * @param TagInterface|null $tag
     * @param array $data
     */
    public function __construct(
        string $title = '',
        ?Attachment $attachment = null,
        ?TagInterface $tag = null,
        array $data = []
    ) {
        $this->title = $title;
        $this->attachment = $attachment;
        $this->tag = $tag;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @inheritdoc
     */
    public function getTag(): ?TagInterface
    {
        return $this->tag;
    }

    /**
     * @inheritdoc
     */
    public function setTag(TagInterface $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * @inheritdoc
     */
    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    /**
     * @inheritdoc
     */
    public function setAttachment(Attachment $attachment): void
    {
        $this->attachment = $attachment;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
