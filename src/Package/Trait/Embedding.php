<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\Sort;

trait Embedding {

    const VERSION = '1.0.0';

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function word(object $flags, object $options): void
    {
        $object = $this->object();

        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');

        Dir::create($dir_version, Dir::CHMOD);

        $source = $dir_version . 'Search' . $object->config('extension.json');
        $source_embedding = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding = $object->data_read($source_embedding);
        $data_float = $object->data_read($source_float);
        if(!$data){
            return;
        }
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        if(!$data_float){
            $data_float = new Data();
        }
        $words = $data->get('word');
        if(!$words){
            return;
        }
        $floats = $data_float->get('float') ?? (object) [];
        $float_list = [];
        $float_value_list = [];
        $float_available = [];
        if($floats){
            foreach($floats as $float){
                if(property_exists($float, 'value')){
                    $float_available[] = $float->value;
                    $float_value_list["{$float->value}"] = $float->id;
                }
                $float_list[$float->id] = $float;
            }
        }
        $id_float = $data->get('id.float') ?? 0;
        $id_float++;
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $id_embedding = $data->get('id.embedding.word') ?? 0;
        $id_embedding++;
        foreach($words as $word){
            if(property_exists($word, 'word') && $word->word === ''){
                ddd($words);
            }
            if(property_exists($word, 'word') && $word->word !== ''){
                $hash = hash('sha256', $word->word);
                if(!property_exists($embeddings, $hash)){
                    $get_embedding = $this->get_embedding($word->word, $options);
                    $embedding = (object) [
                        'id' => $id_embedding,
                        'embedding' => $get_embedding->get('embeddings.0'),
                        'model' => $get_embedding->get('model'),
                        'tokens' => $get_embedding->get('prompt_eval_count'),
                        'word' => $word->word,

                    ];
                    if(!is_array($embedding->embedding)){
                        breakpoint($embedding);
                    }
                    foreach($embedding->embedding as $nr => $value){
                        if(
                            !in_array(
                                $value,
                                $float_available,
                                true
                            )
                        ){
                            $float_list[$id_float] = (object) [
                                'id' => $id_float,
                                'value' => $value,
                                'count' => 1,
                            ];
                            $float_value_list["{$value}"] = $id_float;
                            $float_available[] = $value;
                            $embedding->embedding[$nr] = $id_float;
                            $data->set('id.float', $id_float);
                            $id_float++;
                        } else {
                            $id_float = $float_value_list["{$value}"];
                            $embedding->embedding[$nr] = $id_float;
                            if(!property_exists($float_list[$id_float], 'count')){
                                $float_list[$id_float]->count = 1;
                            } else {
                                $float_list[$id_float]->count++;
                            }
                        }
                    }
                    $embeddings->{$hash} = $embedding;
                    $data->set('id.embedding.word', $id_embedding);
                    $id_embedding++;
                } else {
                    $embedding = $embeddings->{$hash};

                }
                $word->embedding = $embedding->id;
                $word->tokens = $embedding->tokens;
            }
        }
        $data_embedding->set('embedding', $embeddings);

        $float_list = Sort::list($float_list)->with(['count' => 'desc']);
        $data_float->set('float', $float_list);
        $data->set('word', $words);
        $data->write($source);
        $data_embedding->write($source_embedding);
        $data_float->write($source_float);
        File::permission($object ,[
            'dir_data' => $dir_data,
            'dir_search' => $dir_search,
            'dir_version' => $dir_version,
            'source' => $source,
            'source_float' => $source_float,
            'source_embedding' => $source_embedding
        ]);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function sentence_piece(object $flags, object $options): void
    {
        $object = $this->object();

        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $source_embedding_word = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding_word = $object->data_read($source_embedding_word);
        $data_float = $object->data_read($source_float);
        if(!$data){
            return;
        }
        if(!$data_embedding_word){
            return;
        }
        if(!$data_float){
            return;
        }
        $words = $data->get('word');
        if(!$words){
            return;
        }
        $word_list_id = [];
        $word_list_embedding = [];
        foreach($words as $word){
            $word_list_id[$word->id] = $word;
            $word_list_embedding[$word->embedding] = $word;
        }
        $sentences = $data->get('sentence') ?? [];
        $sentence_pieces = $data->get('sentence_piece') ?? [];
        $id_sentence_piece = $data->get('id.sentence_piece') ?? 0;
        $id_sentence_piece++;
        $pieces = [];
        $pieces_count = 0;
        foreach($sentences as $sentence){
            if(
                property_exists($sentence, 'word') &&
                is_array($sentence->word)
            ){
                foreach($sentence->word as $word){
                    $pieces[] = [
                        'word' => $word,
                        'sentence' => $sentence->id
                    ];
                    $pieces_count++;
                }
            }
        }
        for($i = 0; $i < $pieces_count; $i++){
            $piece = [];
            for($j=$i; $j < ($i + 6); $j++){
                if(!array_key_exists($j, $pieces)){
                    break 2;
                }
                $piece[] = $pieces[$j] ?? null;
            }
            $sentence_piece = [
                'id' => $id_sentence_piece,
                'word' => [],
                'sentence' => [],
                'embedding' => []
            ];
            foreach($piece as $word){
                $sentence_piece['word'][] = $word['word'];
                if(
                    !in_array(
                        $word['sentence'],
                        $sentence_piece['sentence'],
                        true
                    )
                ){
                    $sentence_piece['sentence'][] = $word['sentence'];
                }
            }
            $id_sentence_piece++;
            $sentence_pieces[] = $sentence_piece;
        }
        ddd($sentence_pieces);
    }
    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function sentence(object $flags, object $options): void
    {
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Sentence' . $object->config('extension.json');
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
        $word_list = [];
        foreach($words as $word){
            $word_list[$word->id] = $word;
        }
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $id_embedding = $data->get('id.embedding.sentence') ?? 0;
        $id_embedding++;
        $sentences = $data->get('sentence');
        if(!$sentences){
            return;
        }
        foreach($sentences as $sentence){
            if(!property_exists($sentence, 'word')){
                continue;
            }
            $text = [];
            foreach($sentence->word as $word){
                $text[] = $word_list[$word]->word ?? null;
            }
            $text = implode(' ', $text);
            $hash = hash('sha256', $text);
            if(!property_exists($embeddings, $hash)){
                $get_embedding = $this->get_embedding($text, $options);
                $embedding = (object) [
                    'id' => $id_embedding,
                    'embedding' => $get_embedding->get('embeddings.0'),
                    'model' => $get_embedding->get('model'),
                    'tokens' => $get_embedding->get('prompt_eval_count'),
                ];
                $embeddings->{$hash} = $embedding;
                $data->set('id.embedding.sentence', $id_embedding);
                $id_embedding++;
            } else {
                $embedding = $embeddings->{$hash};
            }
            $sentence->embedding = $embedding->id;
            $sentence->tokens = $embedding->tokens;
        }
        $data_embedding->set('embedding', $embeddings);
        $data->set('sentence', $sentences);
        $data->write($source);
        $data_embedding->write($source_embedding);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function paragraph(object $flags, object $options): void
    {
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Paragraph' . $object->config('extension.json');
        $source_sentence_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Sentence' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding = $object->data_read($source_embedding);
        $data_sentence_embedding = $object->data_read($source_sentence_embedding);
        if(!$data){
            return;
        }
        if(!$data_sentence_embedding){
            return;
        }
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        $words = $data->get('word');
        if(!$words){
            return;
        }
        $word_list = [];
        foreach($words as $word){
            $word_list[$word->id] = $word;
        }
        $sentences = $data->get('sentence');
        if(!$sentences){
            return;
        }
        $sentence_list = [];
        foreach($sentences as $sentence){
            $sentence_list[$sentence->id] = $sentence;
        }
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $id_embedding = $data->get('id.embedding.paragraph') ?? 0;
        $id_embedding++;
        $paragraphs = $data->get('paragraph');
        if(!$paragraphs){
            return;
        }
        $sentence_embeddings = $data_sentence_embedding->get('embedding') ?? (object) [];
        $sentence_embeddings_list = [];
        foreach($sentence_embeddings as $sentence_embedding){
            $sentence_embeddings_list[$sentence_embedding->id] = $sentence_embedding;
        }
        foreach($paragraphs as $paragraph){
            $paragraph_embeddings = [];
            foreach($paragraph->sentence as $sentence_id){
                $sentence = $sentence_list[$sentence_id];
                if(property_exists($sentence, 'embedding')){
                    $paragraph_embeddings[] = $sentence_embeddings_list[$sentence->id] ?? (object) [];
                }
            }
            $set = [];
            $tokens = 0;
            foreach($paragraph_embeddings as $paragraph_embedding){
                if(
                    property_exists($paragraph_embedding, 'embedding') &&
                    is_array($paragraph_embedding->embedding)
                ){
                    foreach($paragraph_embedding->embedding as $nr => $float){
                        $set[$nr][] = $float;
                    }
                    $tokens += $paragraph_embedding->tokens;
                }
            }
            foreach($set as $nr => $list){
                $set[$nr] = $this->array_average($list);
            }
            $text = implode(PHP_EOL, $set);
            $hash = hash('sha256', $text);
            if(!property_exists($embeddings, $hash)){
                $embedding = (object) [
                    'id' => $id_embedding,
                    'embedding' => $set,
                    'model' => 'average-sentence',
                    'tokens' => $tokens,
                ];
                $embeddings->{$hash} = $embedding;
                $data->set('id.embedding.paragraph', $id_embedding);
                $id_embedding++;
            } else {
                $embedding = $embeddings->{$hash};
            }
            $paragraph->embedding = $embedding->id;
            $paragraph->tokens = $tokens;
        }
        $data_embedding->set('embedding', $embeddings);
        $data->set('paragraph', $paragraphs);
        $data->write($source);
        $data_embedding->write($source_embedding);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function document(object $flags, object $options): void
    {
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Document' . $object->config('extension.json');
        $source_paragraph_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Paragraph' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding = $object->data_read($source_embedding);
        $data_paragraph_embedding = $object->data_read($source_paragraph_embedding);
        if(!$data){
            return;
        }
        if(!$data_paragraph_embedding){
            return;
        }
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        $paragraphs = $data->get('paragraph');
        if(!$paragraphs){
            return;
        }
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $id_embedding = $data->get('id.embedding.document') ?? 0;
        $id_embedding++;
        $paragraph_embeddings = $data_paragraph_embedding->get('embedding') ?? (object) [];
        $paragraph_embeddings_list = [];
        foreach($paragraph_embeddings as $paragraph_embedding){
            $paragraph_embeddings_list[$paragraph_embedding->id] = $paragraph_embedding;
        };
        $documents = $data->get('document');
        if(!$documents){
            return;
        }
        foreach($documents as $document){
            $document_embeddings = [];
            $tokens = 0;
            foreach($document->paragraph as $paragraph_id){
                if(array_key_exists($paragraph_id, $paragraph_embeddings_list)){
                    $paragraph = $paragraph_embeddings_list[$paragraph_id];
                    $document_embeddings[] = $paragraph->embedding;
                    $tokens += $paragraph->tokens;
                }
            }
            $set = [];
            foreach($document_embeddings as $document_embedding){
                foreach($document_embedding as $nr => $float){
                    $set[$nr][] = $float;
                }
            }
            foreach($set as $nr => $list){
                $set[$nr] = $this->array_average($list);
            }
            $text = implode(PHP_EOL, $set);
            $hash = hash('sha256', $text);
            if(!property_exists($embeddings, $hash)){
                $embedding = (object) [
                    'id' => $id_embedding,
                    'embedding' => $set,
                    'model' => 'average-paragraph',
                    'tokens' => $tokens,
                ];
                $embeddings->{$hash} = $embedding;
                $data->set('id.embedding.document', $id_embedding);
                $id_embedding++;
            } else {
                $embedding = $embeddings->{$hash};
            }
            $document->embedding = $embedding->id;
            $document->tokens = $tokens;
        }
        $data_embedding->set('embedding', $embeddings);
        $data->set('document', $documents);
        $data->write($source);
        $data_embedding->write($source_embedding);
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


