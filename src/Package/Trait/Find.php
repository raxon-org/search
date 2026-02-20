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

trait Find {

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function find(object $flags, object $options): void
    {
        //min max at second iteration is wrong

        $object = $this->object();
        Core::interactive();
        $options->iterations = $options->iterations ?? 128;
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');
        $url_spec = $object->config('controller.dir.data') . 'Spec.json';
        $url_model = $object->config('controller.dir.data') . 'Model.json';
        $spec = $object->data_read($url_spec, 'spec');
        if(!$spec){
            throw new ErrorException('Spec file not found');
        }
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            foreach($spec->data() as $nr => $char){
                $char_to_key[$char] = $nr;
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $spec->data());
        }
        $text = $options->text ?? null;
        /*
        if(property_exists($options, 'result_count')){

        } else {
            $options->result_count[] = 0;
        }
        */
//        $current_count = end($options->result_count) ?? 0;
        $current_pointer_min = $options->model_pointer_min ?? [0];
        $current_pointer_max = $options->model_pointer_max ?? [0];
        $search = [];
        if($text){
            $split = mb_str_split($text);
            foreach($split as $nr => $char){
                if(array_key_exists($char, $char_to_key)){
                    $search[] = $char_to_key[$char];
                }
            }
        }
        $count = 0;
        $result_max = 10000; //max_results
        $key_to_char = $object->config('key.to.char');
        $model  = $object->data('model.data');
        if($model === null){
            $model = $object->data_read($url_model);
            if(!$model){
                throw new ErrorException('Model file not found');
            }
            $model = $model->data();
            $object->data('model.data', $model);
        }
        $search_count = count($search);
        $model_count = $object->config('model.count');
        if($model_count === null){
            $model_count = count($model);
            $object->config('model.count', $model_count);
        }
        $result = [];
        $result_header = [];
        $start_fresh = false;
        if(property_exists($options, 'model_pointer_min')){
            $pointer_start = end($options->model_pointer_min);
        } else {
            $pointer_start = 0;
            $start_fresh = true;
        }
        if(property_exists($options, 'model_pointer_max')){
            $pointer_end = end($options->model_pointer_max);
        } else {
            $pointer_end = $model_count;
        }
        $pointer_min = [];
        $pointer_max = [];

