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

trait Find {
    const VERSION = '1.0.0';
    const LIMIT = 10000;

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function input(object $flags, object $options): void
    {
        if (!property_exists($options, 'input')) {
            throw new Exception('Option input not set');
        }
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
        if(!property_exists($options, 'ramdisk')){
            $options->ramdisk = false;
        }
        if(!property_exists($options, 'memory')){
            $options->memory = false;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $source_embedding_word = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
        $source_ramdisk_user = $object->config('ramdisk.url') .
            $object->config(Config::POSIX_ID) .
            $object->config('ds');
        $source_ramdisk_user_package = $source_ramdisk_user .
            'Package' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon = $source_ramdisk_user_package .
            'Raxon' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon_search = $source_ramdisk_user_package_raxon .
            'Search' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon_search_version = $source_ramdisk_user_package_raxon_search .
            $options->version .
            $object->config('ds');
        $source_ramdisk = $source_ramdisk_user_package_raxon_search_version .
            'Search' .
            $object->config('extension.json')
        ;
        $source_ramdisk_embedding_word = $source_ramdisk_user_package_raxon_search_version .
            'Search.Embedding.Word' .
            $object->config('extension.json')
        ;
        $source_ramdisk_embedding_sentence_piece = $source_ramdisk_user_package_raxon_search_version .
            'Search.Embedding.Sentence.Piece' .
            $object->config('extension.json')
        ;
        if($options->ramdisk === true){
            if(File::exist($source_ramdisk)){
                $data = $object->data_read($source_ramdisk);
            } else {
                Dir::create($source_ramdisk_user_package_raxon_search_version, Dir::CHMOD);
                File::copy($source, $source_ramdisk);
                File::permission($object, [
                    'source_ramdisk' => $source_ramdisk,
                    'source_ramdisk_user' => $source_ramdisk_user,
                    'source_ramdisk_user_package' => $source_ramdisk_user_package,
                    'source_ramdisk_user_package_raxon' => $source_ramdisk_user_package_raxon,
                    'source_ramdisk_user_package_raxon_search' => $source_ramdisk_user_package_raxon_search,
                    'source_ramdisk_user_package_raxon_search_version' => $source_ramdisk_user_package_raxon_search_version,
                ]);
                $data = $object->data_read($source_ramdisk);
            }
            if(File::exist($source_ramdisk_embedding_word)) {
                $data_embedding_word = $object->data_read($source_ramdisk_embedding_word);
            } else {
                File::copy($source_embedding_word, $source_ramdisk_embedding_word);
                File::permission($object, [
                    'source_ramdisk_embedding_word' => $source_ramdisk_embedding_word
                ]);
                $data_embedding_word = $object->data_read($source_ramdisk_embedding_word);
            }
            if(File::exist($source_ramdisk_embedding_sentence_piece)) {
                $data_embedding_sentence_piece = $object->data_read($source_ramdisk_embedding_sentence_piece);
            } else {
                File::copy($source_embedding_sentence_piece, $source_ramdisk_embedding_sentence_piece);
                File::permission($object, [
                    'source_ramdisk_embedding_sentence_piece' => $source_ramdisk_embedding_sentence_piece
                ]);
                $data_embedding_sentence_piece = $object->data_read($source_ramdisk_embedding_sentence_piece);
            }
        }
        elseif($options->memory){
            $data = $object->data_read($source);
            $data_embedding_sentence_piece = $object->data_read($source_embedding_sentence_piece);
            $shmop = SharedMemory::open(10, 'a', 0, 0);
            if($shmop){
                $size = File::size($source_embedding_word);
                try {
                    $read = SharedMemory::read($shmop, 0, $size);
                    $data_embedding_word = new Data(Core::object($read));
                    ddd($data_embedding_word);
                    //read data
                }
                catch (Exception $e){
                    $read = File::read($source_embedding_word);
                    $size = File::size($source_embedding_word);
                    $shmop = SharedMemory::open(10, 'n', 0600, $size);
                    if($shmop){
                        SharedMemory::write($shmop, $read);
                    }
                    $data_embedding_word = new Data(Core::object($read));
                }
            } else {
                $read = File::read($source_embedding_word);
//                $gzip = gzencode($read, 9);
                $size = File::size($source_embedding_word);
                $shmop = SharedMemory::open(10, 'n', 0600, $size);
                if($shmop){
                    SharedMemory::write($shmop, $read);
                }
                $data_embedding_word = new Data(Core::object($read));
            }
        }
        else {
            $data = $object->data_read($source);
            $data_embedding_word = $object->data_read($source_embedding_word);
            $data_embedding_sentence_piece = $object->data_read($source_embedding_sentence_piece);
        }

        if (!$data) {
            throw new Exception('No data for version: ' . $options->version);
        }

        if (!$data_embedding_word) {
            return;
        }
//        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $documents = $data->get('document');
        $paragraphs = $data->get('paragraph');
        $sentences = $data->get('sentence');
        $words = $data->get('word');
//        $sentences = [];
//        $paragraphs = [];
//        $words = [];
        $vocabulary = [];
        /*
        foreach ($sentence_list as $child) {
            $sentences[$child->id] = $child;
        }
        foreach ($paragraph_list as $child) {
            $paragraphs[$child->id] = $child;
        }
        */
        foreach ($words as $child) {
//            $words[$child->id] = $child;
            $vocabulary[$child->word] = $child;
        }
        /*
        if (!$data_embedding_sentence_piece) {
            return;
        }
        */
        /*
        $data_float = $object->data_read($source_float);
        if (!$data_float) {
            return;
        }
        $floats = [];
        $float_list = $data_float->get('float') ?? [];
        foreach ($float_list as $child) {
            $floats[$child->id] = $child;
        }
        */
        $embedding_words = (object) [];
        $embedding_word_list = $data_embedding_word->get('embedding');
        foreach($embedding_word_list as $child){
            $embedding_words->{$child->id} = $child;
        }
        $embedding_sentence_pieces = (object) [];
        if($data_embedding_sentence_piece){
            $embedding_sentence_piece_list = $data_embedding_sentence_piece->get('embedding') ?? [];
        } else {
            $embedding_sentence_piece_list = [];
        }
        foreach ($embedding_sentence_piece_list as $child) {
            $embedding_sentence_pieces->{$child->id} = $child;
        }
        $input = $this->get_embedding($options->input, $options);
        if($input->has('error')){
            throw new Exception($input->get('error'));
        }
        $input = [ $input->get('embeddings.0') ];
        /*
        $result = [];
        foreach($embedding_words as $id => $embedding_word){
            if(is_array($vector) && is_array($embedding_word->embedding)) {
                $embedding = $this->get_embedding_float($embedding_word->embedding, $floats);
                breakpoint($vector);
                breakpoint($embedding);
                $similarity = $this->cosine_similarity($vector, $embedding);
                if(!array_key_exists("{$similarity}", $result)){
                    $result["{$similarity}"] = [];
                }
                $result["{$similarity}"][] = (object)[
                    'id' => $id,
                    'word_text' => $embedding_word->word ?? null,
                    'tokens' => $embedding_word->tokens ?? 0,
                    'similarity' => $similarity,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        */
        /*
        $input = explode(' ', $options->input);
        foreach($input as $nr => $value){
            $input[$nr] = trim($value);
        }
        foreach($input as $nr => $value){
            if(array_key_exists($value, $vocabulary)) {
                $input[$nr] = $embedding_words[$vocabulary[$value]->embedding]->embedding;
            }
        }
        */
        /*
        if(array_key_exists($word, $vocabulary)){
            $word = $vocabulary[$options->input];
            $vector = $this->get_embedding_float($embedding_words[$word->embedding]->embedding, $floats);
        } else {
            throw new Exception('Vocabulary not found: ' . $options->input);
        }
        */
        $result = [];
        $count = 0;
        foreach($embedding_sentence_pieces as $id => $embedding_sentence_piece){
            foreach($embedding_sentence_piece->embedding as $embedding_nr => $word_id){
                try {
//                    $embedding_sentence_piece->embedding_decode[$embedding_nr] = Core::object(gzdecode(base64_decode($embedding_words[$word_id]->embedding)), Core::OBJECT_ARRAY);
                    $embedding_sentence_piece->embedding_decode[$embedding_nr] = $embedding_words->{$word_id}->embedding;
                    if(!is_array($embedding_sentence_piece->embedding_decode[$embedding_nr])){
                        ddd($embedding_words[$word_id]);
                    }
                } catch(Exception | ErrorException | Error $e){
                    d($e);
                    ddd($embedding_words->{$word_id});
                }

            }
            foreach($input as $nr => $vector){
                if($vector && !is_array($vector)){
                    $vector = Core::object(gzdecode(base64_decode($vector)), Core::OBJECT_ARRAY);
                }
                if(is_array($vector) && is_array($embedding_sentence_piece->embedding_decode)) {
                    $similarity = [];
                    foreach($embedding_sentence_piece->embedding_decode as $embedding_decode_nr => $embedding){
                        if(!is_array($vector)){
                            continue;
                        }
                        if(!is_array($embedding)){
                            ddd($embedding);
                        }
                        /*
                      if(
                          array_key_exists(0, $embedding) &&
                          is_int($embedding[0])
                      ){
                          $embedding = $this->get_embedding_float($embedding, $floats);
                      }
                        */
                        $similarity[] = $this->cosine_similarity($vector, $embedding);
                    }
                    /**
                     * attention, add 3x the highest score 1x silver, and 1x bronze
                     */
                    rsort($similarity, SORT_NATURAL);
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[1];
                    $similarity[] = $similarity[2];
                    $average = $this->array_average($similarity, $options);
                    $length = mb_strlen($average);
                    $average = $average . str_repeat('0', 16 - $length);
                    $word_text = [];
                    foreach($embedding_sentence_piece->word as $word_id){
                        $word_text[] = $words->{$word_id}->word ?? null;
                    }
                    if(!array_key_exists("{$average}", $result)){
                        $result["{$average}"] = [];
                    }
                    $result["{$average}"][] = (object) [
                        'id' => $embedding_sentence_piece->id,
                        'word' => $embedding_sentence_piece->word ?? [],
                        'sentence' => $embedding_sentence_piece->sentence ?? [],
                        'tokens' => $embedding_sentence_piece->tokens ?? 0,
                        'similarity' => $similarity,
                        'average' => $average,
                        'word_text' => $word_text,
                        'memory' => File::size_format(memory_get_peak_usage(true))
                    ];
                }
            }
            /*
            if(is_array($vector) && is_array($embedding_sentence_piece->embedding_decode)) {
                $similarity = [];
                foreach($embedding_sentence_piece->embedding_decode as $nr => $embedding){
                  $similarity[] = $this->cosine_similarity($vector, $embedding);
                }
                rsort($similarity, SORT_NATURAL);
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[1];
                $similarity[] = $similarity[2];
                $average = $this->array_average($similarity, $options);
                $word_text = [];
                foreach($embedding_sentence_piece->word as $word_id){
                    $word_text[] = $words[$word_id]->word ?? null;
                }
                if(!array_key_exists("{$average}", $result)){
                    $result["{$average}"] = [];
                }
                $result["{$average}"][] = (object)[
                    'id' => $embedding_sentence_piece->id,
                    'word' => $embedding_sentence_piece->word ?? [],
                    'sentence' => $embedding_sentence_piece->sentence ?? [],
                    'tokens' => $embedding_sentence_piece->tokens ?? 0,
                    'similarity' => $similarity,
                    'average' => $average,
                    'word_text' => $word_text,
                    'memory' => File::size_format(memory_get_peak_usage(true))
                ];
            }
            */
            unset($embedding_sentence_piece->embedding_decode);
        }
        krsort($result, SORT_NATURAL);
        foreach($result as $average => $list){
            foreach($list as $nr => $record){
                echo $record->average . ' | ' . $record->id . ' ' . implode(' ', $record->word_text);
                echo '; Memory: ' . $record->memory;
                echo '; Similarity: ';
                $output = [];
                foreach($record->similarity as $similarity){
                    $output[] = round($similarity, 4);

                }
                echo implode(' ', $output) . PHP_EOL;
                $count++;
                if($count > $options->limit){
                    break 2;
                }

            }
        }
        if(property_exists($options, 'duration')){
            $time = microtime(true);
            $duration = round(($time - $object->config('time.start')) * 1000, 3);
            echo "Duration: " . $duration . 'msec' . PHP_EOL;
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function parallel(object $flags, object $options): void
    {
        if (!property_exists($options, 'input')) {
            throw new Exception('Option input not set');
        }
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
        if(!property_exists($options, 'ramdisk')){
            $options->ramdisk = false;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $source_embedding_word = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
        $source_ramdisk_user = $object->config('ramdisk.url') .
            $object->config(Config::POSIX_ID) .
            $object->config('ds');
        $source_ramdisk_user_package = $source_ramdisk_user .
            'Package' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon = $source_ramdisk_user_package .
            'Raxon' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon_search = $source_ramdisk_user_package_raxon .
            'Search' .
            $object->config('ds');
        $source_ramdisk_user_package_raxon_search_version = $source_ramdisk_user_package_raxon_search .
            $options->version .
            $object->config('ds');
        $source_ramdisk = $source_ramdisk_user_package_raxon_search_version .
            'Search' .
            $object->config('extension.json')
        ;
        $source_ramdisk_embedding_word = $source_ramdisk_user_package_raxon_search_version .
            'Search.Embedding.Word' .
            $object->config('extension.json')
        ;
        $source_ramdisk_embedding_sentence_piece = $source_ramdisk_user_package_raxon_search_version .
            'Search.Embedding.Sentence.Piece' .
            $object->config('extension.json')
        ;
        if($options->ramdisk === true){
            if(File::exist($source_ramdisk)){
                $data = $object->data_read($source_ramdisk);
            } else {
                Dir::create($source_ramdisk_user_package_raxon_search_version, Dir::CHMOD);
                File::copy($source, $source_ramdisk);
                File::permission($object, [
                    'source_ramdisk' => $source_ramdisk,
                    'source_ramdisk_user' => $source_ramdisk_user,
                    'source_ramdisk_user_package' => $source_ramdisk_user_package,
                    'source_ramdisk_user_package_raxon' => $source_ramdisk_user_package_raxon,
                    'source_ramdisk_user_package_raxon_search' => $source_ramdisk_user_package_raxon_search,
                    'source_ramdisk_user_package_raxon_search_version' => $source_ramdisk_user_package_raxon_search_version,
                ]);
                $data = $object->data_read($source_ramdisk);
            }
            if(File::exist($source_ramdisk_embedding_word)) {
                $data_embedding_word = $object->data_read($source_ramdisk_embedding_word);
            } else {
                File::copy($source_embedding_word, $source_ramdisk_embedding_word);
                File::permission($object, [
                    'source_ramdisk_embedding_word' => $source_ramdisk_embedding_word
                ]);
                $data_embedding_word = $object->data_read($source_ramdisk_embedding_word);
            }
            if(File::exist($source_ramdisk_embedding_sentence_piece)) {
                $data_embedding_sentence_piece = $object->data_read($source_ramdisk_embedding_sentence_piece);
            } else {
                File::copy($source_embedding_sentence_piece, $source_ramdisk_embedding_sentence_piece);
                File::permission($object, [
                    'source_ramdisk_embedding_sentence_piece' => $source_ramdisk_embedding_sentence_piece
                ]);
                $data_embedding_sentence_piece = $object->data_read($source_ramdisk_embedding_sentence_piece);
            }
        } else {
            $data = $object->data_read($source);
            $data_embedding_word = $object->data_read($source_embedding_word);
            $data_embedding_sentence_piece = $object->data_read($source_embedding_sentence_piece);
        }

        if (!$data) {
            throw new Exception('No data for version: ' . $options->version);
        }

        if (!$data_embedding_word) {
            return;
        }
//        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $document_list = $data->get('document');
        $paragraph_list = $data->get('paragraph');
        $sentence_list = $data->get('sentence');
        $word_list = $data->get('word');
        $sentences = [];
        $paragraphs = [];
        $words = [];
        $vocabulary = [];
        foreach ($sentence_list as $child) {
            $sentences[$child->id] = $child;
        }
        foreach ($paragraph_list as $child) {
            $paragraphs[$child->id] = $child;
        }
        foreach ($word_list as $child) {
            $words[$child->id] = $child;
            $vocabulary[$child->word] = $child;
        }
        /*
        if (!$data_embedding_sentence_piece) {
            return;
        }
        */
        /*
        $data_float = $object->data_read($source_float);
        if (!$data_float) {
            return;
        }
        $floats = [];
        $float_list = $data_float->get('float') ?? [];
        foreach ($float_list as $child) {
            $floats[$child->id] = $child;
        }
        */
        $embedding_words = [];
        $embedding_word_list = $data_embedding_word->get('embedding');
        foreach($embedding_word_list as $child){
            $embedding_words[$child->id] = $child;
        }
        $embedding_sentence_pieces = [];
        if($data_embedding_sentence_piece){
            $embedding_sentence_piece_list = $data_embedding_sentence_piece->get('embedding') ?? [];
        } else {
            $embedding_sentence_piece_list = [];
        }

        foreach ($embedding_sentence_piece_list as $child) {
            $embedding_sentence_pieces[$child->id] = $child;
        }
        $input = $this->get_embedding($options->input, $options);
        if($input->has('error')){
            throw new Exception($input->get('error'));
        }
        $input = [ $input->get('embeddings.0') ];
        /*
        $result = [];
        foreach($embedding_words as $id => $embedding_word){
            if(is_array($vector) && is_array($embedding_word->embedding)) {
                $embedding = $this->get_embedding_float($embedding_word->embedding, $floats);
                breakpoint($vector);
                breakpoint($embedding);
                $similarity = $this->cosine_similarity($vector, $embedding);
                if(!array_key_exists("{$similarity}", $result)){
                    $result["{$similarity}"] = [];
                }
                $result["{$similarity}"][] = (object)[
                    'id' => $id,
                    'word_text' => $embedding_word->word ?? null,
                    'tokens' => $embedding_word->tokens ?? 0,
                    'similarity' => $similarity,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        */
        /*
        $input = explode(' ', $options->input);
        foreach($input as $nr => $value){
            $input[$nr] = trim($value);
        }
        foreach($input as $nr => $value){
            if(array_key_exists($value, $vocabulary)) {
                $input[$nr] = $embedding_words[$vocabulary[$value]->embedding]->embedding;
            }
        }
        */
        /*
        if(array_key_exists($word, $vocabulary)){
            $word = $vocabulary[$options->input];
            $vector = $this->get_embedding_float($embedding_words[$word->embedding]->embedding, $floats);
        } else {
            throw new Exception('Vocabulary not found: ' . $options->input);
        }
        */
        $result = [];
        $count = 0;

        ddd(count($embedding_sentence_pieces));



        foreach($embedding_sentence_pieces as $id => $embedding_sentence_piece){
            foreach($embedding_sentence_piece->embedding as $embedding_nr => $word_id){
                try {
//                    $embedding_sentence_piece->embedding_decode[$embedding_nr] = Core::object(gzdecode(base64_decode($embedding_words[$word_id]->embedding)), Core::OBJECT_ARRAY);
                    $embedding_sentence_piece->embedding_decode[$embedding_nr] = $embedding_words[$word_id]->embedding;
                    if(!is_array($embedding_sentence_piece->embedding_decode[$embedding_nr])){
                        ddd($embedding_words[$word_id]);
                    }
                } catch(Exception | ErrorException | Error $e){
                    ddd($embedding_words[$word_id]);
                }

            }
            foreach($input as $nr => $vector){
                if($vector && !is_array($vector)){
                    $vector = Core::object(gzdecode(base64_decode($vector)), Core::OBJECT_ARRAY);
                }
                if(is_array($vector) && is_array($embedding_sentence_piece->embedding_decode)) {
                    $similarity = [];
                    foreach($embedding_sentence_piece->embedding_decode as $embedding_decode_nr => $embedding){
                        if(!is_array($vector)){
                            continue;
                        }
                        if(!is_array($embedding)){
                            ddd($embedding);
                        }
                        /*
                      if(
                          array_key_exists(0, $embedding) &&
                          is_int($embedding[0])
                      ){
                          $embedding = $this->get_embedding_float($embedding, $floats);
                      }
                        */
                        $similarity[] = $this->cosine_similarity($vector, $embedding);
                    }
                    /**
                     * attention, add 3x the highest score 1x silver, and 1x bronze
                     */
                    rsort($similarity, SORT_NATURAL);
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[0];
                    $similarity[] = $similarity[1];
                    $similarity[] = $similarity[2];
                    $average = $this->array_average($similarity, $options);
                    $length = mb_strlen($average);
                    $average = $average . str_repeat('0', 16 - $length);
                    $word_text = [];
                    foreach($embedding_sentence_piece->word as $word_id){
                        $word_text[] = $words[$word_id]->word ?? null;
                    }
                    if(!array_key_exists("{$average}", $result)){
                        $result["{$average}"] = [];
                    }
                    $result["{$average}"][] = (object) [
                        'id' => $embedding_sentence_piece->id,
                        'word' => $embedding_sentence_piece->word ?? [],
                        'sentence' => $embedding_sentence_piece->sentence ?? [],
                        'tokens' => $embedding_sentence_piece->tokens ?? 0,
                        'similarity' => $similarity,
                        'average' => $average,
                        'word_text' => $word_text,
                        'memory' => File::size_format(memory_get_peak_usage(true))
                    ];
                }
            }
            /*
            if(is_array($vector) && is_array($embedding_sentence_piece->embedding_decode)) {
                $similarity = [];
                foreach($embedding_sentence_piece->embedding_decode as $nr => $embedding){
                  $similarity[] = $this->cosine_similarity($vector, $embedding);
                }
                rsort($similarity, SORT_NATURAL);
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[1];
                $similarity[] = $similarity[2];
                $average = $this->array_average($similarity, $options);
                $word_text = [];
                foreach($embedding_sentence_piece->word as $word_id){
                    $word_text[] = $words[$word_id]->word ?? null;
                }
                if(!array_key_exists("{$average}", $result)){
                    $result["{$average}"] = [];
                }
                $result["{$average}"][] = (object)[
                    'id' => $embedding_sentence_piece->id,
                    'word' => $embedding_sentence_piece->word ?? [],
                    'sentence' => $embedding_sentence_piece->sentence ?? [],
                    'tokens' => $embedding_sentence_piece->tokens ?? 0,
                    'similarity' => $similarity,
                    'average' => $average,
                    'word_text' => $word_text,
                    'memory' => File::size_format(memory_get_peak_usage(true))
                ];
            }
            */
            unset($embedding_sentence_piece->embedding_decode);
        }
        krsort($result, SORT_NATURAL);
        foreach($result as $average => $list){
            foreach($list as $nr => $record){
                echo $record->average . ' | ' . $record->id . ' ' . implode(' ', $record->word_text);
                echo '; Memory: ' . $record->memory;
                echo '; Similarity: ';
                $output = [];
                foreach($record->similarity as $similarity){
                    $output[] = round($similarity, 4);

                }
                echo implode(' ', $output) . PHP_EOL;
                $count++;
                if($count > $options->limit){
                    break 2;
                }

            }
        }
        if(property_exists($options, 'duration')){
            $time = microtime(true);
            $duration = round(($time - $object->config('time.start')) * 1000, 3);
            echo "Duration: " . $duration . 'msec' . PHP_EOL;
        }
    }

    public function get_embedding_float($embedding, $floats): array
    {
        foreach($embedding as $nr => $float_id){
            $embedding[$nr] = $floats[$float_id]->value;
        }
        return $embedding;
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function input_old(object $flags, object $options): void
    {
        if(!property_exists($options, 'input')){
            throw new Exception('Option input not set');
        }
        if(!property_exists($options, 'type')){
            $options->type = 'word';
        }
        $object = $this->object();
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if(!$data){
            throw new Exception('No data for version: ' . $options->version);
        }
        $words = [];
        $word_list = [];
        $sentences = [];
        $sentence_list = [];
        $paragraphs = [];
        $paragraph_list = [];
        switch($options->type){
            case 'sentence':
                $source_embedding = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
                $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
                $document_list = $data->get('document');
                $paragraph_list = $data->get('paragraph');
                $children = $data->get('sentence');
                $word_list = $data->get('word');

                $list = [];
                foreach($children as $child){
                    $list[$child->id] = $child;
                }
                foreach($paragraph_list as $child){
                    $paragraphs[$child->id] = $child;
                }
                foreach($word_list as $child){
                    $words[$child->id] = $child;
                }
                $data_embedding = $object->data_read($source_embedding);
                if(!$data_embedding){
                    $data_embedding = new Data();
                }
                $data_float = $object->data_read($source_float);
                if(!$data_float){
                    return;
                }
                $floats = [];
                $float_list = $data_float->get('float') ?? [];
                foreach($float_list as $child){
                    $floats[$child->id] = $child;
                }
                $embeddings = [];
                $embeddings_list = $data_embedding->get('embedding') ?? [];
                foreach($embeddings_list as $child){
                    foreach($child->embedding as $embedding_nr => $float_id){
                        $child->embedding[$embedding_nr] = $floats[$float_id]->value;
                    }
                    $embeddings[$child->id] = $child;
                }
                $input = $this->get_embedding($options->input, $options);
                foreach($list as $child_id => $child){
                    $embedding = [];
                    if(property_exists($child, 'word') && is_array($child->word)){
                        foreach($child->word as $word_nr => $word_id){
                            $embedding[] = $embeddings[$word_id]->embedding;
                        }
                    }
                    d($embedding);
                }
                breakpoint($list);
                $vector = $input->get('embeddings.0');
                foreach($embeddings as $embedding_id => $embedding){

                }
                /*
                    if(is_array($vector) && is_array($embedding->embedding)){
                        $similarity = $this->cosine_similarity($vector, $embedding->embedding);
                        $sentence = [];
                    }
                */
                ddd($input);
                break;
            case 'word':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
                $children = $data->get('word');
                $paragraph_list = $data->get('paragraph');
                $sentence_list = $data->get('sentence');
                $word_list = $data->get('word');
                break;
            default:
                throw new Exception('Type not set; available types: (document, paragraph, sentence, word)');
        }
        if(!$children){
            return;
        }
        $list = [];
        foreach($children as $child){
            $list[$child->embedding] = $child;
        }
        foreach($paragraph_list as $child){
            $paragraphs[$child->id] = $child;
        }
        foreach($sentence_list as $child){
            $sentences[$child->id] = $child;
        }
        foreach($word_list as $child){
            $words[$child->id] = $child;
        }
        $data_embedding = $object->data_read($source_embedding);
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        $embeddings = [];
        $embeddings_list = $data_embedding->get('embedding') ?? (object) [];
        foreach($embeddings_list as $child){
            $embeddings[$child->id] = $child;
        }
        $input = $this->get_embedding($options->input, $options);
        $result = [];
        foreach($embeddings as $embedding_id => $embedding){
            ddd($embedding);
            $vector = $input->get('embeddings.0');
            if(is_array($vector) && is_array($embedding->embedding)){
                $similarity = $this->cosine_similarity($vector, $embedding->embedding);
                $sentence = [];
                if(
                    array_key_exists($embedding->id, $list) &&
                    property_exists($list[$embedding->id], 'word') &&
                    is_array($list[$embedding->id]->word)
                ){
                    foreach($list[$embedding->id]->word as $word_nr => $id_word){
                        $list[$embedding->id]->word[$word_nr] = $words[$id_word] ?? null;
                    }
                }
                elseif(array_key_exists($embedding->id, $list) &&
                    property_exists($list[$embedding->id], 'word') &&
                    is_string($list[$embedding->id]->word)
                ){
                    $sentence = $list[$embedding->id]->sentence ?? [];
                    if(empty($sentence)){
//                        $word = $list[$embedding->id] ?? [];
                        foreach($sentences as $sentence_id => $sentence_value){
                            foreach($sentence_value->word as $word_id){
                                if (
                                    is_int($word_id) &&
                                    array_key_exists($word_id, $words) &&
                                    property_exists($words[$word_id], 'word') &&
                                    $list[$embedding->id]->id === $word_id &&
                                    !in_array(
                                        $sentence_value->id,
                                        $sentence,
                                        true
                                    )
                                ) {
                                    $sentence[] = $sentence_value->id;
                                    break;
                                }
                                elseif(
                                    is_object($word_id) &&
                                    property_exists($word_id, 'id') &&
                                    array_key_exists($word_id->id, $words) &&
                                    property_exists($words[$word_id->id], 'word') &&
                                    $list[$embedding->id]->id === $word_id->id &&
                                    !in_array(
                                        $sentence_value->id,
                                        $sentence,
                                        true
                                    )
                                ) {
                                    $sentence[] = $sentence_value->id;
                                    break;
                                }
                            }
                        }
//                        ddd($word);
                    }
                }
                foreach($sentence as $sentence_nr => $sentence_id){
                    if(is_int($sentence_id)){
                        $sentence[$sentence_nr] = $sentences[$sentence_id] ?? null;
                        if($sentence[$sentence_nr] === null){
                            continue;
                        }
                        foreach($sentence[$sentence_nr]->word as $word_nr => $word_id){
                            if(is_int($word_id) && array_key_exists($word_id, $words)){
                                $sentence[$sentence_nr]->word[$word_nr] = $words[$word_id] ?? null;
                            }
                        }
                    }
                }
                $paragraph = [];
                $paragraph_ids = [];
                foreach($sentence as $sentence_nr => $sentence_value){
                    foreach($paragraphs as $paragraph_value){
                        if(property_exists($paragraph_value, 'sentence')){
                            foreach($paragraph_value->sentence as $paragraph_sentence_nr => $sentence_id){
                                if(
                                    is_int($sentence_id) &&
                                    $sentence_id === $sentence_value->id
                                ){
                                    if(!in_array($paragraph_value->id, $paragraph_ids, true)){
                                        $paragraph_ids[] = $paragraph_value->id;
                                        $paragraph[] = $paragraph_value;
                                    }
                                    break;
                                }
                                elseif(
                                    is_object($sentence_id) &&
                                    property_exists($sentence_id, 'id') &&
                                    $sentence_id->id === $sentence_value->id
                                ){
                                    if(!in_array($paragraph_value->id, $paragraph_ids, true)){
                                        $paragraph_ids[] = $paragraph_value->id;
                                        $paragraph[] = $paragraph_value;
                                    }
                                    break;
                                }
                            }
                        } else {
                            ddd($paragraph_value);
                        }
                    }
                }
                if(empty($paragraph)){
                    d($sentence_id ?? null);
                    breakpoint($sentence);
                }
                foreach($paragraph as $paragraph_nr => &$paragraph_value){
                    foreach($paragraph_value->sentence as $nr_paragraph_sentence => &$id_sentence){
                        if(
                            is_int($id_sentence) &&
                            array_key_exists($id_sentence, $sentences)
                        ){
                            $paragraph_value->sentence[$nr_paragraph_sentence] = $sentences[$id_sentence];
                            foreach($paragraph_value->sentence[$nr_paragraph_sentence]->word as $word_nr => $id_word){
                                if(is_int($id_word) && array_key_exists($id_word, $words)){
                                    $paragraph_value->sentence[$nr_paragraph_sentence]->word[$word_nr] = $words[$id_word];
                                }
                                elseif(
                                    is_object($id_word) &&
                                    property_exists($id_word, 'id') &&
                                    array_key_exists($id_word->id, $words)
                                ) {
                                    $paragraph_value->sentence[$nr_paragraph_sentence]->word[$word_nr] = $words[$id_word->id] ?? null;
                                } else {
                                    breakpoint($id_word);
                                }
                            }
                        } elseif(
                            is_object($id_sentence) &&
                            property_exists($id_sentence, 'id') &&
                            array_key_exists($id_sentence->id, $sentences)
                        ) {
                            $paragraph_value->sentence[$nr_paragraph_sentence] = $sentences[$id_sentence->id];
                        } else {
                            breakpoint($id_sentence);
                        }
                    }
                }
                $result["{$similarity}"] = [
                    'id' => $embedding->id,
                    'word' => $list[$embedding->id]->word ?? '',
                    'word_embedding' => $embedding->word ?? '',
                    'sentence' => $sentence,
                    'paragraph' => $paragraph,
                    'tokens' => $embedding->tokens ?? 0,
                    'similarity' => $similarity,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        ddd($result);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function get_embedding($text, $options): Data
    {
        $model = $options->model ?? 'nomic-embed-text';

        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "' . $model .'",
            "input": "' . str_replace(["\\", '\'', '"', "\t"], ['\\\\', '&apos;', '&quot;', '&#9;'], $text) . '"
        }\'';
        $output = shell_exec($command);
        if(substr($output, 0, 1) === '{'){
            $output = Core::object($output);
        }
        return new Data($output);
    }

    public function array_average(array $list=[], object $options) : float|int
    {
        if(empty($list)){
            return 0;
        }
        $count = 0;
        $sum = 0;
        $multiplier = $options->multiplier ?? 1;
        foreach($list as $value){
            if(property_exists($options, 'only-positive') && $value < 0) {
                continue;
            }
            $value = ($value * $multiplier);

            $sum += $value;
            $count++;
        }
        if($count === 0){
            return 0;
        }
        return ($sum / $count);
    }

    public function cosine_similarity(array $vector1, array $vector2): float|int
    {
        // Compute dot product
        $dot_product = $this->dot_product($vector1, $vector2);

        // Compute magnitude of vector 1
        $magnitude1 = $this->magnitude($vector1);

        // Compute magnitude of vector 2
        $magnitude2 = $this->magnitude($vector2);
        // Compute cosine similarity
        if ($magnitude1 != 0 && $magnitude2 != 0) {
            $similarity = $dot_product / ($magnitude1 * $magnitude2);
        } else {
            $similarity = 0;
        }
        return $similarity;
    }

    public function magnitude(array $vector): float|int
    {
        // Compute magnitude of vector
        $magnitude = 0;
        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }
        return sqrt($magnitude);
    }

    public function dot_product(array $vector1, array $vector2): float|int
    {
        $dot_product = 0;
        foreach ($vector1 as $key => $value) {
            if(array_key_exists($key, $vector2)){
                if(is_object($value)){
                    trace();
                    ddd('end');
                }
                if(is_object($vector2[$key])){
                    ddd($vector2);
                    ddd('end');
                }
                $dot_product += $value * $vector2[$key];
            }
        }
        // Return the dot product
        return $dot_product;
    }
}


