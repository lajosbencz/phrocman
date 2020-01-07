<?php

namespace Phrocman;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

class EventsManager implements EventEmitterInterface
{
    use EventEmitterTrait;
}
