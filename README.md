# Novica89/erply
Manage Erply API calls to retrieve or create / update resources from and to Erply service

# Compatibility
This package has been used on a project based on Laravel 5.1. However, there should be no issue using it on newer versions of this framework. It has been tested by making couple of calls using this package on Laravel 5.3 and it does work on it as well. Happy coding!

# Installation

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

- ERPLY_USER=your_erply_user
- ERPLY_PASS=your_erply_pass
- ERPLY_CLIENT_CODE=your_erply_client_code

# Usage

## Making a request to Erply service

As you can see in Erply developers documentation, you can retrieve records by using `getRecordName` calls. Ex: `getCustomers` which would retrieve your Erply customers list. 

You can do this call by using this package in the following way:

```php
$customers = ErplyClient::getCustomers(['getBalanceInfo' => 1, 'getAddresses' => 1]);
```

OR by doing

```php
$customers = ErplyClient::request('getCustomers', ['getBalanceInfo' => 1, 'getAddresses' => 1]);
```

Use whichever method you prefer. Note that, if using the first method, just use the method name on `ErplyClient` facade that corresponds to whichever call you want to make to Erply ( in this case it is `getCustomers` method ).

> [You can see all the supported calls on Erply developer documentation](http://erply.com/api/)

## Getting a request status after making a request

So, you've made a call to Erply but how can you know if it was successfull or not ? Fear not, this is as easy as learning maths. Just kidding...

```php
// making a call to get all invoices from Erply and storing response in $invoices variable
$invoices = ErplyClient::getSalesDocuments(['getRowsForAllInvoices' => 1]);

// checking if a request that we just made to Erply to retrieve all the invoices was successfull
if($invoices->wasSuccess()) {
    // happy dance
}
```

## Getting whole response object 

Now, your Erply request was a success, but how do you retrieve response data? This is how

```php
// making a call to get all invoices from Erply and storing response in $invoices variable
$invoices = ErplyClient::getSalesDocuments(['getRowsForAllInvoices' => 1]);

// checking if a request that we just made to Erply to retrieve all the invoices was successfull
if($invoices->wasSuccess()) {
    // instead of happy dance, let's try to be a bit more productive this time and get the whole response object back
    $response = $invoices->response();
    
    // HINT: do a dd($response); here to see your response and it's structure
}
```

## Getting only status object from response

You can return only part of the response, like only a status object that holds information about your request and the response that you got back after a successfull request to Erply.

```php
// making a call to get all invoices from Erply and storing response in $invoices variable
$invoices = ErplyClient::getSalesDocuments(['getRowsForAllInvoices' => 1]);

// checking if a request that we just made to Erply to retrieve all the invoices was successfull
if($invoices->wasSuccess()) {
    // instead of whole response object, let's say we are only interested in response status section of response data
    $responseStatus = $invoices->responseStatus();
    
    // HINT: do a dd($responseStatus); here to see your response status object only and it's data
}
```

## Getting only records object from response

Just like returning only response status part of the Erply response after making a successfull call to Erply, you can retrieve only records returned to you by Erply service. This will return an array of record objects.

```php
// making a call to get all invoices from Erply and storing response in $invoices variable
$invoices = ErplyClient::getSalesDocuments(['getRowsForAllInvoices' => 1]);

// checking if a request that we just made to Erply to retrieve all the invoices was successfull
if($invoices->wasSuccess()) {
    // instead of whole response object, let's say we are only interested in response status section of response data
    $responseRecords = $invoices->records();
    
    // HINT: do a dd($responseRecords); here to see your array of response records objects and their data
}
```
