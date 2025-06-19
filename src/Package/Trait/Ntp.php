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
                        $paragraph = $paragraphs->$paragraph_id;
                        ddd($paragraph);
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


