<?php

return array(
    'factories' => array(
        'Ics' => function () {
            if (class_exists('\App\Calendar\Factory\Ics')) {
                return new \App\Calendar\Factory\Ics();
            } else {
                return new \Nails\Calendar\Factory\Ics();
            }
        }
    )
);
