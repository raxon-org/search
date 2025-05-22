<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;

trait Find {

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
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if(!$data){
            return;
        }
        $words = [];
        $word_list = [];
        $sentences = [];
        $sentence_list = [];
        switch($options->type){
            case 'document':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Document' . $object->config('extension.json');
                $children = $data->get('document');
                break;
            case 'paragraph':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Paragraph' . $object->config('extension.json');
                $children = $data->get('paragraph');
                $sentence_list = $data->get('sentence');
                $word_list = $data->get('word');
                break;
            case 'sentence':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Sentence' . $object->config('extension.json');
                $children = $data->get('sentence');
                $word_list = $data->get('word');
                break;
            case 'word':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
                $children = $data->get('word');
                $sentence_list = $data->get('sentence');
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
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $input = $this->get_embedding($options->input, $options);
        $result = [];
        foreach($embeddings as $embedding){
            $vector = $input->get('embeddings.0');
            if(is_array($vector) && is_array($embedding->embedding)){
                $similarity = $this->cosine_similarity($vector, $embedding->embedding);
                $sentence = [];
                if(
                    array_key_exists($embedding->id, $list) &&
                    property_exists($list[$embedding->id], 'word') &&
                    is_array($list[$embedding->id]->word)){
                    foreach($list[$embedding->id]->word as $word_nr => $id_word){
                        $list[$embedding->id]->word[$word_nr] = $words[$id_word] ?? null;
                    }
                }
                if(array_key_exists($embedding->id, $list)){
                    $sentence = $list[$embedding->id]->sentence ?? [];
                    if(empty($sentence)){
                        foreach($sentences as $sentence_id => $sentence_data){
                            if(is_array($sentence_data->word)){
                                foreach($sentence_data->word as $word_nr => $id_word){
                                    if($id_word == $embedding->id){
                                        $sentence[$sentence_id] = $sentence_data;
                                        break;
                                    }
                                }
                            }
                        }
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
                $result["{$similarity}"] = [
                    'id' => $embedding->id,
                    'word' => $list[$embedding->id]->word ?? '',
                    'word_embedding' => $embedding->word ?? '',
                    'sentence' => $sentence,
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


