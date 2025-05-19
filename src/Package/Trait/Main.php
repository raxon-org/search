<?php
namespace Package\Raxon\Search\Trait;

use Raxon\Module\Core;
use Raxon\Module\File;

use Exception;
trait Main {

    /**
     * @throws Exception
     */
    public function search_install(object $flags, object $options): void
    {
        Core::interactive();
        $object = $this->object();
        echo 'Install ' . $object->request('package') . '...' . PHP_EOL;
    }

    /**
     * @throws Exception
     */
    public function dictionary_create(object $flags, object $options): void
    {
        $object = $this->object();

        $url = $object->config('project.dir.data') . 'Oxford' . $object->config('ds') . 'Output' . $object->config('ds') . 'Words.json';

        $data = $object->data_read($url);
        $list = [];
        if($data){
            foreach($data->data() as $nr => $item){
                if(property_exists($item, 'word')){
                   $list[] = $item->word;
                }
            }
        }
        $url = $object->config('controller.dir.data') . 'Oxford.txt';
        File::write($url, implode(PHP_EOL, $list));


        dd($list);


//        $url_data = $object->config('controller.dir.data') . 'words.txt';
//        ddd($url);


    }
}

