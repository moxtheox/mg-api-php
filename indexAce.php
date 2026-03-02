<?php
require_once __DIR__ . '/vendor/autoload.php';

use Geotab\Client;
use Geotab\Models\Security\FileSessionProvider;
use Geotab\Services\AceChat;
use Geotab\Models\Errors\AceException;

echo "Starting" . PHP_EOL;

Client::create('demo_tsvl_las', function(Client $sdk) {
    $sdk->setSessionProvider(new FileSessionProvider('/usr/src/app/sessions'));
    $sdk->authenticate();

    echo "\nStarting Ace conversation...\n\n";

    $ace = new AceChat($sdk);

    try {
        $ace->ask(
            prompt: "How many Diesel Trucks are in my database?",
            onMessage: function($message) {
                match($message->type) {
                    'UserPrompt'       => print("[You] " . $message->content . "\n\n"),
                    'AssistantMessage' => print("[Ace] " . $message->content . "\n\n"),
                    'UserDataReference'=> print("[Data] " . $message->reasoning . "\n\n"),
                    default            => $message ?? null
                };
            }
        );

        echo "Chat ID: {$ace->chatId}\n";

    } catch (AceException $e) {
        echo "\nAce Error [{$e->code}]: " . $e->getMessage() . "\n";
        echo "Chat ID: {$e->chatId}\n";
        if ($e->messageGroupId) {
            echo "Message Group ID: {$e->messageGroupId}\n";
        }
    }

    echo "\nDone\n";
});