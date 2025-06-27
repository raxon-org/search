<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
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
    public function predict(object $flags, object $options): void
    {
        if (!property_exists($options, 'type')) {
            $options->type = 'word';
        }
        $object = $this->object();
        if (!property_exists($options, 'version')) {
            $options->version = self::VERSION;
        }
        if (!property_exists($options, 'limit')) {
            $options->limit = self::LIMIT;
        }
        if (!property_exists($options, 'model_dir')) {
            $dir_data = $object->config('controller.dir.data');
            $dir_search = $dir_data . 'Search' . $object->config('ds');
            $dir_version = $dir_search . $options->version . $object->config('ds');
        } else {
            $dir_version = $options->model_dir;
            if (substr($dir_version, -1, 1) !== $object->config('ds')) {
                $dir_version .= $object->config('ds');
            }
        }
        if (!property_exists($options, 'input')) {
            throw new Exception('Option input required...');
        }
        $dir_word_ntp = $dir_version . 'Words' . $object->config('ds') . 'Ntp' . $object->config('ds');
        $dir_word_id = $dir_version . 'Words' . $object->config('ds') . 'Id' . $object->config('ds');
        $dir_word_embedding = $dir_version . 'Words' . $object->config('ds') . 'Embedding' . $object->config('ds');

        $word_hash = hash('sha256', $options->input);
        $dir_word_embedding_subdir = $dir_word_embedding . substr($word_hash, 0, 3) . $object->config('ds'); //split in 4096 parts
        $url_word_embedding = $dir_word_embedding_subdir . $word_hash . $object->config('extension.json');
        $data_word_embedding = $object->data_read($url_word_embedding); //need different way to get the id of the word
        if($data_word_embedding){
            $data_word_next = false;
            $count = 0;
            while(true){
                if($data_word_next){
                    $word_id = $data_word_next->get('id');
                } else {
                    $word_id = $data_word_embedding->get('id');
                }
                if($word_id){
                    $word_id_hash = hash('sha256', $word_id);
                    $dir_word_ntp_subdir = $dir_word_ntp . substr($word_id_hash, 0, 3) . $object->config('ds');
                    $url_word_ntp = $dir_word_ntp_subdir . $word_id . $object->config('extension.json');
                    $data_word_ntp = $object->data_read($url_word_ntp);
                    if($data_word_ntp){
                        $max = 0;
                        foreach($data_word_ntp->get('list') as $word_id_next => $record){
                            $max += $record->count ?? 0;
                        }
                        $min = 1;
                        $random = random_int($min, $max);
                        $current = 0;
                        $selected = false;
                        foreach($data_word_ntp->get('list') as $word_id_next => $record){
                            $current += $record->count;
                            if($random <= $current){
                                $selected = $record;
                                break;
                            }
                        }
                        ddd($selected);
                    }
                }
                $count++;
                if($count === 10){
                    break;
                }
            }

        }
    }


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
        echo 'Initializing...' . PHP_EOL;
        $dir_word_ntp = $dir_version . 'Words' . $object->config('ds') . 'Ntp' . $object->config('ds');
        $dir_word_id = $dir_version . 'Words' . $object->config('ds') . 'Id' . $object->config('ds');
        $dir_word_embedding = $dir_version . 'Words' . $object->config('ds') . 'Embedding' . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        File::permission($object, ['dir' => $dir_word_ntp]);
        if($data){
            $documents = $data->get('document');
            $document_count = $data->count('document');
            $paragraphs = $data->get('paragraph');
            $sentences = $data->get('sentence');
            $words = $data->get('word');
            $cache_list = [];
            $cache = $object->data(App::CACHE);
            $count = 0;
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

                                        $hash_ntp_id = hash('sha256', $source_ntp_id);
                                        if($cache->has($hash_ntp_id)){
                                            $data_ntp = $cache->get($hash_ntp_id);
                                        } else {
                                            if (!File::exist($source_ntp_id)) {
                                                $data_word_embedding = false;
                                                if (File::exist($source_word_id)) {
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
                                                $data_ntp->set('url', $source_ntp_id);
                                            } else {
                                                $data_ntp = $object->data_read($source_ntp_id);
                                            }
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
                                                $list = $data_ntp->get('list') ?? (object) [];
                                                $found = false;
                                                foreach($list as $list_word_id => $record){
                                                    if($record->word === $data_next_word_embedding->get('word')){
                                                        $found = $list_word_id;
                                                        break;
                                                    }
                                                }
                                                if($found){
                                                    $list->{$found}->count++;
                                                } else {
                                                    $list->{$next_word} = (object) [
                                                        'word' => $data_next_word_embedding->get('word'),
                                                        'count' => 1
                                                    ];
                                                }
                                                $data_ntp->set('list', $list);
                                            }
                                        }
                                        if(!in_array($hash_ntp_id, $cache_list, true)){
                                            $cache_list[] = $hash_ntp_id;
                                        }
                                        $cache->set($hash_ntp_id, $data_ntp);
                                    }
                                }
                            }
                        }
                    }
                }
                $count++;
                $percentage = ($count / $document_count) * 100;
                $time = microtime(true);
                $duration = $time - $object->config('time.start');
                $duration_percentage = round($duration / (($count) / $document_count), 3);
                $duration_left = round($duration_percentage - $duration, 3);
                echo  Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . round($percentage, 3) . '%; Duration: ' . Time::format($duration, '') . '; Time left: ' . Time::format($duration_left) . '; ' . PHP_EOL;
                if($count % 1000 === 0){
                    foreach($cache_list as $hash){
                        $data_ntp = $cache->get($hash);
                        $data_ntp->write($data_ntp->get('url'));
                        $cache->delete($hash);
                    }
                    $cache_list = [];
                }
            }
            foreach($cache_list as $hash){
                $data_ntp = $cache->get($hash);
                $data_ntp->write($data_ntp->get('url'));
                File::permission($object, ['url' => $data_ntp->get('url')]);
                $cache->delete($hash);
            }
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


