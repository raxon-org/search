<?php
namespace Package\Raxon\Search\Trait;

use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;
trait Embedding {

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function word(object $flags, object $options): void
    {
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if(!$data){
            return;
        }
        $words = $data->get('word');
        if(!$words){
            return;
        }
        foreach($words as $word){
            $word = $this->get_embedding($word);
            ddd($word);
        }

    }

    public function get_embedding($word){
        if(!is_object($word)){
            return $word;
        }
        if(!property_exists('word', $word)){
            return $word;
        }
        $text = $word->word;
        /*
        curl http://localhost:11434/api/embed -d '{
            "model": "all-minilm",
            "input": "Why is the sky blue?"

        }'
        */
        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "nomic-embed-text",
            "input": "' . $text . '"
        }\'';
        $output = shell_exec($command);
        ddd($output);
    }
}


