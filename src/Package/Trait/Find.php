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
        $object = $this->object();
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');

        $url_spec = $object->config('controller.dir.data') . 'Spec.json';
        $url_model = $object->config('controller.dir.data') . 'Model.json';

        $spec = $object->data_read($url_spec);
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
        d($text);
        $search = [];
        if($text){
            $split = mb_str_split($text);
            foreach($split as $nr => $char){
                if(array_key_exists($char, $char_to_key)){
                    $search[] = $char_to_key[$char];
                }
            }
        }
        $model = $object->data_read($url_model);
        if(!$model){
            throw new ErrorException('Model file not found');
        }
        $count = 0;
        $max = 100;

        $key_to_char = $object->config('key.to.char');

        $model = $model->data();
        $search_count = count($search);
        $model_count = count($model);
        $result = [];
        $result_header = [];
        foreach($model as $nr => $token_id){
            $is_found = false;
            $context_window = [];
            for($i = 0; $i < $search_count; $i++){
                $context_window[] = $model[$nr + $i] ?? null;
            }
            if($context_window === $search){
                $is_found = true;
            }
            if($is_found){
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
                //grep line from the beginning of the line to the end line
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
                $part = '';
                for($i = $nr + $search_count; $i <= $end; $i++){
                    $part .= $key_to_char[$model[$i]] ?? '';
                    break;
                }
                if(array_key_exists($part, $result)){
                    $result[$part]++;
                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                } else {
                    $result[$part] = 1;
                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                }
                $count++;
                arsort($result, SORT_NATURAL);
                $nr = 0;
                $max = 10;
                foreach($result as $part => $appearance){
                    $top_result[$part] = $appearance / $count;
                    $nr++;
                    if($nr > $max){
                        break;
                    }
                }
                ddd($top_result);
                $search[] = $char_to_key[$char];
            }
        }
        d('count: ' . $count);
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


