<?php

namespace Laramie\Lib;

use Laramie\Events\ConfigLoaded;
use Laramie\Events\AugmentModelValidator;
use JsonSchema\Validator;
use Exception;

/**
 * Process and load Laramie configuration files.
 */
class ModelLoader
{
    /**
     * Load Laramie's model config(s).
     *
     * @return mixed return json-decoded version of fully fleshed out version of Laramie's config files
     */
    public static function load()
    {
        // The config path _could_ be an array. If so, we'll merge all those configs together
        $configPath = config('laramie.model_path');
        $adminConfigPath = __DIR__.'/../admin-models.json';
        $cachedConfigPath = storage_path('framework/cache/laramie-models.php');

        $configModifiedTime = 0;
        $configCachedTime = file_exists($cachedConfigPath) ? filemtime($cachedConfigPath) : -1;

        $configs = is_array($configPath) ? $configPath : [$configPath];
        array_unshift($configs, $adminConfigPath);

        $configs = collect($configs)
            ->filter(function ($path) {
                return (bool) $path;
            })
            ->each(function ($path) use (&$configModifiedTime) {
                if (!file_exists($path)) {
                    throw new Exception('Could not locate config. Looked at: '.$path);
                }
                $configModifiedTime = max($configModifiedTime, filemtime($path));
            })
            ->all();

        // Cache the "loaded" config by saving a hydrated version of it to storage
        if ($configCachedTime < $configModifiedTime) {
            $config = (object) [];
            $models = (object) [];
            $menu = (object) [];

            // Parse the JSON configs
            $configs = collect($configs)
                ->map(function ($path) {
                    $config = json_decode(file_get_contents($path));
                    if ($config == null) {
                        throw new Exception(sprintf('Could not parse %s due to JSON formatting errors', $path));
                    }

                    return $config;
                });

            // Combine menus
            $configs
                ->map(function ($config) {
                    return object_get($config, 'menu');
                })
                ->filter()
                ->each(function ($pluginMenu) use ($menu) {
                    foreach ($pluginMenu as $key => $value) {
                        $menu->{$key} = $value;
                    }
                });

            // Combine models
            $configs
                ->map(function ($config) {
                    return object_get($config, 'models');
                })
                ->filter()
                ->each(function ($pluginModels) use ($models) {
                    foreach ($pluginModels as $key => $value) {
                        $models->{$key} = $value;
                    }
                });

            // Load each model and set some config properties if they're not
            // already set (and possibly pull in references if model is a string
            // path to a definition file):
            foreach ($models as $key => $model) {
                // If the model is a string, it's referencing another JSON file, load it and swap it in for the model
                if (is_string($model)) {
                    $modelPath = static::joinPaths(base_path(), $model);
                    $model = json_decode(file_get_contents($modelPath));
                }

                $model->{'_type'} = $key;

                $fields = object_get($model, 'fields', (object) []);

                // If a field is a string, it's referencing a JSON definition, load it and swap it in
                foreach ($fields as $tmpKey => $tmpField) {
                    if (is_string($tmpField)) {
                        $fieldPath = static::joinPaths(base_path(), $tmpField);
                        $fields->{$tmpKey} = json_decode(file_get_contents($fieldPath));
                    }
                }

                // Set some required name attributes (although they're not necessarily required by the JSON schema)
                list($singularName, $pluralName) = static::getPrettyNamesFromKey($key);
                $model->name = object_get($model, 'name', $singularName);
                $model->namePlural = object_get($model, 'namePlural', $pluralName);
                $model->isListable = object_get($model, 'isListable', true) !== false;
                //$model->isMetaField = object_get($model, 'isMetaField', false) === true; // NOTE: keeping here for reference, but it's only used on the list page for excluding meta fields (atm only tags/comments/versions); we don't necessarily need to add another attribute to each field for it.
                $model->isEditable = object_get($model, 'isEditable', true) !== false;
                $model->isSystemModel = object_get($model, 'isSystemModel', false) === true;
                $model->isSingular = object_get($model, 'isSingular', false) === true;
                $model->editJs = object_get($model, 'editJs', '');
                $model->editCss = object_get($model, 'editCss', '');
                $model->listJs = object_get($model, 'listJs', '');
                $model->listCss = object_get($model, 'listCss', '');

                // The model `alias` is the field used to identify the model (gererally the
                // first field on the list page, is shown when items are being pulled in to a
                // relationship, etc); It should refer to one of the model's non reference fields.
                // If it's not set, pick out the first text field. If there isn't one, use the id.
                if (!object_get($model, 'alias')) {
                    $fieldKeyToUseAsAlias = null;
                    foreach ($fields as $fieldName => $field) {
                        if (object_get($field, 'type') === 'text') {
                            $fieldKeyToUseAsAlias = $fieldName;
                            break;
                        }
                    }
                    $model->alias = $fieldKeyToUseAsAlias ?: 'id';
                }

                $defaultBulkActions = config('laramie.default_bulk_actions');
                $modelBulkActions = array_merge(object_get($model, 'bulkActions', $defaultBulkActions), object_get($model, 'additionalBulkActions', []));
                if ($modelBulkActions != $defaultBulkActions) {
                    $model->bulkActions = $modelBulkActions;
                }

                $quickSearch = object_get($model, 'quickSearch', $model->alias);
                $model->quickSearch = gettype($quickSearch) != 'array'
                    ? [$quickSearch]
                    : $quickSearch;

                foreach ($model->quickSearch as $field) {
                    if ($field != 'id' && !property_exists($fields, $field)) {
                        throw new Exception(sprintf('Quick-search field "%s" does not exist on model "%s"', $field, $model->name));
                    }
                }

                // Don't allow reference fields to be used as aliases -- we run into an issue
                // with prefetching relationships and potentially going down a recursive rabbit hole
                if (preg_match('/^reference/', object_get($fields, $model->alias.'.type'))) {
                    throw new Exception('Sorry, you may not use a reference field as an alias. You may use a computed field if you need additional flexibility');
                }

                // Don't allow aggregate fields to be used as aliases -- we run into an issue
                // with prefetching relationships and potentially going down a recursive rabbit hole
                if (preg_match('/^aggregate/', object_get($fields, $model->alias.'.type'))) {
                    throw new Exception('Sorry, you may not use an aggregate field as an alias. You may use a computed field if you need additional flexibility');
                }

                // Add some utility computed fields
                $fields->_id = (object) ['type' => 'computed', 'dataType' => 'string', 'label' => 'Id', 'sql' => '(id::text)', 'listByDefault' => false, 'weight' => 900];
                $fields->_created_at = (object) ['type' => 'computed', 'dataType' => 'date', 'label' => 'Created at', 'sql' => '(created_at)', 'listByDefault' => true, 'weight' => 910];
                $fields->_updated_at = (object) ['type' => 'computed', 'dataType' => 'date', 'label' => 'Updated at', 'sql' => '(updated_at)', 'listByDefault' => false, 'weight' => 920];

                if (config('laramie.disable_meta') !== true && object_get($model, 'disableMeta', false) !== true) {
                    //$fields->_version = (object) ['type' => 'computed', 'label' => 'Version', 'sql' => '(select (count(*) + 1) from laramie_data_archive as lda where lda.laramie_data_id = laramie_data.id)', 'listByDefault' => false, 'weight' => 920];
                    // @optimize -- can we dynamically add a sort-by that hooks into the query to allow sorting? Maybe at list-level.
                    $fields->_versions = (object) ['type' => 'computed', 'isMetaField' => true, 'label' => 'Versions', 'sql' => '(select \'{*count*}\')', 'listByDefault' => false, 'isSearchable' => false, 'sortBy' => null, 'weight' => 920];
                    $fields->_tags = (object) ['type' => 'computed', 'isMetaField' => true, 'label' => 'Tags', 'sql' => '(select \'<span class="js-meta" data-meta-type="tags"><i class="fas fa-tags has-text-grey"></i>&nbsp;<span class="tag-count">{*count*}</span></span>\')', 'listByDefault' => false, 'sortBy' => null, 'weight' => 930];
                    $fields->_comments = (object) ['type' => 'computed', 'isMetaField' => true, 'label' => 'Comments', 'sql' => '(select \'<span class="js-meta" data-meta-type="comments"><i class="far fa-comments has-text-grey"></i>&nbsp;<span class="comment-count">{*count*}</span></span>\')', 'listByDefault' => false, 'sortBy' => null, 'weight' => 940];
                }

                // Ensure certain attributes are set for each field (set them if they aren't already)
                foreach ($fields as $fieldName => $field) {
                    $fields->{$fieldName} = static::processField($fieldName, $field, $model->alias);
                }

                // Default sort "column" and direction
                $model->defaultSort = object_get($model, 'defaultSort', '_created_at');
                $model->defaultSortDirection = object_get($model, 'defaultSortDirection', preg_match('/_at$/', $model->defaultSort) ? 'desc' : 'asc');

                // Save the processed model back to the models object
                $models->{$key} = $model;
            }

            // Set what the label for the LaramieUser model (email, username, etc)
            $tmp = static::getPrettyNamesFromKey(config('laramie.username'));
            $models->LaramieUser->fields->user->label = $tmp[0];

            // Save the processed menu and models to the config
            $config->menu = $menu;
            $config->models = $models;

            $validator = new Validator();
            $modelValidator = json_decode(file_get_contents(__DIR__.'/../model-validator.json'));

            event(new AugmentModelValidator($modelValidator));

            $baseFieldValidator = object_get($modelValidator, 'fields._base');

            $errors = [];
            foreach ($models as $m) {
                $validator->check($m, $modelValidator->modelSchema);
                if (!$validator->isValid()) {
                    foreach ($validator->getErrors() as $error) {
                        $errors[] = sprintf('%s: %s', $error['property'], $error['message']);
                    }
                }
                // recursively validate fields -- recursion only needed for aggregate fields
                foreach ($m->fields as $fieldName => $field) {
                    self::dfValidateField($m, $field, $modelValidator, $validator, $errors);
                }
            }

            if ($errors) {
                $errorString = 'One or more models do not conform to shema validation rules';
                $errorString = $errorString.(config('app.debug', false)
                    ? ': '.implode("\n", $errors)
                    : '. Please see the error log for more details.');

                \Log::error(implode("\n", $errors));
                throw new Exception($errorString);
            }

            event(new ConfigLoaded($config));

            // Add json validation _post_ `ConfigLoaded` event in case fields were dynamically added to models via `ConfigLoaded` event
            foreach ($models as $model) {
                $model->_jsonValidator = static::getValidationSchema($model);
            }

            // Save the processed config to storage so we don't have to that processing on every request.
            file_put_contents($cachedConfigPath, json_encode($config, JSON_PRETTY_PRINT));

            // Return the config
            return $config;
        } else {
            // Load the cached version of the config.
            return json_decode(file_get_contents($cachedConfigPath));
        }
    }

