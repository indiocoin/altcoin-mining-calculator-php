<?php

namespace n00bsys0p;

require_once('ExchangeContainer.php');
require_once('Calculator.php');
require_once('CachingHttpClient.php');
require_once('TemplateProcessor.php');

class HashCalculator {

    // App configuration
    protected $config = NULL;

    // Explorer object to deal with Explorer queries
    protected $explorer = NULL;

    // ExchangeContainer for performing operations
    // on grouped Exchange objects
    protected $exchanges = NULL;

    protected $client = NULL;
    protected $calculator = NULL;

    public function __construct($config, $explorer)
    {
        $this->config    = $config;
        $this->explorer  = new $explorer($this->config['explorer']);
        $this->exchanges = new ExchangeContainer($config['exchanges']);
        $this->client    = new CachingHttpClient;
    }

    public function getBlockReward()
    {
        return $this->explorer->getBlockReward();
    }

    public function getDifficulty()
    {
        return $this->explorer->getDifficulty();
    }

    /**
     * Get fiat rates for the altcoin for a single
     * or list of fiat currencies.
     */
    public function getFiatRate($fiat_list)
    {
        $list = array();

        if(is_array($fiat_list))
        {
            foreach($fiat_list as $fiat)
            {
                $currency = $this->processFiat($fiat);
                $list[$fiat] = $currency;
            }
        }
        else
        {
            return array($fiat_list => $this->processFiat($fiat_list));
        }

        return $list;
    }

    /**
     * Find the value for a single fiat currency.
     */
    protected function processFiat($fiat)
    {
        $cache_opts = array('max-age' => CACHE_EXCHANGE_TIMEOUT);
        $cache_filename = 'btcticker_' . strtoupper($fiat);
        $url = $this->config['ticker']['url'];

        // Make sure the fiat code 3 is in the correct format for the API
        $ticker_requires_ucase = $this->config['ticker']['uppercase'];
        $fiat = ($ticker_requires_ucase) ? strtoupper($fiat) : strtolower($fiat);

        // Replace CURR with 
        $url = preg_replace('/\{\{CURR\\}}/', $fiat, $url);
        $response = $this->client->get($url, $cache_filename, $cache_opts, NULL); // We want a NULL response if it fails

        $json = (is_null($response)) ? NULL : json_decode($response);

        return (is_null($json)) ? 0 : $json->last;
    }

    public function getBitcoinRate()
    {
        $rate = $this->exchanges->getAverageBtcRate();
        // Convert to satoshis from native PHP scientific notation
        return Calculator::formatAsSatoshi($rate);
    }

    public function calculateForHashRate($hashrate, $fiat = 'USD')
    {
        $diff     = $this->getDifficulty();
        $reward   = $this->getBlockReward();
        $btcrate  = $this->getBitcoinRate();
        $btcprice = $this->getFiatRate($fiat);

        $this->calculator = new Calculator($diff, $reward, $btcrate, $btcprice);
        $result = $this->calculator->calculatePerDay($hashrate);

        return $result;
    }
}

?>
