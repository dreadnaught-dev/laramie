<?php

declare(strict_types=1);

namespace Laramie\Lib;

use Laramie\Globals;

class MenuHelper
{
    protected $menu = null;
    protected $user = null;
    protected $currentRoute = null;

    public function __construct($menu, $user, $currentRoute = null)
    {
        $this->menu = json_decode(json_encode($menu)); // clone only superficially does what I want
        $this->user = $user;
        $this->currentRoute = $currentRoute;
        $this->setVisibility($this->menu);
    }

    private function setVisibility($node)
    {
        $node->isVisible = false;
        foreach ($node as $key => $value) {
            $itemIsVisible = false;
            switch (gettype($value)) {
                case 'string':
                    $itemIsVisible = $this->user->hasAccessToLaramieModel($value, Globals::AccessTypes['read']);

                    $node->$key = (object) [
                        'isLeaf' => true,
                        'modelKey' => $value,
                        'isVisible' => $itemIsVisible,
                    ];
                    break;

                case 'object':
                    $node->isVisible |= $this->setVisibility($value);
                    break;
            }
            $node->isVisible = (bool) ($node->isVisible || $itemIsVisible);
        }

        return (bool) $node->isVisible;
    }

    public function printMenu($node = null)
    {
        if ($node === null) {
            $node = $this->menu;
        }

        if (data_get($node, 'isVisible') === false) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key == 'isVisible' || data_get($value, 'isVisible') === false) {
                continue;
            }
            if (!data_get($value, 'isLeaf')) {
                echo '<li><a>'.$key.'</a><ul>';
                $this->printMenu($value);
                echo '</ul></li>';
            } else {
                $isActive = false; // $currentRoute->hasParameter('modelKey') && $currentRoute->parameter('modelKey') == $modelKeyOrChild;
                echo '<li><a class="'.($isActive ? 'is-active' : '').'" href="'.route('laramie::list', ['modelKey' => data_get($value, 'modelKey')]).'">'.$key.'</a></li>';
            }
        }
    }
}
