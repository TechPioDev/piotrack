<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repeat support requests
    |--------------------------------------------------------------------------
    |
    | A client who asks the website chat for help again - same email address,
    | same kind of request - while their ticket is still open is added to that
    | ticket instead of opening a second one. This is how recently the ticket
    | must have seen activity for that to happen; after it, a new request is
    | treated as a new matter. Zero turns the joining off.
    |
    */

    'ticket_merge_hours' => (int) env('CHAT_TICKET_MERGE_HOURS', 72),

];
