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
        ddd($spec);

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


