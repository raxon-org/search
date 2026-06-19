<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Module\Cli;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\Parallel;


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
        $list_words_application = $this->read_words($list);
        breakpoint(count($list_words_application));
        dd($list_words_application);

        breakpoint(count($list_words));
        dd($list_words);
    }

    public function read_words(array $list): array
    {
        $list_words = [];
        $count_total = count($list);
        $start = microtime(true);
        echo 'Read ' . $count_total . ' files' . PHP_EOL;
        $counter = 0;
        $threads = 8;
        $object = $this->object();
        $chunks = array_chunk($list, $threads);
        $chunk_count = count($chunks);
        $count = 0;
        $done = 0;
        $result = [];
        foreach($chunks as $chunk_nr => $chunk) {
            $closures = [];
            $forks = count($chunk);
            for ($i = 0; $i < $forks; $i++) {
                $closures[] = function () use (
                    $object,
                    $chunk,
                    $chunk_nr,
                    $chunk_count,
                    $i,
                ) {
                    if (array_key_exists($i, $chunk)) {
                        $file = $chunk[$i] ?? false;
                        if($file){
                            $list_words = [];
                            $read = File::read($file->url);
                            $words = explode(' ', $read);
                            foreach($words as $word){
                                if(!in_array($word, $list_words, true)){
                                    $list_words[] = $word;
                                }
                            }
                            return $list_words;
                        }
                    }
                    return null;
                };
            }
            $closures_chunks = array_chunk($closures, 16);
            foreach($closures_chunks as $closures_chunk){
                $list = Parallel::new()->execute($closures_chunk);
                foreach ($list as $key => $item) {
                    if (
                        $item !== null &&
                        $item !== 'progress'
                    ) {
                        $result[] = $item;
                        $count++;
                        $done++;
                    }
                }
                $percentage = round(($count / $count_total), 2);
                $duration = microtime(true) - $start;
                $eta = 'calculating...';
                if($percentage > 0){
                    $ttl = $duration / $percentage;
                    $eta = $ttl * ($count_total - $count);
                    $eta = time_format($eta, '');
                }
                echo Cli::tput('cursor.up', 1);
                echo Cli::tput('erase.line');
                echo 'Read ' .  round($percentage * 100, 2) . '% files elapsed: ' . time_format($duration, '') .', E.T.A.:' . $eta . PHP_EOL;
            }
        }
        return $result;
        /*






            //use spatie fork
            $file->read = File::read($file->url);
            $words = explode(' ', $file->read);
            foreach($words as $word){
                if(!in_array($word, $list_words, true)){
                    $list_words[] = $word;
                }
            }
            $counter++;
            if($count > 1){
                echo Cli::tput('cursor.up', 1);
                echo Cli::tput('erase.line');
                $duration = microtime(true) - $start;

                $percentage = round(($counter / $count) * 100, 2);
                $percentage_negative = 100 - $percentage;
                echo 'Read ' .  $percentage . '% files elapsed: ' . time_format($duration, '') . PHP_EOL;
            }

        }
        return $list_words;
        */
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