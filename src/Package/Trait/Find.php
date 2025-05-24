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
        if (!property_exists($options, 'input')) {
            throw new Exception('Option input not set');
        }
        if (!property_exists($options, 'type')) {
            $options->type = 'word';
        }
        $object = $this->object();
        if (!property_exists($options, 'version')) {
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $data = $object->data_read($source);
        if (!$data) {
            throw new Exception('No data for version: ' . $options->version);
        }
        $source_embedding_word = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
//        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $document_list = $data->get('document');
        $paragraph_list = $data->get('paragraph');
        $sentence_list = $data->get('sentence');
        $word_list = $data->get('word');
        $sentences = [];
        $paragraphs = [];
        $words = [];
        $vocabulary = [];
        foreach ($sentence_list as $child) {
            $sentences[$child->id] = $child;
        }
        foreach ($paragraph_list as $child) {
            $paragraphs[$child->id] = $child;
        }
        foreach ($word_list as $child) {
            $words[$child->id] = $child;
            $vocabulary[$child->word] = $child;
        }
        $data_embedding_word = $object->data_read($source_embedding_word);
        if (!$data_embedding_word) {
            return;
        }
        $data_embedding_sentence_piece = $object->data_read($source_embedding_sentence_piece);
        /*
        if (!$data_embedding_sentence_piece) {
            return;
        }
        */
        /*
        $data_float = $object->data_read($source_float);
        if (!$data_float) {
            return;
        }
        $floats = [];
        $float_list = $data_float->get('float') ?? [];
        foreach ($float_list as $child) {
            $floats[$child->id] = $child;
        }
        */
        $embedding_words = [];
        $embedding_word_list = $data_embedding_word->get('embedding');
        foreach($embedding_word_list as $child){
            $embedding_words[$child->id] = $child;
        }
        $embedding_sentence_pieces = [];
        if($data_embedding_sentence_piece){
            $embedding_sentence_piece_list = $data_embedding_sentence_piece->get('embedding') ?? [];
        } else {
            $embedding_sentence_piece_list = [];
        }

        foreach ($embedding_sentence_piece_list as $child) {
            $embedding_sentence_pieces[$child->id] = $child;
        }
        $input = $this->get_embedding($options->input, $options);
        $vector = $input->get('embeddings.0');
        /*
        $result = [];
        foreach($embedding_words as $id => $embedding_word){
            if(is_array($vector) && is_array($embedding_word->embedding)) {
                $embedding = $this->get_embedding_float($embedding_word->embedding, $floats);
                breakpoint($vector);
                breakpoint($embedding);
                $similarity = $this->cosine_similarity($vector, $embedding);
                if(!array_key_exists("{$similarity}", $result)){
                    $result["{$similarity}"] = [];
                }
                $result["{$similarity}"][] = (object)[
                    'id' => $id,
                    'word_text' => $embedding_word->word ?? null,
                    'tokens' => $embedding_word->tokens ?? 0,
                    'similarity' => $similarity,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        */
        /*
        if(array_key_exists($options->input, $vocabulary)){
            $word = $vocabulary[$options->input];
            $vector = $this->get_embedding_float($embedding_words[$word->embedding]->embedding, $floats);
        } else {
            throw new Exception('Vocabulary not found: ' . $options->input);
        }
        */
        $result = [];
        foreach($embedding_sentence_pieces as $id => $embedding_sentence_piece){
            if(is_array($vector) && is_array($embedding_sentence_piece->embedding)) {
                $similarity = [];
                foreach($embedding_sentence_piece->embedding as $nr => $embedding){
                    ddd($embedding);
                    /*
                  if(
                      array_key_exists(0, $embedding) &&
                      is_int($embedding[0])
                  ){
                      $embedding = $this->get_embedding_float($embedding, $floats);
                  }
                    */
                  $similarity[] = $this->cosine_similarity($vector, $embedding);
                }

                /**
                 * attention, add 3x the highest score 1x silver, and 1x bronze
                 */
                rsort($similarity, SORT_NATURAL);
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[0];
                $similarity[] = $similarity[1];
                $similarity[] = $similarity[2];
                $average = $this->array_average($similarity, $options);
                $word_text = [];
                foreach($embedding_sentence_piece->word as $word_id){
                    $word_text[] = $words[$word_id]->word ?? null;
                }
                if(!array_key_exists("{$average}", $result)){
                    $result["{$average}"] = [];
                }
                $result["{$average}"][] = (object)[
                    'id' => $embedding_sentence_piece->id,
                    'word' => $embedding_sentence_piece->word ?? [],
                    'sentence' => $embedding_sentence_piece->sentence ?? [],
                    'tokens' => $embedding_sentence_piece->tokens ?? 0,
                    'similarity' => $similarity,
                    'average' => $average,
                    'word_text' => $word_text,
                ];
            }
        }
        krsort($result, SORT_NATURAL);
        foreach($result as $average => $list){
            foreach($list as $nr => $record){
                echo $record->average . ' | ' . $record->id . ' ' . implode(' ', $record->word_text);
                echo '; Similarity: ';
                $output = [];
                foreach($record->similarity as $similarity){
                    $output[] = round($similarity, 4);
                }
                echo implode(' ', $output) . PHP_EOL;

            }
        }
    }

    public function get_embedding_float($embedding, $floats): array
    {
        foreach($embedding as $nr => $float_id){
            $embedding[$nr] = $floats[$float_id]->value;
        }
        return $embedding;
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function input_old(object $flags, object $options): void
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
            throw new Exception('No data for version: ' . $options->version);
        }
        $words = [];
        $word_list = [];
        $sentences = [];
        $sentence_list = [];
        $paragraphs = [];
        $paragraph_list = [];
        switch($options->type){
            case 'sentence':
                $source_embedding = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
                $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
                $document_list = $data->get('document');
                $paragraph_list = $data->get('paragraph');
                $children = $data->get('sentence');
                $word_list = $data->get('word');

                $list = [];
                foreach($children as $child){
                    $list[$child->id] = $child;
                }
                foreach($paragraph_list as $child){
                    $paragraphs[$child->id] = $child;
                }
                foreach($word_list as $child){
                    $words[$child->id] = $child;
                }
                $data_embedding = $object->data_read($source_embedding);
                if(!$data_embedding){
                    $data_embedding = new Data();
                }
                $data_float = $object->data_read($source_float);
                if(!$data_float){
                    return;
                }
                $floats = [];
                $float_list = $data_float->get('float') ?? [];
                foreach($float_list as $child){
                    $floats[$child->id] = $child;
                }
                $embeddings = [];
                $embeddings_list = $data_embedding->get('embedding') ?? [];
                foreach($embeddings_list as $child){
                    foreach($child->embedding as $embedding_nr => $float_id){
                        $child->embedding[$embedding_nr] = $floats[$float_id]->value;
                    }
                    $embeddings[$child->id] = $child;
                }
                $input = $this->get_embedding($options->input, $options);
                foreach($list as $child_id => $child){
                    $embedding = [];
                    if(property_exists($child, 'word') && is_array($child->word)){
                        foreach($child->word as $word_nr => $word_id){
                            $embedding[] = $embeddings[$word_id]->embedding;
                        }
                    }
                    d($embedding);
                }
                breakpoint($list);
                $vector = $input->get('embeddings.0');
                foreach($embeddings as $embedding_id => $embedding){

                }
                /*
                    if(is_array($vector) && is_array($embedding->embedding)){
                        $similarity = $this->cosine_similarity($vector, $embedding->embedding);
                        $sentence = [];
                    }
                */
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
            "model": "' . $model .'",
            "input": "' . str_replace(["\\", '\''], ['\\\\', '&apos;'], $text) . '"
        }\'';
        $output = shell_exec($command);
        if(substr($output, 0, 1) === '{'){
            $output = Core::object($output);
        }
        return new Data($output);
    }

    public function array_average(array $list=[], object $options) : float|int
    {
        if(empty($list)){
            return 0;
        }
        $count = 0;
        $sum = 0;
        $multiplier = $options->multiplier ?? 1;
        foreach($list as $value){
            if(property_exists($options, 'only-positive') && $value < 0) {
                continue;
            }
            $value = ($value * $multiplier);

            $sum += $value;
            $count++;
        }
        if($count === 0){
            return 0;
        }
        return ($sum / $count);
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
                if(is_object($value)){
                    trace();
                    ddd('end');
                }
                if(is_object($vector2[$key])){
                    ddd($vector2);
                    ddd('end');
                }
                $dot_product += $value * $vector2[$key];
            }
        }
        // Return the dot product
        return $dot_product;
    }
}


