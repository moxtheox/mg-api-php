<?php
declare(strict_types=1);

namespace Geotab\Models\Errors;

class AceException extends GeotabError {
    public function __construct(
        public readonly string $chatId,
        public readonly string $messageGroupId,
        public readonly int $code,
        string $message
    ) {
        // Construct a minimal error envelope compatible with GeotabError::__construct
        parent::__construct([
            'error' => [
                'message' => $message,
                'data'    => ['type' => 'AceException']
            ]
        ]);
    }

    /**
     * Build from a FAILED message_group status block.
     */
    public static function fromStatus(string $chatId, string $messageGroupId, array $status): static {
        return new static(
            chatId: $chatId,
            messageGroupId: $messageGroupId,
            code: $status['code'] ?? 0,
            message: $status['message'] ?? 'Unknown Ace error'
        );
    }

    /**
     * Build from an apiResult errors array entry.
     */
    public static function fromApiError(string $chatId, array $error): static {
        return new static(
            chatId: $chatId,
            messageGroupId: '',
            code: $error['code'] ?? 0,
            message: $error['message'] ?? 'Unknown Ace API error'
        );
    }
}