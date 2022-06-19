<?php

declare(strict_types=1);

namespace Laramie;

class Hook
{
    private static $listeners = [];

    public static function listen($key, $callback, $sort = 0)
    {
        $container = data_get(self::$listeners, $key, []);

        $container[] = (object) [
            'callback' => $callback,
            'sort' => $sort,
        ];

        self::$listeners[$key] = $container;
    }

    public static function fire($event)
    {
        $key = is_string($event)
            ? $event
            : get_class($event);

        $sortedListeners = collect(data_get(self::$listeners, $key, []))
            ->sortBy(function ($item) {
                return $item->sort;
            });

        $returnValue = null;

        foreach ($sortedListeners as $listener) {
            $callback = $listener->callback;

            if (is_string($callback)) {
                [$class, $method] = \Str::parseCallback($callback);
                $instance = app($class);
                $returnValue = $instance->{$method}($event);
            } elseif (is_callable($callback)) {
                $returnValue = $callback($event);
            } else {
                throw new \Exception('You must specify a callback function or a fully qualified "classPath@method" string to handle Laramie Hooks');
            }

            // If a callback returns `false`, stop event propagation
            if ($returnValue === false) {
                break;
            }
        }

        return $returnValue;
    }
}
