<?php

namespace Dzasa\OpenExchangeRates;

use Dzasa\OpenExchangeRates\Cache;
use Exception;
use DateTime;
use DateInterval;
use DatePeriod;

/**
 * OpenExchangeRates
 * 
 * A PHP wrapper for Openexchangerates.org API with extended functionalities
 * 
 * @author Jasenko Rakovic <nacunik@gmail.com>
 */
class OpenExchangeRates {

    /**
     * @var string Default protocol to use. HTTPS is optional
     */
    const DEFAULT_PROTOCOL = 'http';

    /**
     * @var string Default transfer client. 
     */
    const DEFAULT_CLIENT = 'curl';

    /**
     * @var string Route for latest exchange rates
     */
    const ROUTE_LATEST = "%s://openexchangerates.org/api/latest.json";

    /**
     * @var string Route for all currencies
     */
    const ROUTE_CURRENCIES = "%s://openexchangerates.org/api/currencies.json";

    /**
     * @var string Route for historical exchange rates
     */
    const ROUTE_HISTORICAL = "%s://openexchangerates.org/api/historical/%s.json";

    /**
     * @var string Default exchange rates base currency
     */
    const DEFAULT_BASE = "USD";

    /**
     * @var array
     * api_key -> API Key provided by OpenExchangeRates.org
     * protocol -> Can be HTTP or HTTPS
     * client -> Can be CURL and file_get_contents
     * base -> Can be any of ~165 currencies provided by OpenExchangeRates.org
     */
    private $config = array(
        'api_key' => null,
        'protocol' => self::DEFAULT_PROTOCOL,
        'client' => self::DEFAULT_CLIENT,
        'base' => self::DEFAULT_BASE
    );

    /**
     * @var Cache Caching handler. Can be Memcache, APC or File 
     */
    private $cacheHandler;

    /**
     * @var boolean To determine of user is in Paid with advanced options or not. If its in non advanced options plan, use extra logic to have same functionalities as advanced options user.
     */
    private $isAdvanced = false;

    /**
     * @var string Reduce returned currencies to these
     */
    private $symbols;

    /**
     *
     * @var string Disclaimer from API
     */
    private $disclaimer;

    /**
     * @var string License from API
     */
    private $license;

    /**
     *
     * @var array Latest rates 
     */
    private $latestRates = array();

    /**
     *
     * @var array Historical rates
     */
    private $historicalRates = array();

    /**
     *
     * @var array Time series rates 
     */
    private $timeSeries = array();

    /**
     *
     * @var array All currencies used by API
     */
    private $currencies = array();

    /**
     * 
     * 
     * @param array $config
     * @param \OpenExchangeRates\Cache $cacheHandler
     * @throws \Exception
     */
    function __construct($config, Cache $cacheHandler = null) {
        $this->config = $config + $this->config;

        if ($config['api_key'] == null) {
            throw new \Exception("Api key must be defined!");
        }

        if ($cacheHandler) {
            $this->cacheHandler = $cacheHandler;
        }

        $paidCheckResult = $this->getLatestRates($this->config['base'], true, true);

        if (isset($paidCheckResult['error']) && $paidCheckResult['message'] == 'invalid_app_id') {
            throw new \Exception($paidCheckResult['description']);
        } else if (isset($paidCheckResult['error']) && $paidCheckResult['message'] == 'not_allowed') {
            $this->getLatestRates();
        } else if (self::DEFAULT_BASE != $this->config['base']) {
            $this->isAdvanced = true;
        }
        
        // set currencies
        $this->getAllCurrencies();



        $this->disclaimer = isset($this->latestRates['disclaimer']) ? $this->latestRates['disclaimer'] : '';
        $this->license = isset($this->latestRates['license']) ? $this->latestRates['license'] : '';
    }

