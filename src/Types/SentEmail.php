<?php

namespace EuroMail\Types;

class SentEmail
{
    public ?string $id;
    public ?string $messageId;
    public ?string $status;
    public array $to;
    public bool $sandbox;
    public ?string $scheduledAt;
    public ?string $createdAt;
    public array $raw;

    public function __construct(
        ?string $id = null,
        ?string $messageId = null,
        ?string $status = null,
        array $to = [],
        bool $sandbox = false,
        ?string $scheduledAt = null,
        ?string $createdAt = null,
        array $raw = []
    ) {
        $this->id = $id;
        $this->messageId = $messageId;
        $this->status = $status;
        $this->to = $to;
        $this->sandbox = $sandbox;
        $this->scheduledAt = $scheduledAt;
        $this->createdAt = $createdAt;
        $this->raw = $raw;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['message_id'] ?? null,
            $data['status'] ?? null,
            self::normalizeTo($data['to'] ?? null),
            (bool) ($data['sandbox'] ?? false),
            $data['scheduled_at'] ?? null,
            $data['created_at'] ?? null,
            $data
        );
    }

    protected static function normalizeTo($to): array
    {
        if (is_string($to)) {
            return [$to];
        }

        if (is_array($to)) {
            return $to;
        }

        return [];
    }
}
