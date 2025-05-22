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
        switch($options->type){
            case 'document':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Document' . $object->config('extension.json');
                break;
            case 'paragraph':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Paragraph' . $object->config('extension.json');
                break;
            case 'sentence':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Sentence' . $object->config('extension.json');
                break;
            case 'word':
                $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
                break;
            default:
                throw new Exception('Type not set; available types: (document, paragraph, sentence, word)');
        }
        $data = $object->data_read($source);
        $data_embedding = $object->data_read($source_embedding);
        if(!$data){
            return;
        }
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        $words = $data->get('word');
        if(!$words){
            return;
        }
        $embeddings = $data_embedding->get('embedding') ?? (object) [];

        $input = $this->get_embedding($options->input);
        $result = [];
        foreach($embeddings as $embedding){
            breakpoint($input);
            breakpoint($embedding);
            $similarity = $this->cosine_similarity($input->get('embeddings'), $embedding->embedding);
            ddd($similarity);
        }


        d($input);
        dd($embeddings);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function get_embedding($text): Data
    {
        $command = 'curl http://localhost:11434/api/embed -d \'{
            "model": "nomic-embed-text",
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

    public function cosine_similarity($vector1, $vector2): float|int
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

    public function magnitude($vector): float|int
    {
        // Compute magnitude of vector
        $magnitude = 0;
        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }
        return sqrt($magnitude);
    }

    public function dot_product($vector1, $vector2): float|int
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


