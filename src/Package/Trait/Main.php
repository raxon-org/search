<?php
namespace Package\Raxon\Search\Trait;

use Raxon\Module\Core;

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
        $url = $object->config('controller.dir.data') . 'words.txt';
        ddd($url);


    }
}

