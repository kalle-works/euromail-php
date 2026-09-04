<?php

namespace EuroMail\Types;

class EmailDetails extends SentEmail
{
    /** @var array<int, array<string, mixed>> */
    public array $events;

    /**
     * @param string[] $to
     * @param array<int, array<string, mixed>> $events
     * @param array<string, mixed> $raw
     */
    public function __construct(
        ?string $id = null,
        ?string $messageId = null,
        ?string $status = null,
        array $to = [],
        bool $sandbox = false,
        ?string $scheduledAt = null,
        ?string $createdAt = null,
        array $events = [],
        array $raw = []
    ) {
        parent::__construct($id, $messageId, $status, $to, $sandbox, $scheduledAt, $createdAt, $raw);
        $this->events = $events;
    }

    /**
     * @param array<string, mixed> $data
     */
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
            $data['events'] ?? [],
            $data
        );
    }
}
