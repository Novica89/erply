# Novica89/erply
Manage Erply API calls to retrieve or create / update resources from and to Erply service

#Compatibility
This package has been used on a project based on Laravel 5.1. However, there should be no issue using it on newer versions of this framework

##Installation

Run `composer require novica89/erply dev-master` in root of your application

Then, add service provider to `config/app.php`

```php
'providers' => [
    Novica89\Erply\ErplyServiceProvider::class,
];
```

After adding service provider, add alias as well to `config/app.php`

```php
'aliases' => [
    'ErplyClient' => Novica89\Erply\ErplyClient::class,
];
```

Publish this package config file by running
`php artisan vendor:publish`

This will copy `\vendor\novica89\erply\config\erply.php` config file to `config\erply.php` with default Erply credentials
set to Erply demo credentials.

You can edit this config file and your own desired Erply credentials, or edit `.env` file and add these lines to it:

`
ERPLY_USER=your_erply_user
ERPLY_PASS=your_erply_pass
ERPLY_CLIENT_CODE=your_erply_client_code
`
## Usage

To be added ...
