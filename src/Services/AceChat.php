<?php
declare(strict_types=1);

namespace Geotab\Services;

use Fiber;
use Geotab\Client;
use Geotab\Core\Reactor;
use Geotab\Models\Errors\AceException;
use RuntimeException;

class AceChat {
    private readonly string $serviceName;
    private ?string $currentChatId = null;

    public function __construct(
        private Client $client,
        private bool $verbose = false
    ) {
        $this->serviceName = 'dna-planet-orchestration';
    }

    /**
     * The current chat ID. Null until the first ask() call.
     */
    public ?string $chatId {
        get => $this->currentChatId;
    }

    /**
     * Ask Ace a question. Suspends the calling fiber until the response is DONE.
     * Subsequent calls on the same instance continue the same conversation thread.
     *
     * @param string   $prompt    The question to ask Ace.
     * @param callable $onMessage Fires for each new message as it arrives.
     *                            In verbose mode, COTMessages are included.
     *                            In standard mode, only AssistantMessage and UserDataReference fire.
     */
    public function ask(string $prompt, callable $onMessage): void {
        $parentFiber = Fiber::getCurrent()
            ?? throw new RuntimeException("ask() must be called within a Fiber.");

        Reactor::getInstance()->register(function() use ($parentFiber, $prompt, $onMessage) {
            try {
                // Lazy chat creation — no API call until the first ask()
                if ($this->chatId === null) {
                    $this->currentChatId = $this->createChat();
                }

                // Send the prompt, get back a message_group_id
                $messageGroupId = $this->sendPrompt($prompt);

                // Poll until DONE or FAILED
                $this->pollMessageGroup($messageGroupId, $onMessage);

            } finally {
                if ($parentFiber->isSuspended()) {
                    $parentFiber->resume();
                }
            }
        });

        Fiber::suspend();
    }

    /**
     * Creates a new chat and returns the chat_id.
     */
    private function createChat(): string {
        $res = $this->aceCall('create-chat', []);
        echo json_encode($res->data, JSON_PRETTY_PRINT) . "\n";
        $result = $res->data[0] ?? throw new RuntimeException('Ace create-chat returned no result.');
        return $result['chat_id']
            ?? throw new RuntimeException('Could not find chat_id in create-chat response.');
    }

    /**
     * Sends a prompt to the current chat and returns the message_group_id.
     */
    private function sendPrompt(string $prompt): string {
        $res = $this->aceCall('send-prompt', [
            'chat_id' => $this->chatId,
            'prompt'  => $prompt
        ]);

        //echo json_encode($res->data, JSON_PRETTY_PRINT) . PHP_EOL;//TODO:Remove before submission.

        $result = $res->data[0] ?? throw new RuntimeException('Ace send-prompt returned no result.');

        // Check for apiResult errors
        $errors = $result['errors'] ?? [];
        if (!empty($errors)) {
            throw AceException::fromApiError($this->chatId, $errors[0]);
        }

        return $result['message_group']['id']
            ?? throw new RuntimeException('Could not find message_group_id in send-prompt response.');
    }

    /**
     * Polls get-message-group until DONE or FAILED.
     * Fires onMessage for each new message respecting verbosity setting.
     */
    private function pollMessageGroup(string $messageGroupId, callable $onMessage): void {
        $seen    = []; // message IDs already fired
        $history = []; // ordered record of all messages
        $this->client->wait(10);
        while (true) {
            $res = $this->aceCall('get-message-group', [
                'chat_id'          => $this->chatId,
                'message_group_id' => $messageGroupId
            ]);

            $result = $res->data[0] ?? throw new RuntimeException('Ace get-message-group returned no result.');

            // Check for apiResult errors
            $errors = $result['errors'] ?? [];
            if (!empty($errors)) {
                throw AceException::fromApiError($this->chatId, $errors[0]);
            }

            $messageGroup = $result['message_group']
                ?? throw new RuntimeException('Could not find message_group in response.');

            $status = $messageGroup['status']['status'] ?? 'PROCESSING';

            // Process new messages
            $messages = $messageGroup['messages'] ?? [];
            foreach ($messages as $id => $message) {
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $history[] = $message;

                // Respect verbosity — COTMessages only fire in verbose mode
                if ($message['type'] === 'COTMessage' && !$this->verbose) {
                    continue;
                }

                $onMessage((object)$message);
            }

            if ($status === 'DONE') {
                break;
            }

            if ($status === 'FAILED') {
                throw AceException::fromStatus(
                    $this->chatId,
                    $messageGroupId,
                    $messageGroup['status']
                );
            }

            // Still PROCESSING — wait 8 seconds before next poll
            $this->client->wait(8);
        }
    }

    /**
     * Wraps a GetAceResults call with the standard Ace envelope.
     */
    private function aceCall(string $functionName, array $functionParameters): \Geotab\Models\Response {
        return $this->client->call(
            method: 'GetAceResults',
            params: [
                'serviceName'        => $this->serviceName,
                'functionName'       => $functionName,
                'customerData'       => true,
                'functionParameters' => empty($functionParameters) 
                    ? new \stdClass() 
                    : $functionParameters
            ],
            responseClass: \Geotab\Models\AceResponse::class
        );
    }
}