        for($nr = $pointer_start; $nr < $pointer_end; $nr++){
//            $token_id = $model[$nr];
            $is_found = false;
            $context_window = [];
            for($i = 0; $i < $search_count; $i++){
                $context_window[] = $model[$nr + $i] ?? null;
            }
            if($context_window === $search){
                $is_found = true;
            }
            echo Cli::tput('cursor.up') . Cli::tput('erase.line') . PHP_EOL;
            d($search);
            if($is_found){
                $part = '';
                $max = $nr + $search_count + 1;
                //might need conversion for 4 spaces, 3 spaces, 2 spaces
                for($i = $nr + $search_count; $i < $max; $i++){
                    $part .= $key_to_char[$model[$i]] ?? '';
                }
                $pointer_min[$part][] = $nr;
                /*
                $header = [];
                for($i = $nr; $i >= 0; $i--){
                    if($model[$i] === $char_to_key["<HEADER_START>"]){
                        break;
                    }
                }
                $start_header = $i + 1;
                for($i = $start_header; $i < $model_count;  $i++){
                    if( $model[$i] === $char_to_key["<HEADER_END>"]){
                        break;
                    } else {
                        $header[] = $key_to_char[$model[$i]] ?? '';
                    }
                }
                $header = Core::object(implode('', $header));
                */
                //grep line from the beginning of the line to the end line
                /*
                for($i = $nr; $i >= 0; $i--){
                    if($model[$i] === $char_to_key["\n"]){
                        break; //found start of line (at +1)
                    }
                }
                $start = $i + 1;
                for($i = $nr; $i < $model_count;  $i++){
                    if( $model[$i] === $char_to_key["\n"]){
                        break;
                    }
                }
                $end = $i;
                $line = '';
                for($i = $start; $i <= $end; $i++){
                    $line .= $key_to_char[$model[$i]] ?? '';
                }
                */
                if(array_key_exists($part, $result)){
                    $result[$part]++;
//                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                } else {
                    $result[$part] = 1;
//                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                }
                $count++;
                $pointer_max[$part][] = $nr + $search_count;
                /*
                if(
                    property_exists($options, 'result_count') &&
                    $options->result_count > 1 &&
                    $count >= $options->result_count
                ){
                    break;
                }
                if($count >= $result_max){
                    break;
                }
                */
            }
            /*
            elseif(
                $start_fresh === false &&
                $pointer_max > 0 &&
                $nr > $pointer_max &&
                $pointer_max > $pointer_min // * 1.02      //minimal growth of 2%
            ){
                break;
            }
            */
        }
        arsort($result, SORT_NATURAL);
        $nr = 0;
        $max = 10;
        $top_result = [];
        foreach($result as $part => $appearance){
            $top_result[$part] = round(($appearance / $count) * 100, 2);
            $nr++;
            if($nr > $max){
                break;
            }
        }
        $top = [];
        foreach($top_result as $part => $appearance){
            $multiplier = (int) $appearance;
            for($i=0; $i < $multiplier; $i++){
                $top[] = $part;
            }
        }
        if(!array_key_exists(0, $top)){
            d($model_count);
            d($search_count);
            d($options);
            d($result);
            echo 'No results found' . PHP_EOL;
            exit(0);
        }
        $key_rand = array_rand($top);
        $search[] = $char_to_key[$top[$key_rand]] ?? null;
        $text = '';
        foreach($search as $nr => $key){
            if(array_key_exists($key, $key_to_char)){
                $text .= $key_to_char[$key];
            }
        }
        if(property_exists($options, 'iteration_count')){
            $options->iteration_count++;
        } else {
            $options->iteration_count = 1;
        }
        if($options->iteration_count > $options->iterations){
            exit(0);
        }
        $options->text = $text;
        if(!property_exists($options, 'model_pointer_min')){
            $options->model_pointer_min = [];
        }
        if(!property_exists($options, 'model_pointer_max')){
            $options->model_pointer_max = [];
        }
        if(!property_exists($options, 'result_count')){
            $options->result_count = [];
        }
        $options->model_pointer_min[] = reset($pointer_min[$top[$key_rand]]);

        /*
        $end = end($options->model_pointer_max);
        if(
            $end > 0 &&
            $current_pointer_max > $end //* 1.05
        ){ //pointer max can grow max. 5% or else it will add 1 token to the last pointer
            $pointer_max = $end;
        }
        */
        /*
        if(
            $end > 0 &&
            $count === 1
        ){
            $pointer_max = $pointer_min;
        }
        */
        $count_last = end($options->result_count);
        if($count_last > 0 && $count_last === $count){
            //same search count
        }
        elseif($count_last > 0 && $count_last < $count){
            //spread search
//            $pointer_max = $pointer_min;
        }
        elseif($count_last > 0 && $count_last > $count){
            //narrow search
//            $pointer_max = $pointer_min;
        }

        $options->model_pointer_max[] = end($pointer_max[$top[$key_rand]]);
        $options->result_count[] = $count;
//        echo Cli::tput('cursor.up') . Cli::tput('erase.line');
        if(strlen($text) > 80){
            $text = substr($text, -80);
        }
        echo 'Count: ' . $count . ' total: '. $model_count . ', start: '. $pointer_start . ', min: ' . reset($pointer_min[$top[$key_rand]]) .', max: ' . end($pointer_max[$top[$key_rand]]) . ' ' . str_replace("\n", '<br>', $text) . PHP_EOL;
//        usleep(5000);
        $this->find($flags, $options);
    }

    /**
     * @throws ObjectException
     */
    public function transform(object $file, array $spec): object
    {
        $object = $this->object();
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            foreach($spec as $nr => $char){
                $char_to_key[$char] = $nr;
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $spec);
        }
        $header = [];
        $header['file'] = $file->name;
        $header['mtime'] = File::mtime($file->url);
        $header['size'] = File::size($file->url);
        $header['extension'] = $file->extension;;
        $header = Core::object($header, Core::JSON_LINE);

        $transform = [];
        $transform[] = $char_to_key['<HEADER_START>'] ?? null;
        $split = mb_str_split($header);
        foreach($split as $nr => $char){
            if(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<HEADER_END>'] ?? null;
        $split = mb_str_split($file->read);
        foreach($split as $nr => $char){
            if(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<EOF>'] ?? null;
        $file->transform = $transform;
        return $file;
    }


}


