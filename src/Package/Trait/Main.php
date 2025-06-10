<?php
namespace Package\Raxon\Search\Trait;

use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Config;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;
use Raxon\Module\Time;

trait Main {
    const VERSION = '1.0.0';

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
        $dir = $object->config('controller.dir.data');
        $url = $dir . 'Oxford.txt';
        $read = File::read($url);
        $url = $dir . 'words.txt';
        $read .= PHP_EOL . File::read($url);
        $explode = explode(PHP_EOL, $read);
        $list = [];
        foreach($explode as $nr => $word){
            $word = trim($word);
            if (empty($word)) {
                continue;
            }
            $list[$nr] = $word;
        }
        $list = array_unique($list);
        sort($list, SORT_NATURAL);
        $url = $dir . 'Dictionary.txt';
        File::write($url, implode(PHP_EOL, $list));
        File::permission($object, ['url' => $url]);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import_page(object $flags, object $options): void
    {
        if(!property_exists($options, 'url') && !property_exists($options, 'list')){
            throw new Exception('Option URL/LIST not set');
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $object = $this->object();
        $dir_search = false;
        if(!property_exists($options, 'model_dir')){
            $dir_data = $object->config('controller.dir.data');
            $dir_search = $dir_data . 'Search' . $object->config('ds');
            $dir_version = $dir_search . $options->version . $object->config('ds');
            Dir::create($dir_version, Dir::CHMOD);
            $source = $dir_version . 'Search' . $object->config('extension.json');
        } else {
            if(substr($options->model_dir, -1) !== $object->config('ds')){
                $options->model_dir .= $object->config('ds');
            }
            Dir::create($options->model_dir, Dir::CHMOD);
            $source = $options->model_dir . 'Search' . $object->config('extension.json');
        }
        Core::interactive();
        $data = $object->data_read($source);
        if(property_exists($options, 'list')){
            $options->url = Core::object(File::read($options->list), Core::ARRAY);
            if(!is_array($options->url)){
                $options->url = [$options->url];
            }
        } else {
            if(!is_array($options->url)){
                $options->url = [$options->url];
            }
        }
        $count_url = 0;
        $total_url = count($options->url);
        foreach($options->url as $url){
            try {
                $client = new GuzzleHttp\Client([
                    'timeout' => 30.0,        // Maximum time in seconds for the entire request
                    'connect_timeout' => 10.0, // Maximum time in seconds to establish a connection
                ]);
                $res = $client->request('GET', $url, [
                    'verify' => false,  // Disable SSL certificate verification (localhost)
                ]);
                $html = $res->getBody();
            }
            catch(Exception $e){
                echo (string) $e . PHP_EOL;
                continue;
            }
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($html);
            libxml_clear_errors();

            // Get plain text content
            $body = $doc->getElementsByTagName('body')->item(0);
            $plain_text = $body->textContent;
            $plain_text = str_replace(
                [
                    "\r\n",
                    "\n\r",
                    "\r",
                ],
                [
                    "\n",
                    "\n",
                    "\n",
                ],
                $plain_text
            );
            $list = explode(PHP_EOL, $plain_text);
            $paragraph_nr = 0;
            $paragraph = [];
            foreach($list as $nr => $line){
                $line = trim($line);
                if (empty($line)) {
                    $paragraph_nr++;
                    continue;
                }
                if(!array_key_exists($paragraph_nr, $paragraph)){
                    $paragraph[$paragraph_nr] = [];
                }
                $paragraph[$paragraph_nr][] = $line;
            }
            $paragraph = array_values($paragraph);
            if($data){
                $document_list = $data->get('document') ?? (object) [];
                $id_document = $data->get('id.document') ?? 0;
                $id_document++;
                $count_document = $data->get('count.document') ?? 0;
                $paragraph_list = $data->get('paragraph') ?? [];
                $id_paragraph = $data->get('id.paragraph') ?? 0;
                $id_paragraph++;
                $count_paragraph = $data->get('count.paragraph') ?? 0;
                $word_list = $data->get('word') ?? (object) [];
                $id_word = $data->get('id.word') ?? 0;
                $id_word++;
                $count_word = $data->get('count.word') ?? 0;
                $sentence_list = $data->get('sentence') ?? (object) [];
                $id_sentence = $data->get('id.sentence') ?? 0;
                $id_sentence++;
                $count_sentence = $data->get('count.sentence') ?? 0;
            } else {
                $document_list = (object) [];
                $id_document = 1;
                $count_document = 0;
                $paragraph_list = (object) [];
                $id_paragraph = 1;
                $count_paragraph = 0;
                $word_list = (object) [];
                $id_word = 1;
                $count_word = 0;
                $sentence_list = (object) [];
                $id_sentence = 1;
                $count_sentence = 0;
                $data = new Data();
            }
            $document_list_nr = null;
            $is_put = false;
            if($document_list){
                foreach($document_list as $document_list_nr => $document_list_item){
                    if($document_list_item->url === $url){
                        $id_document = $document_list_item->id;
                        $is_put = $document_list_nr;
                        break;
                    }
                }
            }
            $document = (object) [
                'id' => $id_document,
                'url' => $url,
                'paragraph' => [],
                'date' => date('Y-m-d H:i:s'),
            ];
            foreach($paragraph as $paragraph_nr => $lines){
                $sentence_paragraph_list = [];
                foreach($lines as $line){
                    $word_line = explode(' ', $line);
                    $sentence = (object) [
                        'id' => $id_sentence,
                        'word' => [],
                        'count' => 1,
                        'paragraph' => [
                            $id_paragraph
                        ]
                    ];
                    $found = false;
                    foreach($word_line as $word_line_nr => $word){
                        if($word === ''){
                            continue;
                        }
                        $found = false;
                        $count_word++;
                        foreach($word_list as $word_list_nr => $word_list_item){
                            if($word_list_item->word === $word){
                                $word_list_item->count++;
                                $found = true;
                                $sentence->word[] = $word_list_item->id;
                                break;
                            }
                        }
                        if(!$found){
                            $word_list->{$id_word} = (object) [
                                'id' => $id_word,
                                'word' => $word,
                                'count' => 1
                            ];
                            $sentence->word[] = $id_word;
                            $data->set('id.word', $id_word);
                            $id_word++;
                        }
                    }
                    $found = false;
                    foreach($sentence_list as $sentence_list_nr => $sentence_list_item){
                        if($sentence_list_item->word === $sentence->word){
                            $found = true;
                            $sentence->count++;
                            if(
                                is_array($sentence->paragraph) &&
                                !in_array($id_paragraph, $sentence->paragraph)
                            ){
                                $sentence->paragraph[] = $id_paragraph;
                            }
                            $sentence = $sentence_list_item;
                            break;
                        }
                    }
                    if(!$found){
                        $sentence_list->{$sentence->id} = $sentence;
                        $data->set('id.sentence', $id_sentence);
                        $id_sentence++;
                    }
                    $sentence_paragraph_list[] = $sentence->id;
                    $count_sentence++;
                }
                $found = false;
                foreach($paragraph_list as $paragraph_list_nr => $paragraph_list_item){
                    if($paragraph_list_item->sentence === $sentence_paragraph_list){
                        $found = true;
                        $paragraph_list_item->count++;
                        $paragraph = $paragraph_list_item;
                        break;
                    }
                }
                $count_paragraph++;
                if(!$found){
                    $paragraph_list->{$id_paragraph} = (object) [
                        'id' => $id_paragraph,
                        'sentence' => $sentence_paragraph_list,
                        'document' => [
                            $id_document
                        ],
                        'count' => 1
                    ];
                    $document->paragraph[] = $id_paragraph;
                    $data->set('id.paragraph', $id_paragraph);
                    $id_paragraph++;
                } else {
                    $document->paragraph[] = $paragraph->id;
                    $paragraph->count++;
                    if(!in_array($id_document, $paragraph->document)){
                        $paragraph->document[] = $id_document;
                    }
                }
            }
            if($is_put !== false){
                $document_list->{$document->id} = $document;
            } else {
                $document_list->{$document->id} = $document;
                $count_document++;
            }
            $data->set('paragraph', $paragraph_list);
            $data->set('sentence', $sentence_list);
            $data->set('word', $word_list);
            $data->set('document', $document_list);
            $data->set('id.document', $id_document);
            $data->set('count.document', $count_document);
            $data->set('count.paragraph', $count_paragraph);
            $data->set('count.sentence', $count_sentence);
            $data->set('count.word', $count_word);
            $count_url++;
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count_url / $total_url), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            if($count_url % 10 === 0){
                echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . round(($count_url / $total_url) * 100, 2) . '% Duration: ' . $duration . '; Total Duration: ' . $duration_percentage . '; time left: ' . $duration_left  . ' memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
            }
        }
        $data->write($source);
        if($dir_search){
            File::permission($object, [
                'dir_data' => $dir_data,
                'dir_version' => $dir_version,
                'dir_search' => $dir_search,
                'source' => $source
            ]);
        } else {
            File::permission($object, [
                'dir_model' => $options->model_dir,
                'source' => $source
            ]);
        }

        echo 'File written: ' . $source . PHP_EOL;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import_php(object $flags, object $options): void
    {
        $object = $this->object();
        Core::interactive();
        if(!property_exists($options, 'source')){
            $options->source = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/php_manual_en/';
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $dir = new Dir();
        $read = $dir->read($options->source);
        $partition = Core::array_partition($read, 25);
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            foreach($chunk as $file){
                $import[] = '-url[]=https://raxon.local/php_manual_en/' . $file->name;
            }
            $count++;
            $command = Core::binary($object) . ' raxon/search import page ' . implode(' ', $import) . ' -version='. $options->version;
            $output = shell_exec($command);
            echo $output . PHP_EOL;
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . Time::format($duration ,'', true) . '; total duration: ' . time::format($duration_percentage, '', true) . '; time left: ' . Time::format($duration_left, '', true)  . '; memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import_wiki(object $flags, object $options): void
    {
        $object = $this->object();
        Core::interactive();
        if(!property_exists($options, 'source')){
            $options->source = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/wiki/en/';
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $dir = new Dir();
        $read = $dir->read($options->source);
        $partition = Core::array_partition($read, 1000); // (100 * 1 GB ? )
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            $list = [];
            foreach($chunk as $file){
                $list[] = 'https://raxon.local/wiki/en/' . $file->name;
//                $import[] = '-url[]=https://raxon.local/wiki/en/' . $file->name;
            }
            $dir_list =
                $object->config('ramdisk.url') .
                $object->config(Config::POSIX_ID) .
                $object->config('ds') .
                'Search' .
                $object->config('ds')
            ;
            $url_list = $dir_list . $nr . $object->config('extension.json');
            $data = new Data($list);
            $data->write($url_list);
            $count++;
            /*
            $command = Core::binary($object) . ' raxon/search import page -list=' . $url_list . ' -version='. $options->version;
            $output = shell_exec($command);
            echo $output . PHP_EOL;
            */
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . Time::format($duration, '', true) . '; total duration: ' . Time::format($duration_percentage, '', true) . '; time left: ' . Time::format($duration_left, '', true)  . '; memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function generate_wiki(object $flags, object $options): void
    {
        $object = $this->object();
        Core::interactive();
        echo 'Initializing...' . PHP_EOL;
        $source = $object->config('project.dir.data') . 'Wiki' . $object->config('ds');
        $target_wiki = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/wiki/';
        $target_wiki_en = $target_wiki . 'en/';
        File::permission($object, ['target_wiki' => $target_wiki, 'target_wiki_en' => $target_wiki_en]);
        Dir::create($target_wiki_en, Dir::CHMOD);
        $dir = new Dir();
        $read = $dir->read($source);
        $chunkSize = 8192 * 128; // 1 MB (max page size is 1 MB)
        $counter = 0;
        $total = 0;
        foreach($read as $nr => $file){
            $handle = fopen($file->url, 'rb');
            if ($handle === false) {
                die("Unable to open file.");
            }
            $size = File::size($file->url);
            $data = [];
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                // Do something with $chunk
                $data[] = $chunk; // or process/save it
                $counter++;
                if($counter >= 32){  //32 MB at a time...
                    $string = implode('', $data);
                    $pages = $this->extract_pages($string);
                    $this->store_pages($pages, $target_wiki_en);
                    $data = [];
                    $data[] = $chunk; //maybe incomplete the last block so we use it again...
                    $block_size = $chunkSize * $counter;
                    $total += $block_size;
                    $duration = microtime(true) - $object->config('time.start');
                    $duration_percentage = round($duration * ($block_size / $total), 3);
                    $time_remaining = round($duration_percentage - $duration, 3);
                    echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ~' . round(($total / $size) * 100, 2) . '%; time elapsed: ' . Time::format(round($duration, 2), '', true) . '; time remaining: ' . Time::format(round($time_remaining, 2), '', true) . ';' . PHP_EOL;
                    $counter = 0;
                }
            }
            fclose($handle);
        }
        ddd($read);


        /*


        if(!property_exists($options, 'source')){
            $options->source = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/php_manual_en/';
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $dir = new Dir();
        $read = $dir->read($options->source);
        $partition = Core::array_partition($read, 25);
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            foreach($chunk as $file){
                $import[] = '-url[]=https://raxon.local/php_manual_en/' . $file->name;
            }
            $count++;
            $command = Core::binary($object) . ' raxon/search import page ' . implode(' ', $import) . ' -version='. $options->version;
            $output = shell_exec($command);
            echo $output . PHP_EOL;
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            echo 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . $duration . '; total duration: ' . $duration_percentage . '; time left: ' . $duration_left  . '; memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
        */
    }

    private function store_pages($pages=[], $target_dir=''): void
    {
        $object = $this->object();
        foreach ($pages as $page) {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadXML($page);
            libxml_clear_errors();

            // Get plain text content
            $title = $doc->getElementsByTagName('title')->item(0) ?? null;
            $text = $doc->getElementsByTagName('text')->item(0) ?? null;

            if($title && $text){
                $title_text = $title->textContent;
                $plain_text = $text->textContent;
                $html = [];
                $html[] = '<html>';
                $html[] = '<head>';
                $html[] = '<title>' . $title_text .'</title>';
                $html[] = '</head>';
                $html[] = '<body>';
                $html[] = '<h1>' . $title_text . '</h1>';
                $html[] = '<p>' . $plain_text . '</p>';
                $html[] = '</body>';
                $html[] = '</html>';
                $target_url = $target_dir . hash('sha256', $plain_text) . $object->config('extension.html');
                if(!File::exist($target_url)){
                    File::write($target_url, implode(PHP_EOL, $html));
                    File::permission($object, ['url' => $target_url]);
                }
            }
        }
    }

    private function extract_pages($string=''): array
    {
        $pages = [];
        $explode = explode('</page>', $string);
        foreach($explode as $nr => $part){
            $temp = explode('<page>', $part, 2);
            if(array_key_exists(1, $temp)){
                $page = '<page>' . str_replace(
                    [
                        "\r\n",
                        "\n\r",
                        "\r",
                    ],
                    [
                        "\n",
                        "\n",
                        "\n"
                    ],
                    $temp[1]
                ) . PHP_EOL . '</page>';
                $pages[] = $page;
            }
        }
        return $pages;
    }
}