    private static function dfValidateField($model, $field, $schema, $validator, &$errors)
    {
        $type = $field->type;
        if ($type == 'aggregate') {
            foreach ($field->fields as $aggregateFieldName => $aggregateField) {
                self::dfValidateField($model, $aggregateField, $schema, $validator, $errors);
            }
        }
        $fieldValidator = self::extend(object_get($schema, 'fields._base'), object_get($schema, 'fields.'.$type));
        $validator->reset();
        $validator->check($field, $fieldValidator);
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf('%s -> %s -> %s %s: %s', $model->name, $field->{'_fieldName'}, $field->type, $error['property'], $error['message']);
            }
        }
    }

    private static function extend($a, $b)
    {
        $validator = clone $a;

        if ($b === null) {
            return $validator;
        }
        $validator = clone $a;
        foreach ($b as $key => $value) {
            switch ($key) {
                case 'required':
                    $validator->required = array_merge($b->required, $value);
                    break;
                case 'properties':
                    foreach ($value as $propName => $prop) {
                        $validator->properties->{$propName} = $prop;
                    }
                    break;
                default:
                    $validator->{$key} = $value;
            }
        }

        return $validator;
    }

    /**
     * Process a field, ensuring that all required field attributes are defined.
     *
     * @param string   $fieldName  JSON decoded model definition
     * @param stdClass $field      JSON decoded field definition
     * @param string   $modelAlias String reference to the field to use as this
     *                             model's alias (will be used in reference lists, etc)
     * @param string   $prefix     to use for this field (used for aggregates)
     *
     * @return mixed Object representing a fully fleshed out field (all attributes defined)
     */
    public static function processField($fieldName, $field, $modelAlias = null, $prefix = '')
    {
        list($fieldSingularName, $fieldPluralName) = static::getPrettyNamesFromKey($fieldName);
        $field->type = object_get($field, 'type', 'text'); // default fields to text types

        $field->id = $prefix.$fieldName.($field->type == 'aggregate' ? '_{{'.$fieldName.'Key}}' : '');
        $field->_fieldName = $fieldName;

        $field->label = object_get($field, 'label', $fieldSingularName);
        $field->labelPlural = object_get($field, 'labelPlural', $fieldPluralName); // Used in relationships
        $field->listByDefault = object_get($field, 'listByDefault', $field->type !== 'password');

        $weight = $fieldName == $modelAlias ? -1 : object_get($field, 'weight', 100);
        $field->weight = is_int($weight) ? $weight : 100; // Weight determines where the field is diplayed -- provides a default ordering to the sort page and the order on the edit page
        $field->extra = object_get($field, 'extra', '');
        $field->helpText = object_get($field, 'helpText', '');
        $field->required = object_get($field, 'required', false) == true;

        $validationString = object_get($field, 'validation', '');
        $validationRules = explode('|', $validationString);
        if ($field->required && !in_array('required', $validationRules)) {
            $validationRules[] = 'required';
        }
        if (!$field->required && !in_array('required', $validationRules)) {
            $validationRules[] = 'nullable';
        }
        if (!$validationString) {
            switch ($field->type) {
                case 'boolean':
                    $validationRules[] = 'boolean';
                    break;
                case 'computed':
                    $sql = object_get($field, 'sql');
                    if (!$sql) {
                        throw new Exception('Computed field missing `sql` property');
                    }
                    if (!preg_match('/^[(].*[)]$/', $sql)) {
                        throw new Exception('Computed field `sql` content MUST be enclosed in parenthesis');
                    }
                    break;
                case 'email':
                    $validationRules[] = 'email';
                    break;
                case 'currency':
                case 'number':
                case 'range':
                    if (object_get($field, 'isIntegerOnly')) {
                        $validationRules[] = 'integer';
                    } else {
                        $validationRules[] = 'numeric';
                    }
                    if (is_numeric(object_get($field, 'min'))) {
                        $validationRules[] = 'min:'.object_get($field, 'min');
                    }
                    if (is_numeric(object_get($field, 'max'))) {
                        $validationRules[] = 'max:'.object_get($field, 'max');
                    }
                    break;
                case 'password':
                    $validationRules[] = 'confirmed';
                    break;
                case 'url':
                    $validationRules[] = 'url';
                    break;
                case 'image':
                    $validationRules[] = 'laramie_image:'.implode(',', config('laramie.allowed_image_types'));
                    break;
            }
        }
        $field->validation = implode('|', array_filter($validationRules));

        // Set some type-specific settings
        switch ($field->type) {
            case 'reference':
            case 'reference-single':
            case 'reference-many':
                if ($field->type == 'reference-many') {
                    $field->subtype = 'many';
                    $field->sortBy = object_get($field, 'sortBy'); // by default, don't allow sorting of reference fields -- only allow if the user has explicitly specified a sort.
                } else {
                    $field->subtype = object_get($field, 'subtype', 'single');
                    $field->sortBy = property_exists($field, 'sortBy') ? $field->sortBy : $fieldName; // Allow a field to specify null as sortBy. If null, that field won't be sortable.
                }
                $field->type = 'reference';
                break;
            case 'file':
            case 'image':
                if ($field->type == 'image') {
                    $field->subtype = 'image';
                }
                $field->type = 'file';
                $field->relatedModel = 'LaramieUpload';
                $field->isPublic = object_get($field, 'isPublic', config('laramie.files_are_public_by_default')) !== false; // @note -- add there's a config param to set default file visibility (public meaning it's available outside of the admin).
                break;
            case 'computed':
                $field->isEditable = object_get($field, 'isEditable', false); // set this to false by default for computed fields
                break;
            case 'html':
                $field->isListable = false; // html fields are not listable -- they're purely for presentation
                break;
            case 'password':
                $field->isListable = object_get($field, 'isListable') === true; // password fields _can_ be listable, but will only show asterisks
                $field->isSearchable = false;
                break;
            case 'aggregate':
                if (object_get($field, 'isListable') === true) {
                    throw new Exception('Aggregate fields are not listable. If you need to list data contained within an aggregate field, you must create a computed field.');
                }
                $field->isListable = false; // aggregate fields are not listable
                $field->_template = preg_replace('/(^_+|_+$)/', '', preg_replace('/_+/', '_', preg_replace('/\{\{[^}]+\}\}/', '', $field->id)));
                $field->isRepeatable = object_get($field, 'isRepeatable', false);
                if ($field->isRepeatable) {
                    $field->minItems = max(object_get($field, 'minItems', 0), ($field->required ? 1 : 0));
                    $field->maxItems = object_get($field, 'maxItems', 0);

                    if ($field->minItems === 1 && $field->maxItems === 1) {
                        $field->isRepeatable = false;
                    }
                }
                break;
            case 'select':
            case 'radio':
                // Allow for several options arrays, but transform them to consistent interface (i.e., [{'text' => 'displayed option text', 'value' => 'saved to db'}]).
                $options = collect(object_get($field, 'options', []));
                // Determine transform needed based on first option:
                $mappedOptions = $options->map(function ($item) {
                    $type = gettype($item);
                    if ($type == 'string' || is_numeric($item)) {
                        return (object) ['text' => $item, 'value' => $item];
                    } elseif ($type == 'object' || ($type == 'array' && static::isAssoc($item))) {
                        $tmp = (object) $item;
                        $text = object_get($tmp, 'text', object_get($tmp, 'key'));
                        $value = object_get($tmp, 'value') ?: $text;
                        if (!$text || !$value) {
                            throw new Exception('Select / radio `options` must be a valid array.');
                        }

                        return (object) ['text' => $text, 'value' => $value];
                    } elseif ($type == 'array') {
                        return (object) ['text' => array_first($item), 'value' => array_last($item)];
                    } else {
                        throw new Exception('Select / radio `options` must be a valid array.');
                    }
                });
                $field->options = $mappedOptions->toArray();

                break;
        }

        $field->isEditable = object_get($field, 'isEditable') !== false; // Set isEditable to true by default (set to false by default for computed fields).
        $field->isListable = object_get($field, 'isListable') !== false; // Set isListable to true by default
        $field->sortBy = property_exists($field, 'sortBy') ? $field->sortBy : $fieldName; // Allow a field to specify null as sortBy. If null, that field won't be sortable.
        $field->isSortable = object_get($field, 'isSortable') !== false && $field->sortBy !== null;

        if ($field->type == 'aggregate') {
            $subfields = object_get($field, 'fields', []);

            // Check for aggregate fields that reference a string definition. Augment the aggregate field with them.
            $tmp = [];
            foreach ($subfields as $subfieldName => $subfield) {
                if (is_string($subfield)) {
                    $fieldPath = static::joinPaths(base_path(), $subfield);
                    $tmp[$subfieldName] = json_decode(file_get_contents($fieldPath));
                }
            }
            foreach ($tmp as $tmpKey => $tmpValue) {
                $subfields->{$tmpKey} = $tmpValue;
            }

            // Iterate over the aggregate's fields and process them
            foreach ($subfields as $subfieldName => $subfield) {
                $subfields->{$subfieldName} = static::processField($subfieldName, $subfield, 'subfield', $field->id.'_');
            }
        }

        return $field;
    }

    /**
     * Generate a json-validation schema for the `$model`.
     *
     * @param stdClass $model JSON decoded model definition
     *
     * @return mixed Object representing this models's json-validation schema
     */
    public static function getValidationSchema($model)
    {
        $schema = (object) [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'type' => 'object',
            'additionalProperties' => true, // set to true -- will allow dynamic fields to be appended to model (via events, etc, see LaramieListener for how the role model is getting augmented).
            'required' => [
                'id',
                'type',
                'created_at',
                'updated_at',
            ],
            'properties' => (object) [
                'id' => (object) [
                    'type' => 'string',
                    'pattern' => '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$',
                ],
                'type' => (object) [
                    'type' => 'string',
                    'pattern' => '^'.$model->_type.'$',
                ],
                'created_at' => (object) ['type' => 'string'],
                'updated_at' => (object) ['type' => 'string'],
            ],
            'patternProperties' => (object) [
                '^_' => [],
            ],
        ];

        $fieldCollection = collect($model->fields);

        $requiredFields = $fieldCollection
            ->filter(function ($e) {
                return $e->required;
            })
            ->map(function ($e) {
                return $e->id;
            })
            ->values()
            ->all();

        $schema->required = array_merge($schema->required, $requiredFields);

        $fieldCollection
            ->filter(function ($e, $k) {
                // Don't try to validate computed fields
                // Don't try to validate hidden fields -- they can be any type (including objects, etc (useful for allowing modules to create dynamic fields as necessary)).
                if (in_array($e->type, ['computed', 'hidden'])) {
                    return false;
                }

                return !preg_match('/^_/', $k);
            })
            ->map(function ($e) {
                return static::getValidationSchemaHelper($e);
            })
            ->each(function ($e, $k) use ($schema) {
                $schema->properties->{$k} = $e;
            });

        return $schema;
    }

    /**
     * Utility function used when generating valiation schema.
     *
     * @param stdClass $field JSON decoded field definition
     *
     * @return mixed Object representing this field's json-validation schema
     */
    private static function getValidationSchemaHelper($field)
    {
        switch ($field->type) {
            case 'boolean':
                $validationType = (object) ['type' => 'boolean'];
                break;
            case 'email':
                $validationType = (object) ['type' => 'string']; // @fixme -- using `email` results in an error when the field is nullable and no value is passed, so switched to `string` for now...
                break;
            case 'currency':
            case 'number':
            case 'range':
                $validationType = (object) ['type' => 'number'];
                if (object_get($field, 'isIntegerOnly')) {
                    $validationType->multipleOf = 1.0;
                }
                if (is_numeric(object_get($field, 'min'))) {
                    $validationType->minimum = object_get($field, 'min');
                }
                if (is_numeric(object_get($field, 'max'))) {
                    $validationType->maximum = object_get($field, 'max');
                }
                break;
            case 'integer':
                $validationType = (object) ['type' => 'integer'];
                break;
            case 'markdown':
                $validationType = (object) [
                    'type' => 'object',
                    'required' => [
                        'markdown',
                        'html',
                    ],
                    'properties' => (object) [
                        'markdown' => (object) ['type' => ['string', 'null']],
                        'html' => (object) ['type' => ['string', 'null']],
                    ],
                ];
                break;
            case 'timestamp':
                $validationType = (object) [
                    'type' => 'object',
                    'required' => [
                        'date',
                        'time',
                        'timezone',
                        'timestamp',
                    ],
                    'properties' => (object) [
                        'date' => (object) ['type' => ['string', 'null']],
                        'time' => (object) ['type' => ['string', 'null']],
                        'timezone' => (object) ['type' => ['string', 'null']],
                        'timestamp' => (object) ['type' => ['integer', 'null']],
                    ],
                ];
                break;
            case 'password':
                $validationType = (object) [
                    'type' => 'object',
                    'required' => [
                        'encryptedValue',
                    ],
                    'properties' => (object) [
                        'encryptedValue' => (object) ['type' => ['string', 'null']],
                    ],
                ];
                break;
            case 'file':
            case 'image':
            case 'reference':
                if (object_get($field, 'subtype') == 'many') {
                    // Multi reference
                    $validationType = (object) [
                        'type' => 'array',
                        'items' => (object) [
                            'type' => 'string',
                            'pattern' => '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$',
                        ],
                    ];
                } else {
                    // Single reference or file/image
                    $validationType = (object) [
                        'type' => 'string',
                        'pattern' => '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$',
                    ];
                }
                break;
            case 'aggregate':
                $validationType = (object) []; // @todo -- dive into aggregate fields and correctly set `types` and `patterns` for the fields where applicable. Note that this is most important for non-admin saving; normal Laravel validation still happens via the admin.
                break;
            case 'select':
                $validationType = (object) ['type' => ['string', 'array']];
                break;
            default:
                $validationType = (object) ['type' => 'string'];
                break;
        }

        if ($field->required) {
            // Is the item required?
            return $validationType;
        } else {
            // If it's not required, allow nulls to pass validation as well
            return (object) [
                'oneOf' => [
                    $validationType,
                    (object) ['type' => 'null'],
                ],
            ];
        }
    }

    /**
     * Take in an arbitrary number of arguments, joining them together in a
     * path, honoring the system's `DIRECTORY_SEPARATOR`.
     *
     * @param string[] Path parts
     *
     * @return string $path
     */
    private static function joinPaths()
    {
        $paths = array();

        foreach (func_get_args() as $arg) {
            if ($arg !== '') {
                $paths[] = $arg;
            }
        }

        return preg_replace('/[\\'.DIRECTORY_SEPARATOR.']+/', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }

    /**
     * Take a key and return a prettified singular and plural version of its value.
     *
     * @param string Key
     *
     * @return string[] Singular name, Plural name
     */
    private static function getPrettyNamesFromKey($key)
    {
        $tmp = array_values(array_filter(explode('_', snake_case($key))));
        $singularName = title_case(implode(' ', $tmp));
        $tmp[count($tmp) - 1] = str_plural($tmp[count($tmp) - 1]);
        $pluralName = title_case(implode(' ', $tmp));

        return [$singularName, $pluralName];
    }

    /**
     * Take an array and return whether or not it's associative
     * (from https://stackoverflow.com/a/173479).
     *
     * @param array $arr
     *
     * @return bool
     */
    private static function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
