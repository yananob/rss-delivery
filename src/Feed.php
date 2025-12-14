<?php

declare(strict_types=1);

namespace App;

class Feed
{
    private string $id;
    private string $name;
    private string $url;
    private string $notify_method;
    private ?string $notify_bot;

    public function __construct(
        string $id,
        string $name,
        string $url,
        string $notify_method,
        ?string $notify_bot
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->notify_method = $notify_method;
        $this->notify_bot = $notify_bot;
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
