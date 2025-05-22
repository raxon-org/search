<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;

trait Embedding {

    /**
     * @throws ObjectException
     * @throws Exception
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
        }
        $data->set('word', $words);
        $data->write($source);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function sentence(object $flags, object $options): void
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
        $word_list = [];
        foreach($words as $word){
            $word_list[$word->id] = $word;
        }
        $sentences = $data->get('sentence');
        if(!$sentences){
            return;
        }
        foreach($sentences as $sentence){
            if(!property_exists($sentence, 'word')){
                continue;
            }
            $text = [];
            foreach($sentence->word as $word){
                $text[] = $word_list[$word]->word ?? null;
            }
            ddd($text);
        }
//        $data->write($source);
    }



    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function get_embedding(object $word): object
    {
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
        $data = new Data($output);
        $word->embedding = $data->get('embeddings.0');
        $word->model = $data->get('model');
        $word->tokens = $data->get('prompt_eval_count');
        return $word;
    }
}


