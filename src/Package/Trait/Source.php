<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Module\Dir;
use Raxon\Module\File;


trait Source {

    public function dictionary_create(object $flags, object $options): void
    {
        $object = $this->object();
        //read code files in /Application (php, js, css, tpl, html)
        //read code files in /mnt/Vps3/Mount/Domain/
        //read code files in /mnt/Vps3/Mount/Shared/
        //read code files in /mnt/Vps3/Mount/Package/

        $options_dir_read = [
            'extension' => [
                'php',
                'js',
                'css',
                'tpl',
                'html',
                'rax'
            ]
        ];
        $url = $object->config('project.dir.root');
        $list = $this->dir_read($url, $options_dir_read);
        $url_dictionary = $object->config('controller.dir.data') . 'Oxford.txt';
        $list_words = explode("\n", File::read($url_dictionary));
        breakpoint(count($list_words));
        dd($list_words);
    }

    public function dir_read(string $url, array $options=[]): array
    {
        $dir = new Dir();
        $files = $dir->read($url, true);
        $list = [];
        if($files){
            foreach ($files as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(in_array($file->extension, $options['extension'], true)){
                        $list[] = $file;
                    }
                }
            }
        }
        return $list;
    }
}