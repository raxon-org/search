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

trait Paragraph {
    const VERSION = '1.0.0';
    const LIMIT = 10000;


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
        $patch = false;
        if(property_exists($options, 'patch')){
            $patch = $options->patch;
        }
        $force = false;
        if(property_exists($options, 'force')){
            $force = $options->force;
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
        $dir_paragraph = $dir_version . 'Paragraph' . $object->config('ds');
        $dir_paragraph_embedding = $dir_paragraph . 'Embedding' . $object->config('ds');
        $dir_paragraph_id = $dir_paragraph . 'Id' . $object->config('ds');
//        $dir_sentence = $dir_version . 'Sentence' . $object->config('ds');
//        $dir_sentence_embedding = $dir_sentence . 'Embedding' . $object->config('ds');
//        $dir_sentence_id = $dir_sentence . 'Id' . $object->config('ds');
//        $dir_word_similarity = $dir_version . 'Words' . $object->config('ds') . 'Similarity' . $object->config('ds');
//        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
        Dir::create($dir_paragraph_id, Dir::CHMOD);
        Dir::create($dir_paragraph_embedding, Dir::CHMOD);
        File::permission($object, ['dir1' => $dir_paragraph_id, 'dir2' => $dir_paragraph_embedding, 'dir3' => $dir_paragraph]);
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if($data){
            $paragraphs = $data->get('paragraph');
            $count = $data->count('paragraph');
            foreach($paragraphs as $nr => $paragraph){
                $count_tokens = 0;
                /*
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
                */
                $hash_paragraph_id = hash('sha256', $paragraph->id);
                $dir_paragraph_id_hash = $dir_paragraph_id . substr($hash_paragraph_id, 0, 3) . $object->config('ds');
//                $sentence->tokens = $count_tokens;
                $source_paragraph_id = $dir_paragraph_id_hash . $paragraph->id;
                if(
                    !File::exist($source_paragraph_id) ||
                    $patch === true ||
                    $force === true
                ){
                    Dir::create($dir_paragraph_id_hash, Dir::CHMOD);
                    $data_paragraph = new Data($paragraph);
//                    $hash_sentence_text = hash('sha256', implode(' ', $sentence->text));
//                    $dir_sentence_embedding_hash = $dir_sentence_embedding . substr($hash_sentence_text, 0, 3) . $object->config('ds');
//                    Dir::create($dir_sentence_embedding_hash, Dir::CHMOD);
//                    $source_sentence_hash = $dir_sentence_embedding_hash . $hash_sentence_text . $object->config('extension.json');
                    ddd($data_paragraph);
                    $data_paragraph->write($source_paragraph_id);
//                    File::write($source_sentence_id, $hash_sentence_text);
                    File::permission($object, [
                        'dir_sentence_id_hash'=>$dir_paragraph_id_hash,
//                        'dir_sentence_embedding_hash'=>$dir_sentence_embedding_hash,
                        'source_sentence_id' => $source_paragraph_id,
//                        'source_sentence_hash' => $source_sentence_hash
                    ]);
                    $percentage =round((($nr + 1) / $count) * 100, 3);
                    $time = microtime(true);
                    $duration = $time - $object->config('time.start');
                    $duration_percentage = round($duration / (($nr + 1) / $count), 3);
                    $duration_left = round($duration_percentage - $duration, 3);
                    echo  Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . $percentage . '%; Duration: ' . Time::format($duration, '') . '; Time left: ' . Time::format($duration_left) . '; ' . PHP_EOL;
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


