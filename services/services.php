<?php

use Nails\Calendar\Factory;

return [
    'factories' => [
        'Ics' => function (): Factory\Ics {
            if (class_exists('\App\Calendar\Factory\Ics')) {
                return new \App\Calendar\Factory\Ics();
            } else {
                return new Factory\Ics();
            }
        },
    ],
];
