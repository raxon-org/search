<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Service {

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function ask(object $flags, object $options): void
    {
        $object = $this->object();
        if(!property_exists($options, 'text')){
            throw new ErrorException('Missing text option');
        }
        $dir_input = $object->config('ramdisk.url') . '33/Model/Input/';
        $dir_output = $object->config('ramdisk.url') . '33/Model/Output/';
        $uuid = Core::uuid();
        Dir::create($dir_input, Dir::CHMOD);
        Dir::create($dir_output, Dir::CHMOD);
        $ask = (object) [
            'uuid' => $uuid,
            'text' => $options->text ?? null,
            'url'  => (object) [
                'input' => $dir_input . $uuid . $object->config('extension.json'),
                'output' => $dir_output . $uuid . $object->config('extension.json')
            ]
        ];
        $data = new Data();
        $data->set('ask', $ask);
        $data->write($ask->url->input);
        //wait for output
        while(true){
            if(File::exist($ask->url->output)){
                break;
            }
            sleep(1);
        }
        File::delete($ask->url->input);
        echo File::read($ask->url->output) . PHP_EOL;
    }

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function model(object $flags, object $options): void
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
            $key_to_char = [];
            foreach($spec->data() as $nr => $record){
                if(property_exists($record, 'token')){
                    $char_to_key[$record->token] = $nr;
                    $key_to_char[$nr] = $record->token;
                }
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $key_to_char);
        }
        $dir_input = $object->config('ramdisk.url') . '33/Model/Input/';
        $dir_output = $object->config('ramdisk.url') . '33/Model/Output/';
        Dir::create($dir_input, Dir::CHMOD);
        Dir::create($dir_output, Dir::CHMOD);
        $dir = new Dir();
        while(true){
            $object->config('time.duration', microtime(true) - $object->config('time.start'));
            if($object->config('time.duration') > 60){
                break;
            }
            $read = $dir->read($dir_input);
            if(!$read){
                sleep(1);
            }
            foreach($read as $file){
                if($file->type === File::TYPE){
                    $file->read = File::read($file->url);
                    $file->node = Core::object($file->read);
                    ddd($file);
                }
            }
            usleep(500000);
        }
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


