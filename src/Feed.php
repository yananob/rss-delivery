<?php

declare(strict_types=1);

namespace App;

class Feed
{
    public function __construct(
        private string $id,
        private string $name,
        private string $url,
        private string $notify_method,
        private ?string $notify_bot
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getNotifyMethod(): string
    {
        return $this->notify_method;
    }

    public function getNotifyBot(): ?string
    {
        return $this->notify_bot;
    }
}