    /**
     * Get latest exchange rates from API and change currency if user is not in paid advanced options plan
     * 
     * @param string $base
     * @param bool $resetBase
     * @return array
     */
    public function getLatestRates($base = false, $resetBase = true, $skipCache = false) {


        if ($this->cacheHandler != null && !$skipCache) {
            $key = md5(date("Y-m-d"));
            $cacheKey = sprintf("%s%s", $this->config['base'], isset($this->symbols) ? $this->symbols : '');
            $cacheKey = "OER_latest__" . md5($cacheKey);

            $cache = $this->cacheHandler->get($cacheKey);

            if ($cache) {
                $this->latestRates = $cache;

                return $this->latestRates;
            }
        }

        $this->config['route'] = sprintf(self::ROUTE_LATEST . "?app_id=%s&base=%s", $this->config['protocol'], $this->config['api_key'], $base ? $base : $this->getBaseCurrency(true));

        if (isset($this->symbols) && $this->isAdvanced) {
            $this->config['route'] .= sprintf($this->config['route'] . "&symbols=%s", $this->symbols);
        }

        $result = $this->sendRequest();

        $this->latestRates = $result;

        if (!$this->isAdvanced && self::DEFAULT_BASE != $this->config['base'] && $resetBase) {
            $this->setBaseCurrency($this->config['base'], $this->latestRates);
        }

        if (isset($this->symbols) && !$this->isAdvanced) {
            $this->reduceSymbols($this->latestRates['rates']);
        }


        if ($this->cacheHandler != null && !isset($this->latestRates['error']) && !$skipCache) {
            $this->cacheHandler->set($cacheKey, $this->latestRates);
        }

        return $this->latestRates;
    }

    /**
     * Get historical exchange rates and change currency if user is not in paid advanced options plan
     * 
     * @param date $date
     * @return array
     */
    public function getHistorical($date, $skipCache = false) {

        $time = strtotime($date);

        if (!$time) {
            throw new \Exception("Not valid input for date, check http://php.net/manual/en/function.strtotime.php");
        }

        $date = date("Y-m-d", $time);

        if ($this->cacheHandler != null && !$skipCache) {
            $cacheKey = sprintf("%s%s%s", $this->config['base'], isset($this->symbols) ? $this->symbols : '', $date);
            $cacheKey = "OER_historical__" . md5($cacheKey);

            $cache = $this->cacheHandler->get($cacheKey);

            if ($cache) {
                $this->historicalRates = $cache;

                return $this->historicalRates;
            }
        }

        $this->config['route'] = sprintf(self::ROUTE_HISTORICAL . "?app_id=%s&base=%s", $this->config['protocol'], $date, $this->config['api_key'], $this->getBaseCurrency(true));

        if (isset($this->symbols) && $this->isAdvanced) {
            $this->config['route'] .= sprintf($this->config['route'] . "&symbols=%s", $this->symbols);
        }

        $result = $this->sendRequest();

        $this->historicalRates = $result;

        if (!$this->isAdvanced && self::DEFAULT_BASE != $this->config['base']) {
            $this->setBaseCurrency($this->config['base'], $this->historicalRates);
        }

        if (isset($this->symbols) && !$this->isAdvanced) {
            $this->reduceSymbols($this->historicalRates['rates']);
        }

        if ($this->cacheHandler != null && !isset($this->historicalRates['error']) && !$skipCache) {
            $this->cacheHandler->set($cacheKey, $this->historicalRates);
        }

        return $this->historicalRates;
    }

    /**
     * Get exchnage rates for date range. 
     * Please note that each day requested counts as one API request (so requesting a full month of data will count as up to 31 'hits').
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param bool $skipCache
     * @return array
     * @throws \Exception
     */
    public function getTimeSeries($startDate, $endDate, $skipCache = false) {
        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate);

        if (!$startTime || !$endTime) {
            throw new \Exception("Not valid input for start or end date, check http://php.net/manual/en/function.strtotime.php");
        }


