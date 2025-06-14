<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Word {
    const VERSION = '1.0.0';
    const LIMIT = 10000;
    const THRESHOLD = 3; //minimum of count 3 words to keep the word

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function cleanup(object $flags, object $options): void
    {
        $object = $this->object();
        if (!property_exists($options, 'version')) {
            $options->version = self::VERSION;
        }
        if(!property_exists($options, 'limit')){
            $options->limit = self::LIMIT;
        }
        if(!property_exists($options, 'threshold')){
            $options->limit = self::THRESHOLD;
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
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if($data){
            $words = $data->get('word');
            $count = 0;
            foreach($words as $id => $word){
                if($word->count >= $options->threshold){
                    $count++;
                } else {
                    unset($words->id);
                }
                if($count % 10 === 0){
                    echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Keeping: ' . $count . ' words...' . PHP_EOL;
                }
            }
            $data->set('word', $words);
            $data->write($source);
            echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Keeping: ' . $count . ' words...' . PHP_EOL;
        }
    }


    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
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
        $dir_data = false;
        $dir_search = false;
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
        $dir_word_embedding = $dir_version . 'Words' . $object->config('ds') . 'Embedding' . $object->config('ds');
        $dir_word_id = $dir_version . 'Words' . $object->config('ds') . 'Id' . $object->config('ds');
        Dir::create($dir_word_id, Dir::CHMOD);
        File::permission($object, ['dir_word_id' => $dir_word_id]);
        $dir = new Dir();
        $read = $dir->read($dir_word_embedding);
        $map = [];
        if($read){
            $count = count($read);
            foreach($read as $nr => $subdir){
                if($subdir->type === Dir::TYPE){
                    $read_subdir = $dir->read($subdir->url);
                    if($read_subdir){
                        foreach($read_subdir as $file){
                            if($file->type === File::TYPE){
                                $data_word = $object->data_read($file->url);
                                $hash = hash('sha256', $data_word->get('id'));
                                $dir_word_id_hash = $dir_word_id . substr($hash, 0, 3) . $object->config('ds'); //split in 4096 parts
                                Dir::create($dir_word_id_hash, Dir::CHMOD);
                                $url_word = $dir_word_id_hash . $data_word->get('id');
                                if(!File::exist($url_word)){
                                    File::write($url_word, hash('sha256', $data_word->get('word')));
                                    File::permission($object, ['dir_word' => $dir_word_id_hash, 'url_word' => $url_word]);
                                }
                            }
                        }
                    }
                    $percentage =round((($nr + 1) / $count) * 100, 3);
                    echo  Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . $percentage . '%' . PHP_EOL;
                }
            }
        }
        if(property_exists($options, 'duration')){
            $time = microtime(true);
            $duration = $time - $object->config('time.start');
            echo "Duration: " . Time::format(round($duration, 3)) . PHP_EOL;
        }
    }
}


