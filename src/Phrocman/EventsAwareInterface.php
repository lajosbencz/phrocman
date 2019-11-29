<?php

namespace Phrocman;


interface EventsAwareInterface
{
    function setEventsManager(EventsManager $eventsManager): void;
    function getEventsManager(): EventsManager;
}