        if ($this->cacheHandler != null && !$skipCache) {
            $cacheKey = sprintf("%s%s%s%s", $this->config['base'], isset($this->symbols) ? $this->symbols : '', $startTime, $endTime);
            $cacheKey = "OER_timeseries__" . md5($cacheKey);

            $cache = $this->cacheHandler->get($cacheKey);

            if ($cache) {
                $this->timeSeries = $cache;

                return $this->timeSeries;
            }
        }
        $startDate = new DateTime(date("Y-m-d", $startTime));
        $endDate = new DateTime(date("Y-m-d", $endTime));

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($startDate, $interval, $endDate);

        $this->timeSeries = array(
            'disclaimer' => $this->getDisclaimer(),
            'license' => $this->getLicense(),
            'base' => $this->getBaseCurrency(),
            'rates' => array()
        );

        if ($this->isAdvanced) {
            $this->config['route'] = sprintf(self::ROUTE_HISTORICAL . "?app_id=%s&base=%s", $this->config['protocol'], $date->format("Y-m-d"), $this->config['api_key'], $this->getBaseCurrency(true));
            $this->config['route'] .= sprintf($this->config['route'] . "&symbols=%s", $this->symbols);

            $result = $this->sendRequest();

            if ($this->cacheHandler != null && !isset($this->historicalRates['error']) && !$skipCache) {
                $this->cacheHandler->set($cacheKey, $this->timeSeries);
            }

            return $this->timeSeries;
        }

        foreach ($daterange as $date) {
            $this->config['route'] = sprintf(self::ROUTE_HISTORICAL . "?app_id=%s&base=%s", $this->config['protocol'], $date->format("Y-m-d"), $this->config['api_key'], $this->getBaseCurrency(true));

            if (isset($this->symbols) && $this->isAdvanced) {
                $this->config['route'] .= sprintf($this->config['route'] . "&symbols=%s", $this->symbols);
            }

            $result = $this->sendRequest();

            if (!isset($result['error'])) {
                $this->timeSeries['rates'][$date->format("Y-m-d")] = $result['rates'];
            }

            if (!$this->isAdvanced && self::DEFAULT_BASE != $this->config['base']) {
                $this->setBaseCurrency($this->config['base'], $this->timeSeries['rates'][$date->format("Y-m-d")]);
            }

            if (isset($this->symbols) && !$this->isAdvanced) {
                $this->reduceSymbols($this->timeSeries['rates'][$date->format("Y-m-d")]);
            }

            //be nice to API :)
            usleep(200000);
        }


        if ($this->cacheHandler != null && !isset($this->historicalRates['error']) && !$skipCache) {
            $this->cacheHandler->set($cacheKey, $this->timeSeries);
        }

