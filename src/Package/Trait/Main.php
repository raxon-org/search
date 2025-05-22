<?php
namespace Package\Raxon\Search\Trait;

use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Module\Core;
use Raxon\Module\Dir;
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
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $options->url, [
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
            $paragraph_list = $data->get('paragraph') ?? [];
            $id_paragraph = $data->get('id.paragraph') ?? 0;
            $id_paragraph++;
            $word_list = $data->get('word') ?? [];
            $id_word = $data->get('id.word') ?? 0;
            $id_word++;
            $sentence_list = $data->get('sentence') ?? [];
            $id_sentence = $data->get('id.sentence') ?? 0;
            $id_sentence++;
        } else {
            $document_list = [];
            $id_document = 1;
            $paragraph_list = [];
            $id_paragraph = 1;
            $word_list = [];
            $id_word = 1;
            $sentence_list = [];
            $id_sentence = 1;
        }
        $document = (object) [
            'id' => $id_document,
            'url' => $options->url,
            'paragraph' => []
        ];
        foreach($paragraph as $nr => $lines){
            $sentence_paragraph_list = [];
            foreach($lines as $line){
                $word_line = explode(' ', $line);
                $sentence = (object) [
                    'id' => $id_sentence,
                    'word' => [],
                    'text' => ''
                ];
                $found = false;
                foreach($word_line as $word_line_nr => $word){
                    $found = false;
                    foreach($word_list as $word_list_nr => $word_list_item){
                        if($word_list_item->word === $word){
                            $found = true;
                            $sentence->word[] = $word_list_item->id;
                            $sentence->text .= $word_list_item->word . ' ';
                            break;
                        }
                    }
                    if(!$found){
                        $word_list[] = (object) [
                            'id' => $id_word,
                            'word' => $word
                        ];
                        $sentence->word[] = $id_word;
                        $sentence->text .= $word . ' ';
                        $id_word++;
                    }
                }
                if(!$found){
                    $sentence->text = substr($sentence->text, 0, -1);
                }
                $sentence->text = rtrim($sentence->text);
                $found = false;
                foreach($sentence_list as $sentence_list_nr => $sentence_list_item){
                    if($sentence_list_item->text === $sentence->text){
                        $found = true;
                        $sentence = $sentence_list_item;
                        break;
                    }
                }
                if(!$found){
                    $sentence_list[] = $sentence;
                    $id_sentence++;
                }
                $sentence_paragraph_list[] = $sentence;
            }
            $found = false;
            foreach($paragraph_list as $paragraph_list_nr => $paragraph_list_item){
                if($paragraph_list_item->sentence === $sentence_paragraph_list){
                    $found = true;
                    $paragraph = $paragraph_list_item;
                    break;
                }
            }
            if(!$found){
                $paragraph_list[] = (object) [
                    'id' => $id_paragraph,
                    'sentence' => $sentence_paragraph_list
                ];
                $document->paragraph[] = $id_paragraph;
                $id_paragraph++;
            } else {
                $document->paragraph[] = $paragraph->id;
            }
        }
        ddd($document);
        d($word_list);
        d($sentence_list);
        ddd($paragraph_list);


        d($source);
        ddd($options);


    }
}

