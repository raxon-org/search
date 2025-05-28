<?php
namespace Package\Raxon\Search\Trait;

use Composer\Advisory\PartialSecurityAdvisory;
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
//        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding = $object->data_read($source_embedding);
//        $data_float = $object->data_read($source_float);
        if(!$data){
            return;
        }
        if(!$data_embedding){
            $data_embedding = new Data();
        }
        /*
        if(!$data_float){
            $data_float = new Data();
        }
        */
        $words = $data->get('word');
        if(!$words){
            return;
        }
        /*
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
        */
        $embeddings = $data_embedding->get('embedding') ?? (object) [];
        $id_embedding = $data->get('id.embedding.word') ?? 0;
        $id_embedding++;
        /*
        $words = [(object) [
            'id' => 1,
            'word' => '(MVC)'
        ]];
        */
        $count_words = $data->get('id.word') ?? 0;;
        $count = 0;
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
                        'embedding' => base64_encode(gzencode(Core::object($get_embedding->get('embeddings.0'), Core::JSON_LINE), 9)),
                        'model' => $get_embedding->get('model'),
                        'tokens' => $get_embedding->get('prompt_eval_count'),
                        'word' => $word->word,
                    ];
                    /*
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
                    */
                    $embeddings->{$hash} = $embedding;
                    $data->set('id.embedding.word', $id_embedding);
                    $id_embedding++;
                } else {
                    $embedding = $embeddings->{$hash};

                }
                $word->embedding = $embedding->id;
                $word->tokens = $embedding->tokens;
                $count++;
                if($count % 10 === 0){
                    $time = microtime(true);
                    $duration = round($time - $object->config('time.start'), 3);
                    if($count_words > 0){
                        $duration_percentage = round($duration / ($count / $count_words), 3);
                        echo 'Percentage: ' . round($count / $count_words, 2) . '; Duration: ' . $duration . '; Total duration: ' . $duration_percentage . '; Memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
                    } else {
                        echo 'Percentage: ' . round($count / 1, 2) . '; Duration: ' . $duration . '; Memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
                    }

                }

            }
        }
        $data_embedding->set('embedding', $embeddings);

//        $float_list = Sort::list($float_list)->with(['count' => 'desc']);
//        $data_float->set('float', $float_list);
        $data->set('word', $words);
        $data->write($source);
        $data_embedding->write($source_embedding);