        return $this->timeSeries;
    }

    /**
     * Get all currencies available in API
     * 
     * @param bool $skipCache
     * @return type
     */
    public function getAllCurrencies($skipCache = false) {
        
        if ($this->cacheHandler != null && !$skipCache) {
            $cacheKey = "OER_currencies";

            $cache = $this->cacheHandler->get($cacheKey);

            if ($cache) {
                $this->currencies = $cache;

                return $this->currencies;
            }
        } else if(!isset($this->currencies['error']) && is_array($this->currencies)){
            return $this->currencies;
        }
        
        $this->config['route'] = sprintf(self::ROUTE_CURRENCIES . "?app_id=%s", $this->config['protocol'], $this->config['api_key']);

        $result = $this->sendRequest();

        $this->currencies = $result;
        
        if ($this->cacheHandler != null && !isset($this->currencies['error']) && !$skipCache) {
            $this->cacheHandler->set($cacheKey, $this->currencies);
        }

        return $result;
    }

    /**
     * Convert between two currencies not using API
     * 
     * @param string $from
     * @param string $to
     * @param float $amount
     * @param int $decimals
     * @param float $fromRate
     * @param float $toRate
     * @return mixed
     */
    public function convert($from, $to, $amount, $decimals = 2, $fromRate = false, $toRate = false) {
        if (empty($this->latestRates)) {
            $this->getLatestRates();
        }

        if ($fromRate) {
            $fromRate = $fromRate;
        } else {
            $fromRate = $this->latestRates['rates'][$from];
        }

        if ($toRate) {
            $toRate = $toRate;
        } else {
            $toRate = $this->latestRates['rates'][$to];
        }

        if ($fromRate == 0 || $toRate == 0) {
            $convertedAmount = 0;
        } else {
            $convertedAmount = $amount / $fromRate * $toRate;
        }

        if ($decimals === false) {
            return $convertedAmount;
        } else {
            $finalResult = number_format($convertedAmount, $decimals);
        }

        return array(
            'from' => $from,
            'from_rate' => $fromRate,
            'to' => $to,
            'toRate' => $toRate,
            'amount' => $amount,
            'result' => $finalResult
        );
    }

    /**
     * 
     * @param string $currency
     * @param integer $decimals
     * @return float
     */
    public function getRate($currency, $decimals = 3) {
        if (empty($this->latestRates)) {
            $this->getLatestRates();
        }
        
        $finalResult = number_format($this->latestRates['rates'][strtoupper($currency)], $decimals);
        
        return $finalResult;
    }

    /**
     * Change base currency if user is not in paid advanced options plan.
     * 
     * 
     * @param string $base
     * @param array $source
     * @param bool $range
     */
    public function setBaseCurrency($base, &$source = array(), $range = false) {
        $this->config['base'] = strtoupper($base);

        if (!$this->isAdvanced) {

            if (is_array($source) && isset($source['rates'])) {
                $fromRate = $source['rates'][$base];

                foreach ($source['rates'] as $key => &$rate) {
                    if ($base != $key) {
                        $rate = $this->convert($base, $key, 1, false, $fromRate, $rate);
                    } else {
                        $rate = 1;
                    }
                    $source['base'] = $this->config['base'];
                }
            } else if (is_array($source) && !isset($source['rates']) && !isset($source['error'])) {
                $fromRate = $source[$base];

                foreach ($source as $key => &$rate) {
                    if ($base != $key) {
                        $rate = $this->convert($base, $key, 1, false, $fromRate, $rate);
                    } else {
                        $rate = 1;
                    }
                }
            }
        }
    }

    /**
     * Set custom symbols return in exchange rates array
     * 
     * @param string $symbols
     */
    public function setSymbols($symbols) {
        $this->symbols = $symbols;
    }

    /**
     * Reduce currencies from list in exchange rates array
     * 
     * @param array $source
     */
    private function reduceSymbols(&$source) {
        if (isset($this->symbols)) {
            $symbols = explode(",", $this->symbols);

            foreach ($source as $key => $rate) {
                if (!in_array($key, $symbols)) {
                    unset($source[$key]);
                }
            }
        }
    }

    /**
     * Return base currency. Can return real base used with API or our base currency
     * 
     * @return string
     */
    public function getBaseCurrency($real = false) {
        if ($this->isAdvanced || !$real) {
            return $this->config['base'];
        } else {
            return self::DEFAULT_BASE;
        }
    }

    /**
     * Return Disclaimer
     * 
     * @return string
     */
    public function getDisclaimer() {
        return $this->disclaimer;
    }

    /**
     * Return License
     * 
     * @return string
     */
    public function getLicense() {
        return $this->license;
    }

    /**
     * Send request to OpenExchangeRates.org API
     * 
     * @return array
     */
    private function sendRequest() {
        if ($this->config['client'] === 'curl') {
            $ch = curl_init($this->config['route']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $json = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($json, true);

            return $result;
        } elseif ($this->config['client'] == 'file_get_contents') {
            $json = file_get_contents($this->config['route']);

            $result = json_decode($json, true);

            return $result;
        }
    }

}
