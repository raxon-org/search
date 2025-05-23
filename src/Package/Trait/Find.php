<?php
namespace Package\Raxon\Search\Trait;

use Composer\Advisory\PartialSecurityAdvisory;
use Exception;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;

trait Find {
    const VERSION = '1.0.0';

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function input(object $flags, object $options): void
    {
        if(!property_exists($options, 'input')){
            throw new Exception('Option input not set');
        }
        if(!property_exists($options, 'type')){
            $options->type = 'word';
        }
        $object = $this->object();
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if(!$data){
            return;
        }
        $words = [];
        $word_list = [];
        $sentences = [];
        $sentence_list = [];
        $paragraphs = [];
        $paragraph_list = [];
        switch($options->type){
            case 'sentence':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
                $document_list = $data->get('document');
                $paragraph_list = $data->get('paragraph');
                $children = $data->get('sentence');
                $word_list = $data->get('word');

                $list = [];
                foreach($children as $child){
                    ddd($child);
                    $list[$child->embedding] = $child;
                }
                foreach($paragraph_list as $child){
                    $paragraphs[$child->id] = $child;
                }
                foreach($sentence_list as $child){
                    $sentences[$child->id] = $child;
                }
                foreach($word_list as $child){
                    $words[$child->id] = $child;
                }
                $data_embedding = $object->data_read($source_embedding);
                if(!$data_embedding){
                    $data_embedding = new Data();
                }
                $embeddings = [];
                $embeddings_list = $data_embedding->get('embedding') ?? (object) [];
                foreach($embeddings_list as $child){
                    $embeddings[$child->id] = $child;
                }
                $input = $this->get_embedding($options->input, $options);
                ddd($input);
                break;
            case 'word':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
                $children = $data->get('word');
                $paragraph_list = $data->get('paragraph');
                $sentence_list = $data->get('sentence');
                $word_list = $data->get('word');
                break;
            default:
                throw new Exception('Type not set; available types: (document, paragraph, sentence, word)');
        }
        if(!$children){
            return;
        }
        $list = [];
        foreach($children as $child){
            $list[$child->embedding] = $child;
        }
        foreach($paragraph_list as $child){
            $paragraphs[$child->id] = $child;
        }
        foreach($sentence_list as $child){
            $sentences[$child->id] = $child;
        }
        foreach($word_list as $child){
            $words[$child->id] = $child;
        }
        $data_embedding = $object->data_read($source_embedding);
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        $embeddings = [];
        $embeddings_list = $data_embedding->get('embedding') ?? (object) [];
        foreach($embeddings_list as $child){
            $embeddings[$child->id] = $child;
        }
        $input = $this->get_embedding($options->input, $options);
        $result = [];
        foreach($embeddings as $embedding_id => $embedding){
            ddd($embedding);
            $vector = $input->get('embeddings.0');
            if(is_array($vector) && is_array($embedding->embedding)){
                $similarity = $this->cosine_similarity($vector, $embedding->embedding);
                $sentence = [];
                if(
                    array_key_exists($embedding->id, $list) &&
                    property_exists($list[$embedding->id], 'word') &&
                    is_array($list[$embedding->id]->word)
                ){
                    foreach($list[$embedding->id]->word as $word_nr => $id_word){
                        $list[$embedding->id]->word[$word_nr] = $words[$id_word] ?? null;
                    }
                }
                elseif(array_key_exists($embedding->id, $list) &&
                    property_exists($list[$embedding->id], 'word') &&
                    is_string($list[$embedding->id]->word)
                ){
                    $sentence = $list[$embedding->id]->sentence ?? [];
                    if(empty($sentence)){
//                        $word = $list[$embedding->id] ?? [];
                        foreach($sentences as $sentence_id => $sentence_value){
                            foreach($sentence_value->word as $word_id){
                                if (
                                    is_int($word_id) &&
                                    array_key_exists($word_id, $words) &&
                                    property_exists($words[$word_id], 'word') &&
                                    $list[$embedding->id]->id === $word_id &&
                                    !in_array(
                                        $sentence_value->id,
                                        $sentence,
                                        true
                                    )
                                ) {
                                    $sentence[] = $sentence_value->id;
                                    break;
                                }
                                elseif(
                                    is_object($word_id) &&
                                    property_exists($word_id, 'id') &&
                                    array_key_exists($word_id->id, $words) &&
                                    property_exists($words[$word_id->id], 'word') &&
                                    $list[$embedding->id]->id === $word_id->id &&
                                    !in_array(
                                        $sentence_value->id,
                                        $sentence,
                                        true
                                    )
                                ) {
                                    $sentence[] = $sentence_value->id;
                                    break;
                                }
                            }
                        }
//                        ddd($word);
                    }
                }
                foreach($sentence as $sentence_nr => $sentence_id){
                    if(is_int($sentence_id)){
                        $sentence[$sentence_nr] = $sentences[$sentence_id] ?? null;
                        if($sentence[$sentence_nr] === null){
                            continue;
                        }
                        foreach($sentence[$sentence_nr]->word as $word_nr => $word_id){
                            if(is_int($word_id) && array_key_exists($word_id, $words)){
                                $sentence[$sentence_nr]->word[$word_nr] = $words[$word_id] ?? null;
                            }
                        }
                    }
                }
                $paragraph = [];
                $paragraph_ids = [];
                foreach($sentence as $sentence_nr => $sentence_value){
                    foreach($paragraphs as $paragraph_value){
                        if(property_exists($paragraph_value, 'sentence')){
                            foreach($paragraph_value->sentence as $paragraph_sentence_nr => $sentence_id){
                                if(
                                    is_int($sentence_id) &&
                                    $sentence_id === $sentence_value->id
                                ){
                                    if(!in_array($paragraph_value->id, $paragraph_ids, true)){
                                        $paragraph_ids[] = $paragraph_value->id;
                                        $paragraph[] = $paragraph_value;
                                    }
                                    break;
                                }
                                elseif(
                                    is_object($sentence_id) &&
                                    property_exists($sentence_id, 'id') &&
                                    $sentence_id->id === $sentence_value->id
                                ){
                                    if(!in_array($paragraph_value->id, $paragraph_ids, true)){
                                        $paragraph_ids[] = $paragraph_value->id;
                                        $paragraph[] = $paragraph_value;
                                    }
                                    break;
                                }
                            }
                        } else {
                            ddd($paragraph_value);
                        }
                    }
                }
                if(empty($paragraph)){
                    d($sentence_id ?? null);
                    breakpoint($sentence);
                }
                foreach($paragraph as $paragraph_nr => &$paragraph_value){
                    foreach($paragraph_value->sentence as $nr_paragraph_sentence => &$id_sentence){
                        if(
                            is_int($id_sentence) &&
                            array_key_exists($id_sentence, $sentences)
                        ){
                            $paragraph_value->sentence[$nr_paragraph_sentence] = $sentences[$id_sentence];
                            foreach($paragraph_value->sentence[$nr_paragraph_sentence]->word as $word_nr => $id_word){
                                if(is_int($id_word) && array_key_exists($id_word, $words)){
                                    $paragraph_value->sentence[$nr_paragraph_sentence]->word[$word_nr] = $words[$id_word];
                                }
                                elseif(
                                    is_object($id_word) &&
                                    property_exists($id_word, 'id') &&
                                    array_key_exists($id_word->id, $words)
                                ) {
                                    $paragraph_value->sentence[$nr_paragraph_sentence]->word[$word_nr] = $words[$id_word->id] ?? null;
                                } else {
                                    breakpoint($id_word);
                                }
                            }
                        } elseif(
                            is_object($id_sentence) &&
                            property_exists($id_sentence, 'id') &&
                            array_key_exists($id_sentence->id, $sentences)
                        ) {
                            $paragraph_value->sentence[$nr_paragraph_sentence] = $sentences[$id_sentence->id];
                        } else {
                            breakpoint($id_sentence);
                        }
                    }
                }
                $result["{$similarity}"] = [
                    'id' => $embedding->id,
                    'word' => $list[$embedding->id]->word ?? '',
                    'word_embedding' => $embedding->word ?? '',
                    'sentence' => $sentence,
                    'paragraph' => $paragraph,
                    'tokens' => $embedding->tokens ?? 0,
                    'similarity' => $similarity,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        ddd($result);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function get_embedding($text, $options): Data
    {
        $model = $options->model ?? 'nomic-embed-text';
        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "' . $model . '",
            "input": "' . str_replace("\n", '\\n', $text) . '"
        }\'';
        $output = shell_exec($command);
        if(substr($output, 0, 1) === '{'){
            $output = Core::object($output);
        }
        return new Data($output);
    }

    public function array_average(array $list=[]): float|int
    {
        if(empty($list)){
            return 0;
        }
        $sum = array_sum($list);
        $count = count($list);
        return $sum / $count;
    }

    public function cosine_similarity(array $vector1, array $vector2): float|int
    {
        // Compute dot product
        $dot_product = $this->dot_product($vector1, $vector2);

        // Compute magnitude of vector 1
        $magnitude1 = $this->magnitude($vector1);

        // Compute magnitude of vector 2
        $magnitude2 = $this->magnitude($vector2);
        // Compute cosine similarity
        if ($magnitude1 != 0 && $magnitude2 != 0) {
            $similarity = $dot_product / ($magnitude1 * $magnitude2);
        } else {
            $similarity = 0;
        }
        return $similarity;
    }

    public function magnitude(array $vector): float|int
    {
        // Compute magnitude of vector
        $magnitude = 0;
        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }
        return sqrt($magnitude);
    }

    public function dot_product(array $vector1, array $vector2): float|int
    {
        $dot_product = 0;
        foreach ($vector1 as $key => $value) {
            if(array_key_exists($key, $vector2)){
                $dot_product += $value * $vector2[$key];
            }
        }
        // Return the dot product
        return $dot_product;
    }
}


