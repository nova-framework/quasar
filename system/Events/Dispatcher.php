<?php

namespace System\Events;


class Dispatcher
{
    /**
     * @var array  The registered event listeners.
     */
    protected $listeners = array();

    /**
     * @var array  The sorted event listeners.
     */
    protected $sorted = array();

    /**
     * @var array  The event firing stack.
     */
    protected $firing = array();


    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array  $events
     * @param  mixed   $listener
     * @param  int     $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0)
    {
        foreach ((array) $events as $event) {
            $this->listeners[$event][$priority][] = $listener;

            unset($this->sorted[$event]);
        }
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]);
    }

    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param  string  $event
     * @param  array   $payload
     * @return mixed
     */
    public function until($event, $payload = array())
    {
        return $this->fire($event, $payload, true);
    }

    /**
     * Get the event that is currently firing.
     *
     * @return string
     */
    public function firing()
    {
        return last($this->firing);
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param  string  $event
     * @param  mixed   $payload
     * @param  bool    $halt
     * @return array|null
     */
    public function fire($event, $payload = array(), $halt = false)
    {
        $responses = array();

        if (is_object($event)) {
            list ($payload, $event) = array(array($event), get_class($event));
        }

        // Ensure that payload is an array.
        else if (! is_array($payload)) {
            $payload = array($payload);
        }

        $this->firing[] = $event;

        foreach ($this->getListeners($event) as $listener) {
            $response = call_user_func_array($listener, $payload);

            if (! is_null($response) && $halt) {
                array_pop($this->firing);

                return $response;
            } else if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        array_pop($this->firing);

        return $halt ? null : $responses;
    }

    /**
     * Get all of the listeners for a given event name.
     *
     * @param  string  $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        if (! isset($this->sorted[$eventName])) {
            $this->sortListeners($eventName);
        }

        return $this->sorted[$eventName];
    }

    /**
     * Sort the listeners for a given event by priority.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function sortListeners($eventName)
    {
        $this->sorted[$eventName] = array();

        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);

            $this->sorted[$eventName] = call_user_func_array('array_merge', $this->listeners[$eventName]);
        }
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event)
    {
        unset($this->listeners[$event], $this->sorted[$event]);
    }
}
