<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Private hosts
    |--------------------------------------------------------------------------
    |
    | A connector that is set up with a form may name a server to connect to.
    | PublicHost::allows() refuses servers on this machine or a private
    | network, so a connector cannot be pointed at services only your own
    | server can reach. Turn this on when such servers are meant to be used,
    | for example a mail server on the same network as a self-hosted app.
    |
    */

    'allow_private_hosts' => (bool) env('CONNECTORS_ALLOW_PRIVATE_HOSTS', false),

];
