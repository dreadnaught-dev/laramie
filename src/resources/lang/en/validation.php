<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'This must be accepted.',
    'active_url' => 'This is not a valid URL.',
    'after' => 'This must be a date after :date.',
    'after_or_equal' => 'This must be a date after or equal to :date.',
    'alpha' => 'This may only contain letters.',
    'alpha_dash' => 'This may only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'This may only contain letters and numbers.',
    'array' => 'This must be an array.',
    'before' => 'This must be a date before :date.',
    'before_or_equal' => 'This must be a date before or equal to :date.',
    'between' => [
        'numeric' => 'This must be between :min and :max.',
        'file' => 'This must be between :min and :max kilobytes.',
        'string' => 'This must be between :min and :max characters.',
        'array' => 'This must have between :min and :max items.',
    ],
    'boolean' => 'This field must be true or false.',
    'confirmed' => 'This confirmation does not match.',
    'date' => 'This is not a valid date.',
    'date_equals' => 'This must be a date equal to :date.',
    'date_format' => 'This does not match the format :format.',
    'different' => 'This and :other must be different.',
    'digits' => 'This must be :digits digits.',
    'digits_between' => 'This must be between :min and :max digits.',
    'dimensions' => 'This has invalid image dimensions.',
    'distinct' => 'This field has a duplicate value.',
    'email' => 'This must be a valid email address.',
    'ends_with' => 'This must end with one of the following: :values.',
    'exists' => 'The selected :attribute is invalid.',
    'file' => 'This must be a file.',
    'filled' => 'This field must have a value.',
    'gt' => [
        'numeric' => 'This must be greater than :value.',
        'file' => 'This must be greater than :value kilobytes.',
        'string' => 'This must be greater than :value characters.',
        'array' => 'This must have more than :value items.',
    ],
    'gte' => [
        'numeric' => 'This must be greater than or equal :value.',
        'file' => 'This must be greater than or equal :value kilobytes.',
        'string' => 'This must be greater than or equal :value characters.',
        'array' => 'This must have :value items or more.',
    ],
    'image' => 'This must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'This field does not exist in :other.',
    'integer' => 'This must be an integer.',
    'ip' => 'This must be a valid IP address.',
    'ipv4' => 'This must be a valid IPv4 address.',
    'ipv6' => 'This must be a valid IPv6 address.',
    'json' => 'This must be a valid JSON string.',
    'lt' => [
        'numeric' => 'This must be less than :value.',
        'file' => 'This must be less than :value kilobytes.',
        'string' => 'This must be less than :value characters.',
        'array' => 'This must have less than :value items.',
    ],
    'lte' => [
        'numeric' => 'This must be less than or equal :value.',
        'file' => 'This must be less than or equal :value kilobytes.',
        'string' => 'This must be less than or equal :value characters.',
        'array' => 'This must not have more than :value items.',
    ],
    'max' => [
        'numeric' => 'This may not be greater than :max.',
        'file' => 'This may not be greater than :max kilobytes.',
        'string' => 'This may not be greater than :max characters.',
        'array' => 'This may not have more than :max items.',
    ],
    'mimes' => 'This must be a file of type: :values.',
    'mimetypes' => 'This must be a file of type: :values.',
    'min' => [
        'numeric' => 'This must be at least :min.',
        'file' => 'This must be at least :min kilobytes.',
        'string' => 'This must be at least :min characters.',
        'array' => 'This must have at least :min items.',
    ],
    'multiple_of' => 'This must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'This format is invalid.',
    'numeric' => 'This must be a number.',
    'password' => 'The password is incorrect.',
    'present' => 'This field must be present.',
    'regex' => 'This format is invalid.',
    'required' => 'This field is required.',
    'required_if' => 'This field is required when :other is :value.',
    'required_unless' => 'This field is required unless :other is in :values.',
    'required_with' => 'This field is required when :values is present.',
    'required_with_all' => 'This field is required when :values are present.',
    'required_without' => 'This field is required when :values is not present.',
    'required_without_all' => 'This field is required when none of :values are present.',
    'same' => 'This and :other must match.',
    'size' => [
        'numeric' => 'This must be :size.',
        'file' => 'This must be :size kilobytes.',
        'string' => 'This must be :size characters.',
        'array' => 'This must contain :size items.',
    ],
    'starts_with' => 'This must start with one of the following: :values.',
    'string' => 'This must be a string.',
    'timezone' => 'This must be a valid zone.',
    'unique' => 'This has already been taken.',
    'uploaded' => 'This failed to upload.',
    'url' => 'This format is invalid.',
    'uuid' => 'This must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
