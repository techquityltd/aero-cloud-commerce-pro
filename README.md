# Aero: Cloud Commerce Pro
Integration into your Cloud Commerce Pro application from Aero.

## Installation
Installation requires Composer and PHP 7.2 or greater.

```
composer require techquity/aero-cloud-commerce-pro
```

## Configuration
The base configuration will not work with every project and it is required that the following command be ran to create a project specific configuration file.

```
php artisan vendor:publish --provider="Techquity\CloudCommercePro\Providers\CCPServiceProvider"
```

