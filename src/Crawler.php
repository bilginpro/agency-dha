<?php

namespace BilginPro\Agency\Dha;

use Carbon\Carbon;
use GuzzleHttp;

/**
 * Class Crawler
 * @package BilginPro\Ajans\Dha
 */
class Crawler
{
    /**
     * @var string
     */
    protected $x_code = '';

    /**
     * @var string
     */
    protected $y_code = '';

    /**
     * @var int
     */
    protected $summary_length = 150;

    /**
     * @var array
     */
    protected $attributes = [
        'limit' => '5',
    ];

    /**
     * Create a new Crawler Instance
     */
    public function __construct($config)
    {
        $this->setParameters($config);
    }

    /**
     * Does the magic.
     * @return array
     */
    public function crawl($attributes = [])
    {
        $this->setAttributes($attributes);

        $response = $this->fetchUrl($this->getUrl());
        $xml = new \SimpleXMLElement($response);
        $result = [];
        $i = 0;
        foreach ($xml->channel->item as $item) {
            if ($this->limit > $i) {
                $news = new \stdClass;
                $news->code = (string)$item->guid;
                $news->title = (string)$item->title;
                $news->summary = $this->createSummary($item->description);
                $news->content = (string)trim(preg_replace('/\s+/', ' ', $item->description));
                // preg_replace is for cleaning newlines.
                $news->created_at = (new Carbon($item->pubDate))->format('d.m.Y H:i:s');
                $news->category = $this->titleCase(str_replace('DHA-', '', $item->category));
                $news->city = (!empty($item->location) ? $this->titleCase($item->location) : '');
                $news->images = [];
                if (isset($item->photos) && count($item->photos) > 0) {
                    foreach ($item->photos as $image) {
                        $news->images[] = (string)$image;
                    }
                }

                $result[] = $news;
                $i++;
            }
        }

        return $result;
    }

    /**
     * Creates short summary of the news, strip credits.
     * @param $text
     * @return string
     */
    public function createSummary($text)
    {
        if (strpos($text, '(DHA)') > 0) {
            $split = explode('(DHA)', $text);
            if (count($split) > 1) {
                $text = $split[1];
                $text = trim($text, ' \t\n\r\0\x0B-');
            }
        }
        $summary = (string)$this->shortenString(strip_tags($text), $this->summary_length);

        return $summary;
    }

    /**
     * Sets config parameters.
     */
    public function setParameters($config)
    {
        if (!is_array($config)) {
            throw new \InvalidArgumentException('$config variable must be an array.');
        }
        if (array_key_exists('x_code', $config)) {
            $this->x_code = $config['x_code'];
        }
        if (array_key_exists('y_code', $config)) {
            $this->y_code = $config['y_code'];
        }
        if (array_key_exists('limit', $config)) {
            $this->limit = $config['limit'];
        }
    }

    /**
     * Sets filter attributes.
     * @param $attributes array
     */
    protected function setAttributes($attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Returns full url for crawling.
     * @return string
     */
    public function getUrl()
    {
        $url = 'http://ajans.dha.com.tr/dhayharss_resimli.php'
            . '?x=' . $this->x_code
            . '&y=' . $this->y_code;

        return $url;
    }


    /**
     * Fethches given url and returns response as string.
     * @param $url
     * @param string $method
     * @param array $options
     *
     * @return string
     */
    public function fetchUrl($url, $method = 'GET', $options = [])
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request($method, $url, $options);
        if ($res->getStatusCode() == 200) {
            return (string)$res->getBody();
        }
        return '';
    }

    /**
     * Cuts the given string from the end of the appropriate word.
     * @param $str
     * @param $len
     * @return string
     */
    public function shortenString($str, $len)
    {
        if (strlen($str) > $len) {
            $str = rtrim(mb_substr($str, 0, $len, 'UTF-8'));
            $str = substr($str, 0, strrpos($str, ' '));
            $str .= '...';
            $str = str_replace(',...', '...', $str);
        }
        return $str;
    }

    /**
     * Converts a string to "Title Case"
     * @param $str
     * @return string
     */
    public function titleCase($str)
    {
        $str = mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
        return $str;
    }
}
