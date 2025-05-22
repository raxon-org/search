<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;

trait Embedding {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function word(object $flags, object $options): void
    {
        $object = $this->object();
        $source = $object->config('controller.dir.data') . 'Search' . $object->config('extension.json');
        $source_embedding = $object->config('controller.dir.data') . 'Search.Embedding.Word' . $object->config('extension.json');
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
        $id_embedding = $data->get('id.embedding.word') ?? 0;
        $id_embedding++;
        foreach($words as $word){
            $hash = hash('sha256', $word->word);
            if(!property_exists($embeddings, $hash)){
                $get_embedding = $this->get_embedding($word->word);
                $embedding = (object) [
                    'id' => $id_embedding,
                    'embedding' => $get_embedding->get('embeddings.0'),
                    'model' => $get_embedding->get('model'),
                    'tokens' => $get_embedding->get('prompt_eval_count'),
                ];
                $embeddings->{$hash} = $embedding;
                $data->set('id.embedding.word', $id_embedding);
                $id_embedding++;
            } else {
                $embedding = $embeddings->{$hash};
            }
            $word->embedding = $embedding->id;
            $word->tokens = $embedding->tokens;
        }
        $data_embedding->set('embedding', $embeddings);

        $data->set('word', $words);
        $data->write($source);
        $data_embedding->write($source_embedding);
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
                $get_embedding = $this->get_embedding($text);
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
                $paragraph = $paragraph_embeddings_list[$paragraph_id];
                $document_embeddings[] = $paragraph->embedding;
                $tokens += $paragraph->tokens;
            }
            $set = [];
            foreach($document_embeddings as $document_embedding){
                foreach($document_embedding as $nr => $float){
                    $set[$nr][] = $float;
                }
            }
            d($tokens);
            ddd($set);
        }


        /*
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
        */
    }

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

    public function array_average(array $list=[]): float
    {
        if(empty($list)){
            return 0;
        }
        $sum = array_sum($list);
        $count = count($list);
        return $sum / $count;
    }
}


