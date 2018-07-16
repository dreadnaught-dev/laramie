# Laramie

Laramie is a magical composer package for [Laravel](https://laravel.com/) that
grants your application amazing CMS abilities. Installation is totally
non-destructive, so you can install it and not worry about it wrecking existing
work. Drop it in and leverage it for your whole application, just parts of
it, or as a headless CMS driving your nifty frontend / mobile apps.

Some highlights:

* Non-destructive installation
* Incredibly simple data modeling (JSON)
* 2-factor authentication
* Revision control all your content (view history and roll back when needed)
* Customizable workflows via event hooks
* Advanced filtering and sorting
* Markdown editing (in addition to WYSIWYG options)


## Learning Laramie

See Laramie's [online documentation](https://laramie.io/docs).


## Install

Laramie is simply a composer package, so installing it couldn't be easier:

``` bash
composer require laramie-cms/laramie
```

Next, register Laramie's service providers with Laravel by modifying `config/app.php`:

```php
'providers' => [
    // Other Service Providers

    Laramie\Providers\LaramieServiceProvider::class,
    Laramie\Providers\LaramieEventServiceProvider::class,
],
```

Complete the installation:

``` bash
php artisan vendor:publish
php artisan migrate
php artisan laramie:authorize your-user@email.com
```

Bam! That's it! Your new admin will be available at `yoursite.dev/admin`.


## Testing

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.


## Security

If you discover any security related issues, please email Preston Clark at <a href="mailto:laramie.pclark@mailhero.io">laramie.pclark@mailhero.io</a> instead of using the issue tracker.


## License

Laramie is open-sourced software, licensed under the MIT License (MIT). Please see the [license file](LICENSE.md) for more information.

## Additional Notes

Icons by Smashicons from flaticon.com
