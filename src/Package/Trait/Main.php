<?php
namespace Package\Raxon\Search\Trait;

use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;
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
        if(!property_exists($options, 'url')){
            throw new Exception('Option URL not set');
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $object = $this->object();
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        Dir::create($dir_version, Dir::CHMOD);
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if(!is_array($options->url)){
            $options->url = [$options->url];
        }
        $count_url = 0;
        $total_url = count($options->url);
        foreach($options->url as $url){
            $client = new GuzzleHttp\Client();
            $res = $client->request('GET', $url, [
                'verify' => false,  // Disable SSL certificate verification (localhost)
            ]);
            $html = $res->getBody();

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
                $document_list = $data->get('document') ?? [];
                $id_document = $data->get('id.document') ?? 0;
                $id_document++;
                $count_document = $data->get('count.document') ?? 0;
                $paragraph_list = $data->get('paragraph') ?? [];
                $id_paragraph = $data->get('id.paragraph') ?? 0;
                $id_paragraph++;
                $count_paragraph = $data->get('count.paragraph') ?? 0;
                $word_list = $data->get('word') ?? [];
                $id_word = $data->get('id.word') ?? 0;
                $id_word++;
                $count_word = $data->get('count.word') ?? 0;
                $sentence_list = $data->get('sentence') ?? [];
                $id_sentence = $data->get('id.sentence') ?? 0;
                $id_sentence++;
                $count_sentence = $data->get('count.sentence') ?? 0;
            } else {
                $document_list = [];
                $id_document = 1;
                $count_document = 0;
                $paragraph_list = [];
                $id_paragraph = 1;
                $count_paragraph = 0;
                $word_list = [];
                $id_word = 1;
                $count_word = 0;
                $sentence_list = [];
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
                                $found = true;
                                $sentence->word[] = $word_list_item->id;
                                break;
                            }
                        }
                        if(!$found){
                            $word_list[] = (object) [
                                'id' => $id_word,
                                'word' => $word
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
                            $sentence = $sentence_list_item;
                            break;
                        }
                    }
                    if(!$found){
                        $sentence_list[] = $sentence;
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
                        $paragraph = $paragraph_list_item;
                        break;
                    }
                }
                $count_paragraph++;
                if(!$found){
                    $paragraph_list[] = (object) [
                        'id' => $id_paragraph,
                        'sentence' => $sentence_paragraph_list
                    ];
                    $document->paragraph[] = $id_paragraph;
                    $data->set('id.paragraph', $id_paragraph);
                    $id_paragraph++;
                } else {
                    $document->paragraph[] = $paragraph->id;
                }
            }
            if($is_put !== false){
                $document_list[$is_put] = $document;
            } else {
                $document_list[] = $document;
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
            if($count_url % 10 === 0){
                echo 'Percentage: ' . round(($count_url / $total_url) * 100, 2) . '% duration: ' . $duration . ' total duration: ' . $duration_percentage . ' memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
            }
        }
        $data->write($source);
        File::permission($object, [
            'dir_data' => $dir_data,
            'dir_version' => $dir_version,
            'dir_search' => $dir_search,
            'source' => $source
        ]);
        echo 'File written: ' . $source . PHP_EOL;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import_php(object $flags, object $options): void
    {
        $object = $this->object();
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
        $partition = Core::array_partition($read, 4);
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            foreach($chunk as $file){
                $import[] = '-url[]=https://raxon.local/php_manual_en/' . $file->name;
            }
            $count++;
            $command = Core::binary($object) . ' raxon/search import page ' . implode(' ', $import) . ' -version='. $options->version .' > /dev/null';
            exec($command);
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            echo 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . $duration . ' total duration: ' . $duration_percentage . ' memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
    }
}

