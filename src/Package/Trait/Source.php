<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\Filter;
use Raxon\Module\Parallel;
use Raxon\Module\Sort;


trait Source {

    public function create_chunks(object $flags, object $options): void
    {
        /**
         * chunks of 30 lines with column 80
         * every 5 lines a new chunk
         * need meta data for each chunk:
         * - start line
         * - end line
         * - file name
         * - file path
         * - file extension
         * - file size
         * - in namespace
         * - in class
         * - in function
         *
         *
         *
         */
        $object = $this->object();
        $extension = [
            'php',
            'js',
            'css',
            'tpl',
            'html',
            'rax'
        ];
        $url_root = $object->config('project.dir.root');
        $url_domain = $object->config('project.dir.domain');
        $url_package = $object->config('project.dir.package');
        $url_shared = $object->config('project.dir.shared');
        $list_root = $this->dir_read($url_root, $extension);
        $list_domain = $this->dir_read($url_domain, $extension);
        $list_package = $this->dir_read($url_package, $extension);
        $list_shared = $this->dir_read($url_shared, $extension);

//        $list_root = $this->chunk_list($list_root);
//        $list_domain = $this->chunk_list($list_domain);
//        $list_package = $this->chunk_list($list_package);
        $list_shared = $this->chunk_list($list_shared);
        $list_shared = $this->chunk_list_multiply($list_shared);
    }

    public function chunk_list_multiply(array $list): array
    {
        foreach($list as $file){
            if(count($file->chunks) > 1){
                $chunks = [];
                $chunk = [];
                $chunk_length = 0;
                foreach($file->chunks as $file_chunk){
                    foreach($file_chunk as $line){
                        $chunk[] = $line;
                        $chunk_length++;
                        if($chunk_length >= 5){
                            $chunks[] = $chunk;
                            $chunk_length = 0;
                            $chunk = [];
                        }
                    }
                }
                $chunks_count = count($chunks);
                $list = [];
                for($i = 0; $i < $chunks_count; $i++){
                    $max = $i + 6;
                    for($j = $i; $j < $max; $j++){
                        $list[$i][$j] = $chunks[$j] ?? null;
                    }
                }
                ddd($list);
            }
        }
        return $list;
    }

    public function chunk_list(array $list): array
    {
        foreach($list as $file){
            $read = File::read($file->url);
            $split = mb_str_split($read);
            $count = count($split);
            $chunks = [];
            $chunk = [];
            $chunk_length = 0;
            $line = [];
            $line_nr = 0;
            $line_length = 0;
            for($i = 0; $i < $count; $i++){
                $char = $split[$i];
                $line[] = $char;
                $line_length++;
                if($line_length > 80){
                    $line_length = 0;
                    $line_nr++;
                    $chunk[] = implode('',$line);
                    $chunk_length++;
                    $line = [];
                }
                if($char === "\n"){
                    $line_length = 0;
                    $line_nr++;
                    $chunk[] = implode('', $line);
                    $chunk_length++;
                    $line = [];
                }
                if($chunk_length >= 30){
                    $chunks[] = $chunk;
                    $chunk = [];
                    $chunk_length = 0;
                }
            }
            $file->chunks = $chunks;
        }
        return $list;
    }

    public function dir_read(string $url, array $extension=[]){
        $dir = new Dir();
        $files = $dir->read($url, true);
        $list = [];
        if($files){
            foreach ($files as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(in_array($file->extension, $extension, true)){
                        $list[] = $file;
                    }
                }
            }
        }
        return $list;
    }





    public function dictionary_create(object $flags, object $options): void
    {
        $object = $this->object();
        if(!property_exists($options, 'parallel')){
            $options->parallel = 16;
        }
        //read code files in /Application (php, js, css, tpl, html)
        //read code files in /mnt/Vps3/Mount/Domain/
        //read code files in /mnt/Vps3/Mount/Shared/
        //read code files in /mnt/Vps3/Mount/Package/

//        $url_dictionary = $object->config('controller.dir.data') . 'Oxford.txt';
//        $list_words = explode("\n", File::read($url_dictionary));

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
//        $url = $object->config('project.dir.root');
//        $list = $this->dir_read($url, $options_dir_read);
//        $list_words_application = $this->read_words($flags, $options, $list);
        $url_domain = $object->config('project.dir.domain');
        $list_domain = $this->dir_read($url_domain, $options_dir_read);
        $list_words_domain = $this->read_words($flags, $options, $list_domain);

        $url_package = $object->config('project.dir.package');
        $list_package = $this->dir_read($url_package, $options_dir_read);
        $list_words_package = $this->read_words($flags, $options, $list_package);

        breakpoint(count($list_words_package));
        dd($list_words_package);

//        breakpoint(count($list_words_domain));
//        dd($list_words_domain);

//        breakpoint(count($list_words));
//        dd($list_words);
    }

    public function read_words(object $flags, object $options, array $list): array
    {
        $list_words = [];
        $word_count = 0;
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
                                $list_words[] = $word;
                            }
                            return $list_words;
                        }
                    }
                    return null;
                };
            }
            $closures_chunks = array_chunk($closures, $options->parallel);
            foreach($closures_chunks as $closures_chunk){
                $list = Parallel::new()->execute($closures_chunk);
                foreach ($list as $key => $item) {
                    if (
                        $item !== null &&
                        $item !== 'progress'
                    ) {
                        if(is_array($item)){
                            foreach($item as $word){
                                $list_words[] = $word;
                                $word_count++;
                            }
                        }
                        $count++;
                        $done++;
                    }
                }
                $percentage = round(($count / $count_total), 2);
                $duration = microtime(true) - $start;
                $eta = 'Calculating...';
                if($percentage > 0){
                    $ttl = $duration / $percentage;
                    $eta = $ttl - $duration;
                    $eta = Core::time_format($eta, '');
                }
                $duration = Core::time_format($duration, '');
                echo Cli::tput('cursor.up', 1);
                echo Cli::tput('erase.line');
                echo 'Read ' .  round($percentage * 100, 2) . '% (Files: '. $count . '/' . $count_total .', Words: '. Core::number_format($word_count) .') Elapsed: ' . $duration .', E.T.A.: ' . $eta . PHP_EOL;
            }
        }
        return array_unique($list_words);
    }

}