<?php

namespace Laramie\Lib;

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
                    $itemIsVisible = $this->user->isSuperAdmin()
                       || $this->user->isAdmin()
                       || in_array($value, $this->user->getAbilities());

                    $node->$key = (object) [
                        'isLeaf' => true,
                        'modelName' => $value,
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

        if (object_get($node, 'isVisible') === false) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key == 'isVisible' || object_get($value, 'isVisible') === false) {
                continue;
            }
            if (!object_get($value, 'isLeaf')) {
                echo '<li><a>'.$key.'</a><ul>';
                $this->printMenu($value);
                echo '</ul></li>';
            } else {
                $isActive = false; // $currentRoute->hasParameter('modelKey') && $currentRoute->parameter('modelKey') == $modelKeyOrChild;
                echo '<li><a class="'.($isActive ? 'is-active' : '').'" href="'.route('laramie::list', ['modelKey' => object_get($value, 'modelName')]).'">'.$key.'</a></li>';
            }
        }
    }
}
