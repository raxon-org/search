<?php
namespace Package\Raxon\Search\Trait;

use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;
trait Main {

    /**
     * @throws Exception
     */
    public function search_install(object $flags, object $options): void
    {
        Core::interactive();
        $object = $this->object();
        echo 'Install ' . $object->request('package') . '...' . PHP_EOL;
    }

    /**
     * @throws Exception
     */
    public function dictionary_create(object $flags, object $options): void
    {
        $object = $this->object();
        $dir = $object->config('controller.dir.data');
        $url = $dir . 'Oxford.txt';
        $read = File::read($url);
        $url = $dir . 'words.txt';
        $read .= PHP_EOL . File::read($url);
        $explode = explode(PHP_EOL, $read);
        $list = [];
        foreach($explode as $nr => $word){
            $word = trim($word);
            if (empty($word)) {
                continue;
            }
            $list[$nr] = $word;
        }
        $list = array_unique($list);
        sort($list, SORT_NATURAL);
        $url = $dir . 'Dictionary.txt';
        File::write($url, implode(PHP_EOL, $list));
        File::permission($object, ['url' => $url]);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import_page(object $flags, object $options): void
    {
        if(!property_exists($options, 'url')){
            throw new Exception('Option URL not set');
        }
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');

        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $options->url, [
        'verify' => false,  // Disable SSL certificate verification (localhost)
        ]);
        $html = $res->getBody();

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        // Get plain text content
        $body = $doc->getElementsByTagName('body')->item(0);
        $plain_text = $body->textContent;
        $plain_text = str_replace(
            [
                "\r\n",
                "\n\r",
                "\r",
            ],
            [
                "\n",
                "\n",
                "\n",
            ],
            $plain_text
        );
        $list = explode(PHP_EOL, $plain_text);
        $paragraph_nr = 0;
        $paragraph = [];
        foreach($list as $nr => $line){
            $line = trim($line);
            if (empty($line)) {
                $paragraph_nr++;
                continue;
            }
            if(!array_key_exists($paragraph_nr, $paragraph)){
                $paragraph[$paragraph_nr] = [];
            }
            $paragraph[$paragraph_nr][] = $line;
        }
        ddd($paragraph);


        d($source);
        ddd($options);


    }
}

