<?php

namespace Phrocman;


use Evenement\EventEmitterInterface;

interface EventsAwareInterface
{
    function setEventsManager(EventEmitterInterface $eventsManager): void;
    function getEventsManager(): EventEmitterInterface;
}