<?php
namespace Laramie;

use Cache;

use Laramie\Globals;
use Laramie\AdminModels\LaramieRole;

trait LaramieAuth
{
    public function isAdmin() { return $this->isLaramieAdmin(); }
    public function hasAbility($modelType, $ability = 'read') { return $this->hasLaramieAbility($modelType, $ability); }

    private $_laramie = null;
    private $_roles = null;

    public function isLaramieAdmin()
    {
        return in_array(Globals::AdminRoleId, data_get($this->getLaramieData(), 'roles', []));
    }

    public function hasAccessToLaramieModel($modelType, string $ability = null)
    {
        return $this->isLaramieAdmin()
            || $this->hasLaramieAbility($modelType, $ability);
    }

    public function hasLaramieAbility($modelType, $ability)
    {
        $userAbilitiesForType = data_get($this->getLaramieAbilities(), $modelType, []);

        // If no ability is specified, just make sure the user has _some_ ability for the model.
        if (!$ability) {
            return (count($userAbilitiesForType) > 0);
        }

        return in_array('all', $userAbilitiesForType)
            || in_array($ability, $userAbilitiesForType);
    }

    public function getLaramiePrefs()
    {
        return data_get($this->getLaramieData(), 'prefs', (object) []);
    }

    public function updateLaramiePrefs($prefs)
    {
        $laramieData = $this->getLaramieData();
        $laramieData->prefs = $prefs;

        $this->data = json_encode($laramieData);
        $this->save();
    }

    protected function getLaramieData()
    {
        if (!isset($this->_laramie)) {
            // TODO -- remove the default
            $this->_laramie = json_decode(data_get($this, 'data', '{"roles": ["'.Globals::AdminRoleId.'"]}'));
        }

        return $this->_laramie;
    }

    protected function getLaramieRoles()
    {
        if (!isset($this->_roles)) {
            $this->_roles = LaramieRole::superficial()->whereIn('id', data_get($this->getLaramieData(), 'roles', [Globals::DummyId]))->get();
        }

        return $this->_roles;
    }

    protected function getLaramieAbilities()
    {
        $abilities = [];
        $types = $this->getNonSystemModelTypes();

        foreach ($this->getLaramieRoles() as $role) {
            foreach ($types as $type) {
                // @preston stopped here
                $abilitiesForRoleForType = data_get($role, $type, []);
                data_set($abilities, $type, array_unique(array_merge(data_get($abilities, $type, []), $abilitiesForRoleForType)));
            }
        }

        return $abilities;
    }

    private function getNonSystemModelTypes()
    {
        // The types cache gets cleared any time there's an update that triggers a new `ConfigLoaded` hook.
        return Cache::rememberForever(Globals::LARAMIE_TYPES_CACHE_KEY, function() {
            return collect(app(\Laramie\Services\LaramieDataService::class)->getAllModels())
                ->filter(function ($e) {
                    return !data_get($e, 'isSystemModel');
                })
                ->keys()
                ->toArray();
        });
    }
}
