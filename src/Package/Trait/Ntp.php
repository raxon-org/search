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

trait Ntp {
    const VERSION = '1.0.0';
    const LIMIT = 10000;

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function process(object $flags, object $options): void
    {
        if (!property_exists($options, 'type')) {
            $options->type = 'word';
        }
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
        $dir_word_ntp = $dir_version . 'Words' . $object->config('ds') . 'Ntp' . $object->config('ds');
        $dir_word_id = $dir_version . 'Words' . $object->config('ds') . 'Id' . $object->config('ds');
        $dir_word_embedding = $dir_version . 'Words' . $object->config('ds') . 'Embedding' . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if($data){
            $documents = $data->get('document');
            $document_count = $data->count('document');
            $paragraphs = $data->get('paragraph');
            $sentences = $data->get('sentence');
            $words = $data->get('word');
            foreach($documents as $document_id => $document){
                foreach($document->paragraph as $paragraph_id){
                    if(property_exists($paragraphs, $paragraph_id)){
                        $paragraph = $paragraphs->{$paragraph_id};
                        if(property_exists($paragraph, 'sentence')){
                            foreach($paragraph->sentence as $sentence_id){
                                if(property_exists($sentences, $sentence_id)){
                                    $sentence = $sentences->{$sentence_id};
                                    foreach($sentence->word as $word_nr => $word_id){

                                        $hash_word_id = hash('sha256', $word_id);
                                        $subdir_word_id = $dir_word_id .
                                            substr($hash_word_id, 0, 3) .
                                            $object->config('ds');
                                        $source_word_id =  $subdir_word_id .
                                            $word_id;


                                        $subdir_ntp_id = $dir_word_ntp .
                                            substr($hash_word_id, 0, 3) .
                                            $object->config('ds');
                                        $source_ntp_id = $subdir_ntp_id .
                                            $word_id .
                                            $object->config('extension.json');
                                        if(!File::exist($source_ntp_id)){
                                            $data_word_embedding = false;
                                            if(File::exist($source_word_id)) {
                                                $hash_word_embedding = File::read($source_word_id);
                                                $subdir_word_embedding = $dir_word_embedding .
                                                    substr($hash_word_embedding, 0, 3) .
                                                    $object->config('ds');
                                                $source_word_embedding = $subdir_word_embedding . $hash_word_embedding . $object->config('extension.json');
                                                $data_word_embedding = $object->data_read($source_word_embedding);
                                            }
                                            $data_ntp = new Data();
                                            $data_ntp->set('id', $word_id);
                                            $data_ntp->set('word', $data_word_embedding->get('word'));
                                        } else {
                                            $data_ntp = $object->data_read($source_ntp_id);
                                        }
                                        $next_word = $sentence->word[$word_nr + 1] ?? null;
                                        if($next_word){
                                            $hash_next_word_id = hash('sha256', $next_word);
                                            $subdir_next_word_id = $dir_word_id .
                                                substr($hash_next_word_id, 0, 3) .
                                                $object->config('ds');
                                            $source_next_word_id =  $subdir_next_word_id .
                                                $next_word;
                                            if(File::exist($source_next_word_id)) {
                                                $hash_next_word_embedding = File::read($source_next_word_id);
                                                $subdir_next_word_embedding = $dir_word_embedding .
                                                    substr($hash_next_word_embedding, 0, 3) .
                                                    $object->config('ds');
                                                $source_next_word_embedding = $subdir_next_word_embedding . $hash_next_word_embedding . $object->config('extension.json');
                                                $data_next_word_embedding = $object->data_read($source_next_word_embedding);
                                                $list = $data_ntp->get('list') ?? [];
                                                $found = false;
                                                foreach($list as $list_nr => $record){
                                                    if($record->word === $data_next_word_embedding->get('word')){
                                                        $found = $list_nr;
                                                        break;
                                                    }
                                                }
                                                if($found){
                                                    $list[$found]->count++;
                                                } else {
                                                    $list[] = (object) [
                                                        'id' => $next_word,
                                                        'word' => $data_next_word_embedding->get('word'),
                                                        'count' => 1
                                                    ];
                                                }
                                                $data_ntp->set('list', $list);
                                            }
                                            ddd($data_ntp);
                                            /*
                                            $hash_ntp_id = hash('sha256', $next_word);
                                            $subdir_ntp_id = $dir_word_ntp .
                                                substr($hash_ntp_id, 0, 3) .
                                                $object->config('ds');
                                            $source_ntp_id = $subdir_ntp_id .
                                                $word_id .
                                                $object->config('extension.json');
                                            */
                                            /*
                                            if(!File::exist($source_ntp_id)){
                                                $subdir_word_id = $dir_word_id .
                                                    substr($hash_ntp_id, 0, 3) .
                                                    $object->config('ds');
                                                $source_word_id =  $subdir_word_id .
                                                    $word_id;
                                                if(File::exist($source_word_id)){
                                                    $hash_word_embedding = File::read($source_word_id);
                                                    $subdir_word_embedding = $dir_word_embedding .
                                                        substr($hash_word_embedding, 0, 3) .
                                                        $object->config('ds');
                                                    $source_word_embedding = $subdir_word_embedding . $hash_word_embedding . $object->config('extension.json');
                                                    $data_word_embedding = $object->data_read($source_word_embedding);
                                                    $data = new Data();
                                                    $data->set('ntp.word.id', $word_id);
                                                    $data->set('ntp.word.text', $data_word_embedding->get('word'));
                                                    ddd($data);
                                                }

                                            }

                                            d($next_word);
                                            ddd($word_id);
                                            */
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                ddd($document);
            }
            d($sentences);
        }
        if(property_exists($options, 'duration')){
            $time = microtime(true);
            $duration = $time - $object->config('time.start');
            echo "Duration: " . Time::format(round($duration, 3)) . PHP_EOL;
        }
//        File::write($target, ob_get_clean());
//        File::permission($object, ['target' => $target]);
    }
}


