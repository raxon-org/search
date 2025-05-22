<?php
namespace Package\Raxon\Search\Trait;

use Raxon\Exception\ObjectException;
use Raxon\Module\Core;

trait Embedding {

    /**
     * @throws ObjectException
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

    /**
     * @throws ObjectException
     */
    public function get_embedding($word){
        if(!is_object($word)){
            return $word;
        }
        if(!property_exists($word ,'word')){
            return $word;
        }
        $text = $word->word;
        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "nomic-embed-text",
            "input": "' . $text . '"
        }\'';
        $output = shell_exec($command);
        if(substr($output, 0, 1) === '{'){
            $output = Core::object($output);
        }


        ddd($output);
    }
}