//        $data_float->write($source_float);
        File::permission($object ,[
            'dir_data' => $dir_data,
            'dir_search' => $dir_search,
            'dir_version' => $dir_version,
            'source' => $source,
//            'source_float' => $source_float,
            'source_embedding' => $source_embedding
        ]);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function sentence_piece(object $flags, object $options): void
    {
        Core::interactive();
        $object = $this->object();

        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        if(!property_exists($options, 'amount')){
            $options->amount = 6;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $source_embedding_word = $dir_version . 'Search.Embedding.Word' . $object->config('extension.json');
        $source_embedding_sentence_piece = $dir_version . 'Search.Embedding.Sentence.Piece' . $object->config('extension.json');
//        $source_float = $dir_version . 'Search.Float' . $object->config('extension.json');
        $data = $object->data_read($source);
        $data_embedding_word = $object->data_read($source_embedding_word);
        /*
        foreach($data_embedding_word->data() as $child){

            //base64_encode(gzencode(Core::object($get_embedding->get('embeddings.0'), Core::JSON_LINE), 9))
            ddd($child);
        }
        */
        $data_embedding_sentence_piece = $object->data_read($source_embedding_sentence_piece);
//        $data_float = $object->data_read($source_float);
        if(!$data){
            return;
        }
        if(!$data_embedding_word){
            return;
        }
        if(!$data_embedding_sentence_piece){
            $data_embedding_sentence_piece = new Data();
        }
        /*
        if(!$data_float){
            return;
        }
        */
        $words = $data->get('word') ?? [];
        if(!$words){
            return;
        }
        $word_list_id = [];
        $word_list_embedding = [];
        foreach($words as $word){
            $word_list_id[$word->id] = $word;
            $word_list_embedding[$word->embedding] = $word;
        }
        $embeddings_word = $data_embedding_word->get('embedding') ?? [];
        if(!$embeddings_word){
            return;
        }
        $embedding_word_list = [];
        foreach($embeddings_word as $embedding){
            $embedding_word_list[$embedding->id] = $embedding;
        }
        $embeddings = $data_embedding_sentence_piece->get('embedding') ?? (object) [];
        $embedding_list = [];
        foreach($embeddings as $embedding){
            $embedding_list[$embedding->id] = $embedding;
        }
        $id_embedding = $data->get('id.embedding.sentence_piece') ?? 0;
        $id_embedding++;
        /*
        $floats = $data_float->get('float') ?? (object) [];
        $float_list = [];
        $float_value_list = [];
        $float_available = [];
        foreach($floats as $float){
            if(property_exists($float, 'value')){
                $float_available[] = $float->value;
                $float_value_list["{$float->value}"] = $float->id;
            }
            $float_list[$float->id] = $float;
        }
        $id_float = $data->get('id.float') ?? 0;
        $id_float++;
        */
        $sentences = $data->get('sentence') ?? [];
        $sentence_pieces = $data->get('sentence_piece') ?? [];
        $id_sentence_piece = $data->get('id.sentence_piece') ?? 0;
        $id_sentence_piece++;
        $sentence_pieces_hashes = [];
        foreach($sentence_pieces as $sentence_piece){
            $sentence_pieces_hashes[] = $sentence_piece->hash;
        }
        $pieces = [];
        $pieces_count = 0;
        foreach($sentences as $sentence){
            if(
                property_exists($sentence, 'word') &&
                is_array($sentence->word)
            ){
                foreach($sentence->word as $word){
                    $pieces[] = (object) [
                        'word' => $word,
                        'sentence' => $sentence->id
                    ];
                    $pieces_count++;
                }
            }
        }
        for($i = 0; $i < $pieces_count; $i+=$options->amount){
            $piece = [];
            for($j=$i; $j < ($i + $options->amount); $j++){
                if(!array_key_exists($j, $pieces)){
                    break 2;
                }
                $piece[] = $pieces[$j] ?? null;
            }
            $sentence_piece = (object) [
                'id' => $id_sentence_piece,
                'word' => [],
                'sentence' => [],
            ];
            foreach($piece as $word){
                $sentence_piece->word[] = $word->word;
                if(
                    !in_array(
                        $word->sentence,
                        $sentence_piece->sentence,
                        true
                    )
                ){
                    $sentence_piece->sentence[] = $word->sentence;
                }
            }
            $hash = (object) [
                'word' => $sentence_piece->word,
//                'sentence' => $sentence_piece->sentence
            ];
            $sentence_piece->hash = hash('sha256', Core::object($hash, Core::JSON_LINE));
            if(
                !in_array(
                    $sentence_piece->hash,
                    $sentence_pieces_hashes,
                    true
                )
            ){
                $sentence_piece->count = 1;
                $embeddings_sentence_piece = [];
                $tokens = 0;
                foreach($sentence_piece->word as $id_word){
                    if(array_key_exists($id_word, $word_list_id)){
                        $word = $word_list_id[$id_word];
                        $tokens += $word->tokens;
                        $embeddings_sentence_piece[] = $embedding_word_list[$word->embedding]->id;
                    }
                }
                $sentence_piece->embedding = $embeddings_sentence_piece;
//                ddd($embeddings_sentence_piece);
//                $sentence_piece->embedding = $this->get_embedding_sentence_piece($embeddings_sentence_piece);

                $embedding = (object) [
                    'id' => $id_embedding,
//                    'embedding' => base64_encode(gzencode(Core::object($sentence_piece->embedding, Core::JSON_LINE), 9)),
                    'embedding' => $sentence_piece->embedding,
                    'model' => 'average-words-6',
                    'tokens' => $tokens,
                    'word' => $sentence_piece->word,
                    'sentence' => $sentence_piece->sentence,
                    'hash' => $sentence_piece->hash
                ];
                /*
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
                */
                if(!property_exists($embeddings, $embedding->hash)){
                    $embeddings->{$embedding->hash} = $embedding;
                    $data->set('id.embedding.sentence_piece', $id_embedding);
                    $id_embedding++;
                } else {
                    $embedding  = $embeddings->{$embedding->hash};
                }
                $sentence_piece->embedding = $embedding->id;
                $sentence_pieces[] = $sentence_piece;
                $sentence_pieces_hashes[] = $sentence_piece->hash;
                $id_sentence_piece++;
                if($id_sentence_piece % 500 === 0){
//                    $data_embedding_sentence_piece->set('embedding', $embeddings);
//                    $float_sort_list = Sort::list($float_list)->with(['count' => 'desc']);
//                    $data_float->set('float', $float_sort_list);
//                    $data->set('word', $words);
//                    $data->set('sentence_piece', $sentence_pieces);
//                    $data->write($source);
//                    $data_embedding_sentence_piece->write($source_embedding_sentence_piece);
//                    $data_float->write($source_float);
                    /*
                    File::permission($object ,[
                        'dir_data' => $dir_data,
                        'dir_search' => $dir_search,
                        'dir_version' => $dir_version,
                        'source' => $source,
//                        'source_float' => $source_float,
                        'source_embedding' => $source_embedding_sentence_piece
                    ]);
                    */
                    echo 'Counter: ' . $id_sentence_piece . PHP_EOL;
                }
            } else {
                if(!property_exists($sentence_piece, 'count')){
                    $sentence_piece->count = 1;
                }
                $sentence_piece->count++;
            }
        }
        $data_embedding_sentence_piece->set('embedding', $embeddings);
//        $float_sort_list = Sort::list($float_list)->with(['count' => 'desc']);
//        $data_float->set('float', $float_sort_list);
//        $data->set('word', $words);
        $data->set('sentence_piece', $sentence_pieces);
        $data->write($source);
        $data_embedding_sentence_piece->write($source_embedding_sentence_piece);
//        $data_float->write($source_float);
        File::permission($object ,[
            'dir_data' => $dir_data,
            'dir_search' => $dir_search,
            'dir_version' => $dir_version,
            'source' => $source,
//            'source_float' => $source_float,
            'source_embedding' => $source_embedding_sentence_piece
        ]);
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

        /*
        $ch = curl_init();
        // Set the URL of the localhost
        curl_setopt($ch, CURLOPT_URL, "http://localhost:11434/api/embed");
        // Set the POST method
        curl_setopt($ch, CURLOPT_POST, true);
        // Set the POST fields

        $post = [
            'model' => $model,
            'input'=> str_replace(["\\", '\''], ['\\\\', '&apos;'], $text)
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        // Disable CURLOPT_RETURNTRANSFER to output directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        // Set option to receive data in chunks
        $result = [];
        curl_setopt($ch, CURLOPT_TIMEOUT, 2 * 3600);           // 120 minutes for the full request
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    // 10 seconds for the connection

//        $data = [];

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($options) {
//            $data[] = $chunk;
            //make abort happen here
            // Output each chunk as it comes in
                    echo $chunk;
            // Optionally flush the output buffer to ensure it's displayed immediately
                    flush();
            // Return the number of bytes processed in this chunk
            return strlen($chunk);
        });
        curl_exec($ch);
        if (curl_errno($ch)) {
            //restart ollama ? need to record curl errors and if 5 or more, or specific error like cannot connect to http server
            // restart ollama
            // app raxon/ollama stop (stops ollama)
            // app raxon/ollama start & (starts ollama)
            echo 'Curl error: ' . curl_error($ch);
        }
        // Close the cURL session
        curl_close($ch);
        */
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

    /**
     * @throws ObjectException
     */
    public function get_embedding_sentence_piece(array $embeddings): array
    {
        $record = [];
        foreach($embeddings as $nr => $embedding){
            if(is_string($embedding->embedding)){
                $embedding->embedding_decode = Core::object(gzdecode(base64_decode($embedding->embedding)), Core::OBJECT_ARRAY);
            }
            foreach($embedding->embedding_decode as $embedding_nr => $id_float){
                if(!array_key_exists($nr, $record)){
                    $record[$nr] = [];
                }
                $record[$nr][$embedding_nr] = $id_float;
            }
            unset($embedding->embedding_decode);
        }
        return $record;

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


