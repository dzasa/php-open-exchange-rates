Open Exchange Rates PHP Wrapper
===================

A PHP wrapper for Openexchangerates.org API with extended features for non advanced options plan.

Features
-------------------

* Return all available currencies
* Return latest exchange rates using default base currency USD or custom currency
* Return historical exchange rates using default base currency USD or custom currency
* Return dates range exchange rates using default base currency USD or custom currency
* return single rate
* Change base currency
* Convert between currencies
* Cache exchange rates using Memcache, APC or File cache
* Set currencies that will be returned in resulting exchange rates


Usage
-----

```php
<?php

use Dzasa\OpenExchangeRates\OpenExchangeRates;
use Dzasa\OpenExchangeRates\Cache;

$config = array(
    'api_key' => 'your_api_key', // required
    'protocol' => 'https', // optional [http|https]
    'client' => 'curl',  // optional [curl|file_get_contents]
    'base' => 'EUR' // optional
);

// using File cache
$cache = new Cache("file", array('cache_dir'=>'/path_to_cache_dir/'));
$exchangeRates = new OpenExchangeRates($config, $cache);

// or using APC
$cache = new Cache("apc");
$exchangeRates = new OpenExchangeRates($config, $cache);

// or using Memcache
$cache = new Cache("memcache", array('host'=>'localhost', 'port'=> 11211));
$exchangeRates = new OpenExchangeRates($config, $cache);

// or not using cache
$exchangeRates = new OpenExchangeRates($config);

// get all currencies
$currencies = $exchangeRates->getAllCurrencies();

// change base currency
$exchangeRates->setBaseCurrency("BAM");

// set currencies
$exchangeRates->setSymbols("BAM,EUR,USD");

// get latest currencies
$latestRates = $exchangeRates->getLatestRates();

// get exchange rates for 01.01.2014, input can be any valid strtotime input for past
$historicalRates = $exchangeRates->getHistorical("2014-01-01");

// get exchange rates for date range, input can be any valid strtotime() input
$timeSeries = $exchangeRates->getTimeSeries("last Friday", "today");

// convert from EUR to BAM with 3 decimals
$convert = $exchangeRates->convert("EUR", "BAM", 10, 3);

// get rate for EUR currency
$singleRate = $exchangeRates->getRate("EUR");

// get base currency
$baseCurrency = $exchangeRates->getBaseCurrency();

// get API license
$license = $exchangeRates->getLicense();

// get API Disclaimer
$disclaimer = $exchangeRates->getDisclaimer();

```


About
=====

Requirements
------------

- Works with PHP 5.3 or above.


Submitting bugs and feature requests
------------------------------------
Bugs and feature request are tracked on [GitHub]

Version
----

0.7.2


Author
------
Jasenko Rakovic - naucnik@gmail.com

License
----

Licensed under the MIT License - see the LICENSE file for details

[GitHub]:https://github.com/dzasa/php-open-exchange-rates/issues
