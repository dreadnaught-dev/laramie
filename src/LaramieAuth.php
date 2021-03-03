<?php
namespace Laramie;

trait LaramieAuth
{
    public function isAdmin() { return $this->isLaramieAdmin(); }
    public function hasAbility($key, $accessType = 'list') { return $this->hasLaramieAbility($key, $accessType); }

    private $_laramie = null;
    private $_roles = null;

    public function isLaramieAdmin()
    {
        return in_array(Globals::AdminRoleId, data_get($this->getLaramieData(), 'roles', []));
    }

    public function hasAccessToLaramieModel($key, string $accessType = 'list')
    {
        return $this->isLaramieAdmin()
            || $this->hasLaramieAbility($key, $accessType);
    }

    // @TODO -- implement access types (from Globals::AccessTypes).
    public function hasLaramieAbility($key, $accessType = 'list')
    {
        return array_key_exists($ability, $this->getLaramieAbilities());
    }

    public function getLaramiePrefs()
    {
        return data_get($this->getLaramieData(), 'prefs', (object) []);
    }

    public function updateLaramiePrefs($prefs)
    {
        $laramieData = $this->getLaramieData();
        $laramieData->prefs = $prefs;

        $this->laramie = json_encode($laramieData);
        $this->save();
    }

    protected function getLaramieData()
    {
        if (!isset($this->_laramie)) {
            // TODO -- remove the default
            $this->_laramie = json_decode(data_get($this, 'laramie', '{"roles": ["'.Globals::AdminRoleId.'"]}'));
        }

        return $this->_laramie;
    }

    protected function getLaramieRoles()
    {
        if (!isset($this->_roles)) {
            $this->_roles = collect($this->getLaramieData(), 'roles', [])
                ->map(function($item) {
                    return LaramieRole::find($item);
                });
        }

        return $this->_roles;
    }

    protected function getLaramieAbilities()
    {
        $abilities = [];

        foreach ($this->getLaramieRoles() as $role) {
            // The `data` attribute contains the abilities the particular role has been granted
            collect(json_decode(data_get($role->toArray(), 'data')))
                ->filter(function($item) { return $item === true; })
                ->each(function($item, $key) use(&$abilities) { $abilities[$key] = true; });
        }

        return $abilities;
    }
}
