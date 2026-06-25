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

    /**
     * @throws Exception
     */
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
            'rax',
            'md'
        ];
        $url_root = $object->config('project.dir.root');
        $url_domain = $object->config('project.dir.domain');
        $url_package = $object->config('project.dir.package');
        $url_shared = $object->config('project.dir.shared');

        $dir = $object->config('project.dir.data') . 'Search/';
        $target = $dir . 'Data.jsonl';
        if(File::exist($target)){
            if(property_exists($options, 'force')){
                File::delete($target);
            } else {
                throw new Exception('File already exists');
            }
        }
        $list_root = $this->dir_read($url_root, $extension);
        $list_domain = $this->dir_read($url_domain, $extension);
        $list_package = $this->dir_read($url_package, $extension);
        $list_shared = $this->dir_read($url_shared, $extension);

        $list_root = $this->chunk_list($list_root);
        $list_root = $this->chunk_list_multiply($list_root);
        $list_domain = $this->chunk_list($list_domain);
        $list_domain = $this->chunk_list_multiply($list_domain);
        $list_package = $this->chunk_list($list_package);
        $list_package = $this->chunk_list_multiply($list_package);
        $list_shared = $this->chunk_list($list_shared);
        $list_shared = $this->chunk_list_multiply($list_shared);
        $this->chunk_list_write($list_root);
        $this->chunk_list_write($list_domain);
        $this->chunk_list_write($list_package);
        $this->chunk_list_write($list_shared);
    }

    /**
     * @throws Exception
     */
    public function chunk_list_write(array $list): void
    {
        $object = $this->object();
        $dir = $object->config('project.dir.data') . 'Search/';
        $target = $dir . 'Data.jsonl';
        if(!File::exist($dir)){
            Dir::create($dir, Dir::CHMOD);
            File::permission($object, [
                'dir' => $dir
            ]);
        }
        if(!File::exist($target)){
            File::touch($target);
            File::permission($object, [
                'file' => $target
            ]);
        }
        $count = count($list);
        $current = 0;
        foreach($list as $file){
            File::append($target, Core::object($file, Core::JSON_LINE));
            $current++;
            $percentage = round(($current / $count) * 100, 2);
            echo Cli::tput('cursor.up', 1);
            echo Cli::tput('erase.line');
            echo 'Write ' .  round($percentage, 2) . '% (Files: '. $current . '/' . $count .')' . PHP_EOL;
        }
    }


    public function chunk_list_multiply(array $list): array
    {
        $total = count($list);
        $current = 0;
        $total_chunks = 0;
        foreach($list as $file){
            if(count($file->chunks) > 1){
                $chunks = [];
                $chunk = [];
                $chunk_length = 0;
                foreach($file->chunks as $file_chunk){
                    foreach($file_chunk as $line){
                        $chunk[] = $line;
                        $chunk_length++;
                        if($chunk_length >= 10){
                            $chunks[] = $chunk;
                            $chunk_length = 0;
                            $chunk = [];
                        }
                    }
                }
                $chunks_count = count($chunks);
                $result = [];
                $chunk_between = [];
                foreach($chunks as $nr => $file_chunk){
                    $chunk_next = $chunks[$nr + 1] ?? null;
                    $result[] = $file_chunk;
                    //0 = 1 = 2 = 3 = 4 = 5
                    for($i = 5; $i < 10; $i++){
                        $chunk_between[] = $file_chunk[$i] ?? null;
                    }
                    for($i = 0; $i < 5; $i++){
                        $chunk_between[] = $chunk_next[$i] ?? null;
                    }
                    $result[] = $chunk_between;
                    $chunk_between = [];
                }
                $record = [];
                foreach($result as $key => $chunk){
                    foreach($chunk as $k => $collection){
                        if(is_array($collection)){
                            $record[] = implode('', $collection);
                        } else {
                            $record[] = $collection;
                        }
                    }
                    $result[$key] = implode('', $record);
                    $record = [];
                }
                $file->chunks = $result;
            } else {
                foreach($file->chunks as $nr => $file_chunk){
                    if(is_array($file_chunk)){
                        $file->chunks[$nr] = implode('', $file_chunk);
                    } else {
                        $file->chunks[$nr] = $file_chunk;
                    }
                }
            }
            $current++;
            $percentage = round(($current / $total) * 100, 2);
            $total_chunks += count($file->chunks);
            echo Cli::tput('cursor.up', 1);
            echo Cli::tput('erase.line');
            echo 'Preparing ' .  round($percentage, 2) . '% (Files: '. $current . '/' . $total .' Total chunks:' . $total_chunks .')' . PHP_EOL;
        }
        return $list;
    }

    public function chunk_list(array $list): array
    {
        $current = 0;
        $total = count($list);
        foreach($list as $file_nr => $file){
            $meta = (object) [
                'url' => $file->url,
                'name' => $file->name,
                'extension' => $file->extension,
                'size' => $file->size,
            ];
            $read = File::read($file->url);
            $split = mb_str_split($read);
            $count = count($split);
            $chunks = [];
            $chunk = [];
            $chunk[] = '{{meta("' . Core::object($meta, Core::JSON_LINE) . '")}}';
            $chunk_length = 0;
            $line = [];
            $line_nr = 0;
            $line_length = 0;
            $column_nr = 0;
            $is_comment = false;
            $is_doc_comment = false;
            $curly_count = 0;
            $before = [];
            for($i = 0; $i < $count; $i++){
                $char = $split[$i];
                $previous = $split[$i - 1] ?? null;
                if($previous !== null){
                    $before[] = $previous;
                }
                $line[] = $char;
                $column_nr++;
                if($column_nr > 80){
                    $meta = (object) [];
                    $meta->line_number = $line_nr;
                    $meta->column_number = $column_nr;
                    $chunk[] = implode('',$line)  .  ' {{meta("' . Core::object($meta, Core::JSON_LINE) . '")}}';
                    $line = [];
                    $column_nr = 0;
                    $line_nr++;
                    $chunk_length++;
                    $before = [];
                }
                if(
                    $char === '/' &&
                    $previous === '/'
                ){
                    $is_comment = true;
                }
                if(
                    $char === '/' &&
                    $previous === '*'
                ){
                    $is_doc_comment = true;
                }
                if($char === '{'){
                    $curly_count++;
                }
                if($char === '}'){
                    $curly_count--;
                }
                if($char === "\n"){
                    /*
                    if(
                        property_exists($meta, 'in') &&
                        property_exists($meta->in, 'namespace') &&
                        property_exists($meta->in, 'class') &&
                        property_exists($meta->in, 'function')
                    ){
                        $meta = (object) [
//                            'in' => (object) [
//                                'namespace' => $meta->in->namespace,
//                                'class' => $meta->in->class,
//                                'function' => $meta->in->function,
//                            ],
                            'curly_count' => $curly_count,
                        ];
                    } else {
                        $meta = (object) [
//                            'in' => (object) [
//                                'namespace' => null,
//                                'class' => null,
//                                'function' => null,
//                            ],
                            'curly_count' => $curly_count,
                        ];
                    }
                    */
                    $meta = (object) [
                        'curly_count' => $curly_count,
                    ];
                    $meta->line_number = $line_nr;
                    $meta->column_number = $column_nr;
                    $line = implode('', $line);
                    /*
                    if($file->extension === 'php'){
                        $in_namespace = $this->in_namespace($line);
                        if($in_namespace){
                            $meta->in->namespace = $in_namespace;
                        }
                        $in_class = $this->in_class($line);
                        if($in_class){
                            $meta->in->class = $in_class;
                        }
                        $in_function = $this->in_function($line);
                        if($in_function){
                            $meta->in->function = $in_function;
                        }

                    }
                    */
                    $chunk[] = $line .  ' {{meta("' . Core::object($meta, Core::JSON_LINE) . '")}}';

//                    $class = $this->is_class($line);
//                    $function = $this->is_function($line);
//                    $prototype = $this->is_prototype($line);
//                    $module = $this->is_module($line);
                    $line = [];
                    $column_nr = 0;
                    $line_nr++;
                    $chunk_length++;
                    $is_comment = false;
                }
                if($chunk_length >= 20){
                    $chunks[] = $chunk;
                    $chunk = [];
                    $chunk_length = 0;
                }
            }
            if($column_nr > 0){
                $meta = (object) [];
                $meta->line_number = $line_nr;
                $meta->column_number = $column_nr;
                /*
                $meta->in = (object) [
                    'function' => null,     //js & php
                    'class' => null,        //php
                    'namespace' => null,    //php
                    "prototype" => null,    //js
                    "module" => null,       //js
                ];
                */
                $chunk[] = implode('', $line) .  ' {{meta("' . Core::object($meta, Core::JSON_LINE) . '")}}';
                $chunk_length++;
                $chunks[] = $chunk;
            }
            if(count($chunks) === 0){
                d($read);
                dd($chunk);

            }
            $file->chunks = $chunks;
            $list[$file_nr] = $file;
            $current++;
            $percentage = round(($current / $total) * 100, 2);
            echo Cli::tput('cursor.up', 1);
            echo Cli::tput('erase.line');
            echo 'Chunking ' .  round($percentage, 2) . '% (Files: '. $current . '/' . $total .')' . PHP_EOL;
        }
        return $list;
    }

    public function in_namespace(string $line): ?string
    {
        $pattern = '/namespace\s+([a-zA-Z0-9_]+);/';
        preg_match($pattern, $line, $matches);
        return $matches[1] ?? null;
    }

    public function in_class(string $line): ?string
    {
        $pattern = '/class\s+([a-zA-Z0-9_])+/';
        preg_match($pattern, $line, $matches);
        return $matches[1] ?? null;
    }

    public function in_function(string $line): ?string
    {
        $pattern = '/function\s+([a-zA-Z0-9_])+/';
        preg_match($pattern, $line, $matches);
        $result = $matches[1] ?? null;
        if($result === null){
            $pattern = '/function+/';
            preg_match($pattern, $line, $matches);
            if($matches && count($matches) > 0){
                $result = 'anonymous';
            } else {
                $result = null;
            }
        }
        return $result;



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
                        $file->size = File::size($file->url);
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