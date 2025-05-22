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
        $embeddings = $data->get('embedding') ?? [];
        $id_embedding = $data->get('id.embedding') ?? 0;
        $id_embedding++;
        foreach($words as $word){
            $hash = hash('sha256', $word->word);
            if(!array_key_exists($hash, $embeddings)){
                $data_embedding = $this->get_embedding($word->word);
                $embedding = (object) [
                    'id' => $id_embedding,
                    'embedding' => $data_embedding->get('embeddings.0'),
                    'model' => $data_embedding->get('model'),
                    'tokens' => $data_embedding->get('prompt_eval_count'),
                ];
                $embeddings[$hash] = $embedding;
                $id_embedding++;
            } else {
                $embedding = $embeddings[$hash];
            }
            $word->embedding = $embedding->id;
            $word->tokens = $embedding->tokens;
        }
        $data->set('embedding', $embeddings);
        $data->set('id.embedding', $id_embedding);
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
            $embedding = $this->get_embedding(implode(' ', $text));
            $sentence->embedding = $embedding->get('embeddings.0');
            $sentence->model = $embedding->get('model');
            $sentence->tokens = $embedding->get('prompt_eval_count');
        }
        $data->set('sentence', $sentences);
        $data->write($source);
    }

    public function get_embedding($text): Data
    {
        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "nomic-embed-text",
            "input": "' . $text . '"
        }\'';
        $output = shell_exec($command);
        if(substr($output, 0, 1) === '{'){
            $output = Core::object($output);
        }
        return new Data($output);
    }
}


