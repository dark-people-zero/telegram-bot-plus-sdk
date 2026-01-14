<?php

namespace DarkPeople\TelegramBot\Src\Contracts;

final class TelegramEvents
{
    public const EVENTS = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    public const GROUPS = [
        // Messages & posts
        'messages' => ['message', 'edited_message', 'channel_post', 'edited_channel_post'],

        // Inline mode
        'inline' => ['inline_query', 'chosen_inline_result'],

        // Callbacks & payments
        'callbacks_payments' => ['callback_query', 'shipping_query', 'pre_checkout_query'],

        // Polls
        'polls' => ['poll', 'poll_answer'],

        // Members & chat state
        'members' => ['my_chat_member', 'chat_member', 'chat_join_request'],
    ];
}
