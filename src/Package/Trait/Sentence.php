<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Sentence {
    const VERSION = '1.0.0';
    const LIMIT = 10000;

    /**
     * @throws ObjectException
     */
    public function extract(object $flags, object $options): void
    {
        $object = $this->object();
        if (!property_exists($options, 'version')) {
            $options->version = self::VERSION;
        }
        if(!property_exists($options, 'limit')){
            $options->limit = self::LIMIT;
        }
        if(!property_exists($options, 'model_dir')){
            $dir_data = $object->config('controller.dir.data');
            $dir_search = $dir_data . 'Search' . $object->config('ds');
            $dir_version = $dir_search . $options->version . $object->config('ds');
        } else {
            $dir_version = $options->model_dir;
            if(substr($dir_version, -1, 1) !== $object->config('ds')){
                $dir_version .= $object->config('ds');
            }
        }
        $dir_word_embedding = $dir_version . 'Words' . $object->config('ds') . 'Embedding' . $object->config('ds');
        $dir_word_id = $dir_version . 'Words' . $object->config('ds') . 'Id' . $object->config('ds');
//        $dir_word_similarity = $dir_version . 'Words' . $object->config('ds') . 'Similarity' . $object->config('ds');
//        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if($data){
            $sentences = $data->get('sentence');
            foreach($sentences as $nr => $sentence){
                $count_tokens = 0;
                foreach($sentence->word as $word_id){
                    $hash_word_id = hash('sha256', $word_id);
                    $dir_word_id_hash = $dir_word_id . substr($hash_word_id, 0, 3) . $object->config('ds');
                    $source_word_id = $dir_word_id_hash . $word_id;
                    if(File::exist($source_word_id)){
                        $hash_word = File::read($source_word_id);
                        $source_word_embedding = $dir_word_embedding .
                            substr($hash_word, 0, 3) .
                            $object->config('ds') .
                            $hash_word .
                            $object->config('extension.json')
                        ;
                        if(File::exist($source_word_embedding)){
                            $data_word = $object->data_read($source_word_embedding, hash('sha256', $source_word_embedding));
                            $sentence->text[] = $data_word->get('word');
                            $count_tokens += $data_word->get('tokens');
                        }
                    }
                }
                $sentence->tokens = $count_tokens;
                ddd($sentence);
            }
        }


        if(property_exists($options, 'duration')){
            $time = microtime(true);
            $duration = $time - $object->config('time.start');
            echo "Duration: " . Time::format(round($duration, 3)) . PHP_EOL;
        }
    }
}


